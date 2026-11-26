<?php
/**
 * HARİCİ API: Google Gemini (Hafta 10)
 *
 * Neden Gemini? Google AI Studio'dan alınan anahtarla kullanılabiliyor, metin ve fotoğrafı birlikte
 * anlayabiliyor ve cevabı istediğimiz JSON yapısında verebiliyor (responseSchema).
 *
 * İstek:  POST https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent
 *         Başlık: x-goog-api-key: <anahtar>   (anahtar adreste değil başlıkta → sunucu günlüklerine düşmez)
 * Cevap:  candidates[0].content.parts[].text  → modelin ürettiği JSON metni
 *
 * Zaman aşımı, yeniden deneme ve hız sınırı (429) Hafta 9'daki HttpClient'tan gelir.
 */
class GeminiProvider implements LlmProvider
{
    private const BASE = 'https://generativelanguage.googleapis.com/v1beta/models/';

    /**
     * @param string $apiKey  GİZLİ anahtar (GEMINI_API_KEY)
     * @param string $model   Model adı (AI_MODEL); varsayılan Google'ın güncel hafif modeline yönlenen takma ad
     */
    public function __construct(
        private HttpClient $http,
        private string $apiKey,
        private string $model = 'gemini-flash-lite-latest'
    ) {}

    public function name(): string { return 'gemini'; }
    public function model(): string { return $this->model; }

    public function generateJson(string $system, string $user, array $schema, ?array $image = null, ?int $userId = null): string
    {
        // Mesajın parçaları: önce metin, varsa fotoğraf (base64)
        $parts = [['text' => $user]];
        if ($image) $parts[] = ['inlineData' => ['mimeType' => $image['mime'], 'data' => base64_encode($image['data'])]];

        $body = json_encode([
            'systemInstruction' => ['parts' => [['text' => $system]]],
            'contents'          => [['role' => 'user', 'parts' => $parts]],
            'generationConfig'  => [
                'temperature'      => 0.2,                  // düşük: aynı ilana tutarlı cevap
                'maxOutputTokens'  => 2048,
                'responseMimeType' => 'application/json',   // sadece JSON döndür
                'responseSchema'   => $schema,              // ve bu yapıda döndür
            ],
        ], JSON_UNESCAPED_UNICODE);

        $res = $this->http->request('POST', self::BASE . rawurlencode($this->model) . ':generateContent', [
            'x-goog-api-key' => $this->apiKey,
            'Content-Type'   => 'application/json',
        ], $body, ['action' => 'suggest', 'user_id' => $userId]);

        $j = $res['json'] ?? [];
        // Güvenlik filtresi isteği engellediyse aday cevap gelmez
        if (empty($j['candidates'])) {
            $why = $j['promptFeedback']['blockReason'] ?? 'boş cevap';
            throw new ExternalApiException("gemini: cevap üretilmedi ($why)", 'gemini', $res['status'], $res['attempts']);
        }
        // Bütün metin parçalarını birleştir
        $text = '';
        foreach ($j['candidates'][0]['content']['parts'] ?? [] as $p) $text .= $p['text'] ?? '';
        return $text;   // boş olabilir; doğrulama katmanı yakalar
    }
}
