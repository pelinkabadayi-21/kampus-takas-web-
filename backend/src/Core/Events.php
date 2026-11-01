<?php
/**
 * OLAY (EVENT) SİSTEMİ - "Bildirim kancası"
 *
 * Servisler bir şey olduğunda sadece olayı duyurur: Events::emit('offer.accepted', [...]).
 * Bu olayı kimin dinlediğini bilmezler. Dinleyiciler (listener) ayrı bir yerde kaydedilir.
 *
 * Faydası: Mesaj ve teklif servisleri bildirim tablosuna bağımlı değildir. Yarın e-posta
 * bildirimi eklenmek istenirse servislere dokunmadan yeni bir dinleyici eklemek yeterlidir.
 */
class Events
{
    /** @var array<string, callable[]> */
    private static array $listeners = [];

    public static function on(string $event, callable $listener): void
    {
        self::$listeners[$event][] = $listener;
    }

    public static function emit(string $event, array $payload = []): void
    {
        foreach (self::$listeners[$event] ?? [] as $listener) {
            $listener($payload);
        }
    }

    /** Testlerde temiz başlangıç için */
    public static function reset(): void
    {
        self::$listeners = [];
    }
}
