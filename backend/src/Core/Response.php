<?php
/** Tüm API yanıtlarını tek biçimde JSON olarak döndürür. */
class Response
{
    /** Veriyi JSON olarak, verilen HTTP durum koduyla (200, 201, 404...) gönderir ve çalışmayı bitirir. */
    public static function json($data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    /** Hata yanıtı gönderir: {"success": false, "error": "mesaj"} */
    public static function error(string $message, int $status = 400): void
    {
        self::json(['success' => false, 'error' => $message], $status);
    }
}
