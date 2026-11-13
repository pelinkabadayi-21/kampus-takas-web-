<?php
/**
 * İŞ KATMANI - Mesajlaşma
 *
 * İŞ KURALLARI:
 *  M1  Mesaj 1-2000 karakter; boşluklar kırpılır
 *  M2  Kişi kendine mesaj atamaz
 *  M3  Her konuşma bir ilana bağlıdır ve taraflardan biri mutlaka ilan sahibidir.
 *      İlan sahibi olmayan kişi her zaman ilan sahibine yazar (alıcı istekten alınmaz).
 *  M4  İlan sahibi, konuşmayı kendisi BAŞLATAMAZ; sadece kendisine yazan ya da teklif veren
 *      kişiye cevap verebilir (istenmeyen mesaj / spam engeli)
 *  M5  Yeni konuşma sadece yayında veya rezerve ilanlarda başlar; kapanmış ilanda
 *      sadece mevcut konuşma sürer
 *  M6  Dakikada en fazla 10 mesaj (flood koruması)
 *  M7  Konuşmayı sadece iki taraf okuyabilir; okununca "okundu" işaretlenir
 */
class MessageService
{
    public const MAX_PER_MINUTE = 10;

    /** Mesaj, ilan ve teklif depoları alınır. */
    public function __construct(
        private MessageRepository $messages,
        private ListingRepository $listings,
        private ?TradeOfferRepository $offers = null
    ) {}

    /** @param array|int $sender Oturumdaki kullanıcı (geriye uyumluluk için id de olabilir) */
    public function send(int $listingId, array|int $sender, ?int $receiverId, string $body): array
    {
        // Gönderen bilgisi ve boşlukları temizlenmiş mesaj metni
        $sender   = is_array($sender) ? $sender : ['id' => $sender];
        $senderId = (int) $sender['id'];
        $body     = trim($body);

        if ($body === '' || mb_strlen($body) > 2000) throw new ValidationException('Mesaj 1-2000 karakter olmalı', ['body' => 'Mesaj 1-2000 karakter olmalı']);   // M1

        $listing = $this->listings->findById($listingId) ?? throw new ValidationException('İlan bulunamadı', [], 404);
        $ownerId = (int) $listing['user_id'];

        // M3: alıcıyı belirle
        // İlan sahibi değilsem mesaj her zaman ilan sahibine gider
        if ($senderId !== $ownerId) {
            $receiverId = $ownerId;
        } elseif (!$receiverId) {
            throw new ValidationException('Kime cevap verdiğinizi seçin');
        }
        if ($senderId === (int) $receiverId) throw new ValidationException('Kendinize mesaj gönderemezsiniz');                        // M2

        // Bu iki kişi arasında bu ilanla ilgili daha önce konuşma var mı
        $existing = $this->messages->hasConversation($listingId, $senderId, (int) $receiverId);

        // M4
        // İlan sahibi, kendisine yazmamış ya da teklif vermemiş birine ilk mesajı atamaz
        if ($senderId === $ownerId && !$existing && !($this->offers?->pendingExists($listingId, (int) $receiverId))) {
            throw new ValidationException('İlan sahibi konuşma başlatamaz; sadece size yazan ya da teklif veren kişiye cevap verebilirsiniz', [], 403);
        }
        // M5
        // Satılmış/kapalı ilan için yeni konuşma başlatılamaz
        if (!$existing && !in_array($listing['status'], ['aktif', 'rezerve'], true)) {
            throw new ValidationException('Bu ilan yayında değil; yeni konuşma başlatılamaz');
        }
        // M6
        // Spam koruması: son 1 dakikada 10'dan fazla mesaj atılamaz
        if ($this->messages->sentSince($senderId, date('Y-m-d H:i:s', time() - 60)) >= self::MAX_PER_MINUTE) {
            throw new ValidationException('Çok hızlı mesaj gönderiyorsunuz, biraz bekleyin', [], 429);
        }

        // Mesajı veritabanına kaydet
        $id = $this->messages->create($listingId, $senderId, (int) $receiverId, $body, date('Y-m-d H:i:s'));

        Events::emit('message.sent', [                                           // bildirim kancası
            'message_id' => $id, 'listing_id' => $listingId, 'listing_title' => $listing['title'],
            'sender_id' => $senderId, 'sender_name' => $sender['full_name'] ?? 'Bir kullanıcı',
            'receiver_id' => (int) $receiverId,
        ]);
        // Kaydedilen mesajı geri döndür (ekranda hemen gösterilir)
        return $this->messages->findById($id);
    }

    /** M7: Konuşmayı getir ve karşı tarafın mesajlarını okundu yap. */
    public function conversation(int $listingId, array $viewer, int $otherId, int $afterId = 0): array
    {
        $listing = $this->listings->findById($listingId) ?? throw new ValidationException('İlan bulunamadı', [], 404);
        $me = (int) $viewer['id'];
        $owner = (int) $listing['user_id'];
        // Konuşmanın taraflarından biri mutlaka ilan sahibi olmalı
        if ($me !== $owner && $otherId !== $owner) {
            throw new ValidationException('Bu konuşmaya erişiminiz yok', [], 403);
        }
        // Konuşmayı açınca bana gelen mesajları okundu yap
        $this->messages->markRead($listingId, $me, $otherId);
        return [
            'listing'  => ['id' => $listing['id'], 'title' => $listing['title'], 'status' => $listing['status'],
                           'user_id' => $owner, 'price' => $listing['price'], 'listing_type' => $listing['listing_type'],
                           'cover_image' => $listing['images'][0]['image_url'] ?? null],
            // afterId: sadece bu id'den sonraki yeni mesajlar (sayfa yenilemeden güncelleme)
            'messages' => $this->messages->conversation($listingId, $me, $otherId, $afterId),
        ];
    }

    /** Gelen kutusu: her konuşma için son mesaj ve okunmamış sayısı. */
    public function inbox(int $userId): array
    {
        return $this->messages->inbox($userId);
    }
}
