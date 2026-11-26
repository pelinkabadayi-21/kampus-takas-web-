<?php
/**
 * HARİCİ API İSTEMCİSİ (Hafta 9)
 *
 * Cloudinary ve Brevo çağrılarının hepsi bu sınıftan geçer. Böylece hata yönetimi tek yerde yazılır:
 *  - Zaman aşımı: bağlantı en fazla 3 sn, cevap en fazla 5–10 sn beklenir. Servis cevap vermezse
 *    kullanıcının isteği sonsuza kadar asılı kalmaz.
 *  - Yeniden deneme: Sadece GEÇİCİ hatalarda (ağ hatası, zaman aşımı, 429, 5xx) tekrar denenir.
 *    4xx hatalar (yanlış anahtar, eksik alan) tekrar denense de düzelmez, hemen bırakılır.
 *  - Hız sınırı (rate limit): Servis 429 "çok fazla istek" derse "Retry-After" başlığındaki süre
 *    kadar beklenir (en fazla 3 sn); başlık yoksa bekleme her denemede ikiye katlanır (0,5 → 1 sn).
 *  - Günlük: Her çağrının sonucu (servis, işlem, durum kodu, süre, deneme sayısı, hata) kaydedilir.
 *    API anahtarı veya gönderilen içerik günlüğe YAZILMAZ.
 *
 * Testte gerçek ağa çıkmamak için $transport yerine sahte bir fonksiyon verilebilir.
 */
class HttpClient
{
    public const MAX_RETRY_WAIT = 3.0;      // Retry-After en fazla bu kadar saniye beklenir

    /** @var callable */
    private $transport;
    /** @var callable */
    private $sleep;
    /** @var callable|null */
    private $logger;

    /**
     * @param string        $service         Günlükte görünecek servis adı (ör. "cloudinary")
     * @param int           $timeout         Cevap için en fazla bekleme (saniye)
     * @param int           $connectTimeout  Bağlantı kurmak için en fazla bekleme (saniye)
     * @param int           $retries         Geçici hatada kaç kez daha denenecek
     * @param callable|null $transport       Asıl HTTP isteğini yapan fonksiyon (varsayılan: cURL)
     * @param callable|null $logger          fn(array $kayit) — her çağrının sonucu
     * @param callable|null $sleep           fn(float $saniye) — testte beklememek için değiştirilir
     */
    public function __construct(
        private string $service,
        private int $timeout = 5,
        private int $connectTimeout = 3,
        private int $retries = 2,
        ?callable $transport = null,
        ?callable $logger = null,
        ?callable $sleep = null
    ) {
        $this->transport = $transport ?? [self::class, 'curlTransport'];
        $this->logger    = $logger;
        $this->sleep     = $sleep ?? fn(float $s) => usleep((int) ($s * 1_000_000));
    }

    /** Günlük fonksiyonunu sonradan bağlamak için (veritabanı bağlantısı gerektiğinde açılsın diye). */
    public function setLogger(?callable $logger): void { $this->logger = $logger; }

    public function service(): string { return $this->service; }

    /**
     * İstek gönderir. Başarılıysa ['status', 'headers', 'body', 'json', 'attempts'] döner;
     * bütün denemeler başarısızsa ExternalApiException fırlatır.
     *
     * @param array|string|null $body  Dizi → multipart/form-data (dosya yükleme), metin → olduğu gibi (JSON)
     * @param array             $ctx   Günlük için: ['action' => 'upload', 'user_id' => 5]
     */
    public function request(string $method, string $url, array $headers = [], array|string|null $body = null, array $ctx = []): array
    {
        $start    = microtime(true);
        $attempts = 0;
        $maxTries = 1 + max(0, $this->retries);
        $lastError = 'bilinmeyen hata';
        $lastStatus = null;

        while ($attempts < $maxTries) {
            $attempts++;
            $res = ($this->transport)($method, $url, $headers, $body, $this->timeout, $this->connectTimeout);
            $status = (int) ($res['status'] ?? 0);

            // 1) Başarılı cevap (2xx)
            if ($status >= 200 && $status < 300) {
                $json = json_decode($res['body'] ?? '', true);
                $this->log($ctx, $status, true, $attempts, $start, null);
                return ['status' => $status, 'headers' => $res['headers'] ?? [], 'body' => $res['body'] ?? '',
                        'json' => is_array($json) ? $json : null, 'attempts' => $attempts];
            }

            // 2) Hatanın ne olduğunu anlaşılır bir metne çevir
            $lastStatus = $status ?: null;
            $lastError  = $status === 0 ? ($res['error'] ?? 'bağlantı hatası') : self::describe($status, $res['body'] ?? '');

            // 3) Kalıcı hata (4xx, 429 hariç): tekrar denemek işe yaramaz
            $retryable = $status === 0 || $status === 429 || $status >= 500;
            if (!$retryable || $attempts >= $maxTries) break;

            // 4) Geçici hata: bekleyip tekrar dene. 429'da servisin söylediği süreye uy.
            $wait = 0.5 * (2 ** ($attempts - 1));                       // 0,5 sn → 1 sn → 2 sn
            $retryAfter = $res['headers']['retry-after'] ?? null;
            if ($status === 429 && is_numeric($retryAfter)) $wait = (float) $retryAfter;
            ($this->sleep)(min($wait, self::MAX_RETRY_WAIT));
        }

        $this->log($ctx, $lastStatus, false, $attempts, $start, $lastError);
        throw new ExternalApiException("{$this->service}: $lastError", $this->service, $lastStatus, $attempts);
    }

