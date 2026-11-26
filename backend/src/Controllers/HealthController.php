<?php
/** SUNUM KATMANI (API) - İsteği alır, servisi çağırır, JSON yanıt döner. */
class HealthController
{
    /** Uygulama ayarları alınır (veritabanı bilgileri için). */
    public function __construct(private array $config, private ?Integrations $integrations = null) {}

    /** GET /api/health - API çalışıyor mu, veritabanına bağlanılabiliyor mu bilgisini döndürür. */
    public function index(): void
    {
        // Başta "bağlı değil" kabul et
        $dbOk = false;
        $dbMessage = 'bağlı değil';
        try {
            // Veritabanına basit bir sorgu at; çalışırsa bağlantı var demektir
            Database::connect($this->config['db'])->query('SELECT 1');
            $dbOk = true;
            $dbMessage = 'bağlantı başarılı';
        } catch (Throwable $e) {
            // Hata ayrıntısını sadece geliştirme modunda göster
            $dbMessage = $this->config['app']['debug'] ? $e->getMessage() : 'bağlantı hatası';
        }

        // Ana sayfadaki "API çalışıyor / Veritabanı bağlı" yazıları bu cevaptan gelir
        Response::json([
            'success'  => true,
            'app'      => $this->config['app']['name'],
            'api'      => 'çalışıyor',
            'database' => ['ok' => $dbOk, 'message' => $dbMessage],
            // Hafta 9: hangi harici servis açık (anahtarlar gösterilmez)
            'integrations' => $this->integrations?->status(),
            'time'     => date('c'),
        ]);
    }
}
