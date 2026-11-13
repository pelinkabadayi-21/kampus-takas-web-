<?php
/** SUNUM KATMANI - Takas teklifi uçları (hepsi giriş gerektirir) */
class OfferController
{
    /** Teklif servisi ve "değerlendirdim mi" bilgisi için değerlendirme deposu alınır. */
    public function __construct(private TradeOfferService $service, private ?ReviewRepository $reviews = null) {}

    // GET /api/offers   gelen ve giden tekliflerim
    public function mine(): void
    {
        $id = (int) Auth::requireUser()['id'];
        // Gelen/gönderilen teklifler + hangilerini değerlendirdiğim
        Response::json(['success' => true, 'data' => $this->service->mine($id, $this->reviews?->reviewedOfferIds($id) ?? [])]);
    }

    // GET /api/listings/{id}/offers
    public function forListing(int $listingId): void
    {
        // Bir ilana gelen teklifler
        Response::json(['success' => true, 'data' => $this->service->forListing($listingId, Auth::requireUser())]);
    }

    // POST /api/offers   {listing_id, offered_listing_id?, offered_amount?, note?}
    public function store(): void
    {
        // Teklifi oluştur, kaydedilen teklifi geri döndür
        $id = $this->service->create(Request::json(), Auth::requireUser());
        Response::json(['success' => true, 'data' => $this->service->get($id)], 201);
    }

    // PUT /api/offers/{id}/{action}   action: accept | reject | cancel | complete | withdraw
    public function action(int $id, string $action): void
    {
        $u = Auth::requireUser();
        // URL'deki işlem adına göre doğru servis fonksiyonunu çağır
        $data = match ($action) {
            'accept'   => $this->service->accept($id, $u),
            'reject'   => $this->service->reject($id, $u),
            'cancel'   => $this->service->cancel($id, $u),
            'complete' => $this->service->complete($id, $u),
            'withdraw' => $this->service->withdraw($id, $u),
        };
        Response::json(['success' => true, 'data' => $data]);
    }
}
