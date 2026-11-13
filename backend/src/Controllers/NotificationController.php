<?php
/** SUNUM KATMANI - Bildirimler */
class NotificationController
{
    /** Bildirim, mesaj ve teklif depoları alınır (menü sayaçları için). */
    public function __construct(private NotificationRepository $repo, private MessageRepository $messages,
                                private TradeOfferRepository $offers) {}

    // GET /api/notifications?unread=1
    public function index(): void
    {
        $u = Auth::requireUser();
        // ?unread=1 ise sadece okunmamış bildirimler
        Response::json(['success' => true, 'data' => $this->repo->forUser((int) $u['id'], !empty($_GET['unread']))]);
    }

    // GET /api/notifications/counts   menüdeki rozetler için tek istek
    public function counts(): void
    {
        $id = (int) Auth::requireUser()['id'];
        // Menüdeki 🔔 💬 🔄 sayaçları için üç sayı
        Response::json(['success' => true, 'data' => [
            'notifications'  => $this->repo->unreadCount($id),
            'messages'       => $this->messages->unreadCount($id),
            'pending_offers' => $this->offers->countPendingReceived($id),
        ]]);
    }

    // PUT /api/notifications/{id}/read
    public function read(int $id): void
    {
        // Bildirimi okundu yap (sadece kendi bildirimin)
        $this->repo->markRead($id, (int) Auth::requireUser()['id']);
        Response::json(['success' => true]);
    }

    // PUT /api/notifications/read-all
    public function readAll(): void
    {
        // Hepsini okundu yap, kaç tane güncellendiğini döndür
        $n = $this->repo->markAllRead((int) Auth::requireUser()['id']);
        Response::json(['success' => true, 'updated' => $n]);
    }
}
