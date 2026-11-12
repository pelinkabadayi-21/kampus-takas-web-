<?php
/** SUNUM KATMANI - Değerlendirme uçları */
class ReviewController
{
    public function __construct(private ReviewService $service) {}

    // POST /api/reviews   {trade_offer_id, rating, comment?}   (giriş gerekli)
    public function store(): void
    {
        $u = Auth::requireUser();
        $d = Request::json();
        $id = $this->service->create((int) ($d['trade_offer_id'] ?? 0), (int) $u['id'], (int) ($d['rating'] ?? 0), $d['comment'] ?? null);
        Response::json(['success' => true, 'id' => $id], 201);
    }

    // GET /api/users/{id}/reviews   (herkese açık)
    public function forUser(int $userId): void
    {
        Response::json(['success' => true, 'data' => $this->service->forUser($userId)]);
    }
}
