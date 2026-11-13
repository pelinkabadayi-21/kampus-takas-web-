<?php
class Request
{
    /** İstek gövdesindeki JSON'u diziye çevirir. */
    public static function json(): array
    {
        // İsteğin gövdesindeki JSON metnini PHP dizisine çevir
        $data = json_decode(file_get_contents('php://input') ?: '[]', true);
        // Geçerli JSON değilse 400 hatası
        if (!is_array($data)) throw new ValidationException('Geçersiz JSON gövdesi', [], 400);
        return $data;
    }
}