    /** HTTP durum kodunu ve servisin hata mesajını kısa bir Türkçe açıklamaya çevirir. */
    private static function describe(int $status, string $body): string
    {
        $base = match (true) {
            $status === 400 => 'geçersiz istek',
            $status === 401, $status === 403 => 'API anahtarı geçersiz veya yetkisiz',
            $status === 404 => 'adres bulunamadı',
            $status === 429 => 'hız sınırı aşıldı (çok fazla istek)',
            $status >= 500  => 'servis geçici olarak hizmet veremiyor',
            default         => 'beklenmeyen cevap',
        };
        // Servisin kendi mesajını ekle (Cloudinary: error.message, Brevo: message)
        $j = json_decode($body, true);
        $detail = $j['error']['message'] ?? $j['message'] ?? null;
        return "HTTP $status, $base" . (is_string($detail) ? ' – ' . mb_substr($detail, 0, 120) : '');
    }

    /** Çağrının sonucunu günlüğe yazar. Günlük yazılamazsa asıl işlem bozulmasın diye hata yutulur. */
    private function log(array $ctx, ?int $status, bool $ok, int $attempts, float $start, ?string $error): void
    {
        if (!$this->logger) return;
        try {
            ($this->logger)([
                'service'     => $this->service,
                'action'      => $ctx['action'] ?? 'istek',
                'user_id'     => $ctx['user_id'] ?? null,
                'status_code' => $status,
                'success'     => $ok ? 1 : 0,
                'attempts'    => $attempts,
                'duration_ms' => (int) round((microtime(true) - $start) * 1000),
                'error'       => $error !== null ? mb_substr($error, 0, 255) : null,
            ]);
        } catch (Throwable $e) {
            error_log('API günlüğü yazılamadı: ' . $e->getMessage());
        }
    }

    /**
     * Gerçek HTTP isteği (cURL). Ağ hatasında status = 0 ve "error" döner; istisna fırlatmaz.
     * @return array{status:int, headers:array<string,string>, body:string, error?:string}
     */
    public static function curlTransport(string $method, string $url, array $headers, array|string|null $body, int $timeout, int $connectTimeout): array
    {
        if (!function_exists('curl_init')) return ['status' => 0, 'headers' => [], 'body' => '', 'error' => 'PHP cURL eklentisi kurulu değil'];

        $respHeaders = [];
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => $connectTimeout,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_SSL_VERIFYPEER => true,                 // sertifika her zaman doğrulanır
            CURLOPT_HTTPHEADER     => array_map(fn($k, $v) => "$k: $v", array_keys($headers), $headers),
            // Cevap başlıklarını küçük harfli anahtarlarla topla (Retry-After için)
            CURLOPT_HEADERFUNCTION => function ($ch, $line) use (&$respHeaders) {
                if (str_contains($line, ':')) {
                    [$k, $v] = explode(':', $line, 2);
                    $respHeaders[strtolower(trim($k))] = trim($v);
                }
                return strlen($line);
            },
        ]);
        if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);

        $out   = curl_exec($ch);
        $errNo = curl_errno($ch);
        $err   = curl_error($ch);
        $code  = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($errNo === CURLE_OPERATION_TIMEDOUT) return ['status' => 0, 'headers' => [], 'body' => '', 'error' => "zaman aşımı ({$timeout} sn)"];
        if ($errNo) return ['status' => 0, 'headers' => [], 'body' => '', 'error' => 'bağlantı hatası: ' . $err];
        return ['status' => $code, 'headers' => $respHeaders, 'body' => (string) $out];
    }
}
