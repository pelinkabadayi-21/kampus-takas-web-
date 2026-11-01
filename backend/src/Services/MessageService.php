<?php
class MessageService
{
    public function __construct(private MessageRepository $messages, private ListingRepository $listings,
                                private NotificationRepository $notifications) {}

    public function send(int $listingId, int $senderId, int $receiverId, string $body): int
    {
        $body = trim($body);
        if ($senderId === $receiverId) throw new ValidationException('Kendinize mesaj gönderemezsiniz');
        if ($body === '' || mb_strlen($body) > 2000) throw new ValidationException('Mesaj 1-2000 karakter olmalı');
        $listing = $this->listings->findById($listingId) ?? throw new ValidationException('İlan bulunamadı', [], 404);
        // Konuşmanın bir tarafı mutlaka ilan sahibi olmalı
        if (!in_array((int) $listing['user_id'], [$senderId, $receiverId], true)) throw new ValidationException('Mesaj ilan sahibiyle olmalı');

        $id = $this->messages->create($listingId, $senderId, $receiverId, $body);
        $this->notifications->create($receiverId, 'mesaj', "\"{$listing['title']}\" hakkında yeni mesaj", "mesajlar.html?ilan=$listingId");
        return $id;
    }
}
