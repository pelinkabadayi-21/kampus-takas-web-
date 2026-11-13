<?php
/** Tüm API yanıtlarını tek biçimde JSON olarak döndürür. */
class Response
{
    /** Veriyi JSON olarak, verilen HTTP durum koduyla (200, 201, 404...) gönderir ve çalışmayı bitirir. */
    public static function json($data, int $status = 200): void
    {
        // HTTP durum kodunu ayarla (200 başarılı, 404 bulunamadı, 422 form hatası...)
        http_response_code($status);
        // Tarayıcıya cevabın JSON olduğunu söyle
        header('Content-Type: application/json; charset=utf-8');
        // Diziyi JSON metnine çevirip yaz (Türkçe harfler bozulmadan)
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        // Cevap gönderildi, PHP çalışmayı bitirsin
        exit;
    }

    /** Hata yanıtı gönderir: {"success": false, "error": "mesaj"} */
    public static function error(string $message, int $status = 400): void
    {
        self::json(['success' => false, 'error' => $message], $status);
    }
}
