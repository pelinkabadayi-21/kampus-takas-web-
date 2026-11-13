<?php
/** SUNUM KATMANI - Mesajlaşma uçları (hepsi giriş gerektirir) */
class MessageController
{
    /** Mesaj servisi ve okunmamış sayısı için mesaj deposu alınır. */
    public function __construct(private MessageService $service, private MessageRepository $repo) {}

    // GET /api/messages   gelen kutusu
    public function inbox(): void
    {
        $u = Auth::requireUser();
        // Gelen kutusu + okunmamış mesaj sayısı
        Response::json(['success' => true, 'data' => $this->service->inbox((int) $u['id']),
                        'unread' => $this->repo->unreadCount((int) $u['id'])]);
    }

    // GET /api/messages/{listingId}/{userId}?after=ID   konuşma (after: sadece yeni mesajlar)
    public function conversation(int $listingId, int $otherId): void
    {
        $u = Auth::requireUser();
        // ?after=ID ile sadece yeni mesajlar istenebilir
        Response::json(['success' => true] + $this->service->conversation($listingId, $u, $otherId, (int) ($_GET['after'] ?? 0)));
    }

    // POST /api/messages   {listing_id, receiver_id?, body}
    public function send(): void
    {
        $u = Auth::requireUser();
        $d = Request::json();
        // Mesajı gönder (alıcı, ilan, spam kuralları serviste)
        $msg = $this->service->send((int) ($d['listing_id'] ?? 0), $u,
                                    isset($d['receiver_id']) ? (int) $d['receiver_id'] : null, (string) ($d['body'] ?? ''));
        Response::json(['success' => true, 'data' => $msg], 201);
    }
}
