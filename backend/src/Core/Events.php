<?php
/**
 * OLAY (EVENT) SİSTEMİ - "Bildirim kancası"
 *
 * Servisler bir şey olduğunda sadece olayı duyurur: Events::emit('offer.accepted', [...]).
 * Bu olayı kimin dinlediğini bilmezler. Dinleyiciler ayrı bir yerde kaydedilir.
 *
 * Faydası: Mesaj ve teklif servisleri bildirim tablosuna bağımlı değildir. Yarın e-posta
 * bildirimi eklenmek istenirse servislere dokunmadan yeni bir dinleyici eklemek yeterlidir.
 */
class Events
{
    /** @var array<string, callable[]> */
    private static array $listeners = [];

    /** Bir olayı dinleyecek fonksiyonu kaydeder (ör. "offer.accepted" olunca bildirim yaz). */
    public static function on(string $event, callable $listener): void
    {
        // Dinleyiciyi o olayın listesine ekle
        self::$listeners[$event][] = $listener;
    }

    /** Olayı duyurur: o olayı dinleyen bütün fonksiyonları sırayla çalıştırır. */
    public static function emit(string $event, array $payload = []): void
    {
        // Olayı dinleyen her fonksiyonu olay bilgisiyle çalıştır
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
