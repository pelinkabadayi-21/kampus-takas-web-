<?php
/** İŞ KATMANI - Takas teklifi kuralları ve kabul işlemi (transaction örneği). */
class TradeOfferService
{
    public function __construct(
        private TradeOfferRepository $offers,
        private ListingRepository $listings,
        private NotificationRepository $notifications
    ) {}

    public function create(array $d): int
    {
        $listing = $this->listings->findById((int) ($d['listing_id'] ?? 0));
        if (!$listing || $listing['status'] !== 'aktif') throw new ValidationException('İlan teklife açık değil');
        if ((int) $listing['user_id'] === (int) $d['offerer_id']) throw new ValidationException('Kendi ilanınıza teklif veremezsiniz');
        if (empty($d['offered_listing_id']) && ($d['offered_amount'] ?? '') === '') {
            throw new ValidationException('Karşılığında bir ilan veya tutar önermelisiniz');
        }
        if (!empty($d['offered_listing_id'])) {
            $mine = $this->listings->findById((int) $d['offered_listing_id']);
            if (!$mine || (int) $mine['user_id'] !== (int) $d['offerer_id']) throw new ValidationException('Sadece kendi ilanınızı önerebilirsiniz');
        }

        return $this->offers->transaction(function () use ($d, $listing) {
            $id = $this->offers->create($d);
            $this->notifications->create((int) $listing['user_id'], 'teklif',
                "\"{$listing['title']}\" ilanınıza yeni bir takas teklifi geldi", "ilan.html?id={$listing['id']}");
            return $id;
        });
    }

    /**
     * Teklifi kabul et. 4 adım TEK İŞLEM (transaction) içinde yapılır:
     * biri bile başarısız olursa hiçbiri kaydedilmez, veri yarım kalmaz.
     */
    public function accept(int $offerId, int $ownerId): void
    {
        $offer = $this->offers->findById($offerId) ?? throw new ValidationException('Teklif bulunamadı', [], 404);
        if ((int) $offer['listing_owner_id'] !== $ownerId) throw new ValidationException('Bu teklifi sadece ilan sahibi kabul edebilir', [], 403);
        if ($offer['status'] !== 'beklemede') throw new ValidationException('Teklif artık beklemede değil');

        $this->offers->transaction(function () use ($offer) {
            $this->offers->setStatus((int) $offer['id'], 'kabul');                               // 1
            $this->offers->rejectOthers((int) $offer['listing_id'], (int) $offer['id']);         // 2
            $this->listings->setStatus((int) $offer['listing_id'], 'rezerve');                   // 3
            if ($offer['offered_listing_id']) $this->listings->setStatus((int) $offer['offered_listing_id'], 'rezerve');
            $this->notifications->create((int) $offer['offerer_id'], 'teklif_sonuc',            // 4
                "\"{$offer['listing_title']}\" için teklifiniz kabul edildi 🎉", "ilan.html?id={$offer['listing_id']}");
        });
    }
}
