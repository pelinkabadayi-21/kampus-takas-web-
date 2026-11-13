<?php
/** İş kuralı ihlallerinde fırlatılır; API bunu 422 hatasına çevirir. */
class ValidationException extends RuntimeException
{
    /** Hata mesajı, alan bazlı hatalar ve HTTP kodu (varsayılan 422) ile oluşturulur. */
    public function __construct(string $message, public array $errors = [], int $code = 422)
    {
        parent::__construct($message, $code);
    }
}
