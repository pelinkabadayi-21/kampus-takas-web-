<?php
class Request
{
    /** İstek gövdesindeki JSON'u diziye çevirir. */
    public static function json(): array
    {
        $data = json_decode(file_get_contents('php://input') ?: '[]', true);
        if (!is_array($data)) throw new ValidationException('Geçersiz JSON gövdesi', [], 400);
        return $data;
    }
}
