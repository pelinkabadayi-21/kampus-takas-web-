<?php
/** SUNUM KATMANI - Bildirimler */
class NotificationController
{
    public function __construct(private NotificationRepository $repo, private MessageRepository $messages,
                                private TradeOfferRepository $offers) {}

    // GET /api/notifications?unread=1
    public function index(): void
    {
        $u = Auth::requireUser();
        Response::json(['success' => true, 'data' => $this->repo->forUser((int) $u['id'], !empty($_GET['unread']))]);
    }

    // GET /api/notifications/counts   menüdeki rozetler için tek istek
    public function counts(): void
    {
        $id = (int) Auth::requireUser()['id'];
        Response::json(['success' => true, 'data' => [
            'notifications'  => $this->repo->unreadCount($id),
            'messages'       => $this->messages->unreadCount($id),
            'pending_offers' => $this->offers->countPendingReceived($id),
        ]]);
    }

    // PUT /api/notifications/{id}/read
    public function read(int $id): void
    {
        $this->repo->markRead($id, (int) Auth::requireUser()['id']);
        Response::json(['success' => true]);
    }

    // PUT /api/notifications/read-all
    public function readAll(): void
    {
        $n = $this->repo->markAllRead((int) Auth::requireUser()['id']);
        Response::json(['success' => true, 'updated' => $n]);
    }
}
