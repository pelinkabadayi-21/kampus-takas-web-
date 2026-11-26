<?php
/**
 * YAPAY ZEKÂ MODELİ SÖZLEŞMESİ (Hafta 10)
 *
 * Öneri servisi hangi yapay zekâ şirketinin kullanıldığını bilmez; sadece bu arayüzü kullanır.
 * Şu an tek uygulaması GeminiProvider. Başka bir modele geçmek için sadece yeni bir sınıf yazılır.
 * Testte sahte bir sağlayıcı verilir (gerçek API'ye istek gitmez, kota harcanmaz).
 */
interface LlmProvider
{
    /** Sağlayıcının adı (günlük için): "gemini" */
    public function name(): string;

    /** Kullanılan model (günlük için): ör. "gemini-flash-lite-latest" */
    public function model(): string;

    /**
     * Modelden JSON biçiminde cevap ister ve HAM metni döndürür (ayrıştırma ve doğrulama servisin işi).
     *
     * @param string     $system  Sistem talimatı: modelin rolü ve kurallar
     * @param string     $user    Kullanıcı mesajı: ilan verisi ve site verisi
     * @param array      $schema  Beklenen JSON yapısı
     * @param array|null $image   ['mime' => 'image/jpeg', 'data' => ikili veri] veya null
     * @throws ExternalApiException  Servis hatası, zaman aşımı, engellenmiş yanıt
     */
    public function generateJson(string $system, string $user, array $schema, ?array $image = null, ?int $userId = null): string;
}
