<?php
/**
 * İŞ KATMANI - Değerlendirme (puan + yorum)
 *
 * KURALLAR:
 *  R1  Sadece TAMAMLANMIŞ takas değerlendirilebilir (teklif kabul + ilan tamamlandı)
 *  R2  Sadece takasın iki tarafı değerlendirebilir; herkes karşı tarafı puanlar
 *  R3  Puan 1-5; yorum en fazla 500 karakter
 *  R4  Bir takas için her kişi en fazla bir kez değerlendirir (veritabanında UNIQUE)
 */
class ReviewService
{
    /** Değerlendirme ve teklif depoları alınır. */
    public function __construct(private ReviewRepository $reviews, private TradeOfferRepository $offers) {}

    /** Değerlendirme kaydeder; önce R1-R4 kurallarını kontrol eder, sonra bildirim gönderir. */
    public function create(int $offerId, int $reviewerId, int $rating, ?string $comment): int
    {
        $offer = $this->offers->findById($offerId) ?? throw new ValidationException('Teklif bulunamadı', [], 404);
        // R1: Sadece tamamlanmış takaslar değerlendirilebilir
        if ($offer['status'] !== 'kabul' || $offer['listing_status'] !== 'tamamlandi') {                       // R1
            throw new ValidationException('Sadece tamamlanan takaslar değerlendirilebilir');
        }
        if ($rating < 1 || $rating > 5) throw new ValidationException('Puan 1-5 arası olmalı', ['rating' => 'Puan 1-5 arası olmalı']);   // R3
        // Yorum boşsa null olarak kaydet
        $comment = trim((string) $comment) ?: null;
        if ($comment && mb_strlen($comment) > 500) throw new ValidationException('Yorum en fazla 500 karakter olabilir', ['comment' => 'En fazla 500 karakter']);

        $owner = (int) $offer['listing_owner_id'];                                                                   // R2
        $offerer = (int) $offer['offerer_id'];
        if (!in_array($reviewerId, [$owner, $offerer], true)) throw new ValidationException('Bu takasın tarafı değilsiniz', [], 403);
        // Değerlendirilen kişi: karşı taraf
        $reviewed = $reviewerId === $owner ? $offerer : $owner;

        if ($this->reviews->exists($offerId, $reviewerId)) throw new ValidationException('Bu takası zaten değerlendirdiniz');   // R4

        // Değerlendirmeyi kaydet ve karşı tarafa bildirim gönder
        $id = $this->reviews->create(['trade_offer_id' => $offerId, 'reviewer_id' => $reviewerId,
            'reviewed_id' => $reviewed, 'rating' => $rating, 'comment' => $comment]);
        Events::emit('review.created', ['reviewed_id' => $reviewed, 'rating' => $rating, 'listing_title' => $offer['listing_title']]);
        return $id;
    }

    /** Bir kullanıcının aldığı değerlendirmeler ve ortalaması (herkese açık) */
    public function forUser(int $userId): array
    {
        // Ortalama puan + tüm yorumlar
        return ['summary' => $this->reviews->summary($userId), 'items' => $this->reviews->forUser($userId)];
    }
}
