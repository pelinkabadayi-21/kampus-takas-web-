<?php
class ReviewService
{
    public function __construct(private ReviewRepository $reviews, private TradeOfferRepository $offers) {}

    public function create(int $offerId, int $reviewerId, int $rating, ?string $comment): int
    {
        $offer = $this->offers->findById($offerId) ?? throw new ValidationException('Teklif bulunamadı', [], 404);
        if ($offer['status'] !== 'kabul') throw new ValidationException('Sadece tamamlanan takaslar değerlendirilebilir');
        if ($rating < 1 || $rating > 5) throw new ValidationException('Puan 1-5 arası olmalı');

        // Taraflar: ilan sahibi ve teklif veren. Değerlendiren, karşı tarafı puanlar.
        $owner = (int) $offer['listing_owner_id'];
        $offerer = (int) $offer['offerer_id'];
        if (!in_array($reviewerId, [$owner, $offerer], true)) throw new ValidationException('Bu takasın tarafı değilsiniz', [], 403);
        $reviewed = $reviewerId === $owner ? $offerer : $owner;

        return $this->reviews->create(['trade_offer_id' => $offerId, 'reviewer_id' => $reviewerId,
            'reviewed_id' => $reviewed, 'rating' => $rating, 'comment' => $comment]);
    }
}
