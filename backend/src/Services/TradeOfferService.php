<?php
/**
 * İŞ KATMANI - Takas teklifi
 *
 * DURUM MAKİNESİ (trade_offers.status):
 *
 *     beklemede ──(sahip kabul eder)──> kabul ──(taraflardan biri "tamamlandı" der)──> [ilanlar: tamamlandi]
 *        │                               │
 *        ├─(sahip reddeder)──> red       └─(taraflardan biri vazgeçer)──> iptal [ilanlar tekrar aktif]
 *        └─(teklif veren geri çeker)──> iptal
 *
 * İŞ KURALLARI:
 *  T1  Sadece yayındaki (aktif) ilana teklif verilir; kendi ilanına teklif verilmez
 *  T2  Aynı ilana aynı kişiden aynı anda tek bekleyen teklif olur
 *  T3  İlan türüne göre teklif: satış -> tutar zorunlu; takas -> ilan ve/veya tutar;
 *      bağış -> sadece talep (ilan/tutar verilmez)
 *  T4  Karşılığında önerilen ilan teklif verene ait, yayında ve hedef ilandan farklı olmalı
 *  T5  Tutar 0 - 100.000 ₺; not en fazla 255 karakter
 *  T6  Kabul/red sadece ilan sahibi; geri çekme sadece teklif veren; tamamlama/vazgeçme iki taraf
 *  T8  (Öğrenci kuralı) Satılık ilanda teklif, ilan fiyatının yarısından az olamaz
 *  T7  Kabul TEK İŞLEMDE (transaction): teklif kabul, diğer bekleyen teklifler kapanır,
 *      iki ilan da rezerve olur, rezerve ilanları içeren başka bekleyen teklifler iptal edilir
 */
class TradeOfferService
{
    public function __construct(
        private TradeOfferRepository $offers,
        private ListingRepository $listings,
        mixed $legacyNotifications = null     // eski imza (Hafta 4) ile uyumluluk; bildirimler artık olaylarla
    ) {}

    private static function actor(array|int $a): array
    {
        return is_array($a) ? $a : ['id' => $a, 'role' => 'ogrenci'];
    }

    private function now(): string { return date('Y-m-d H:i:s'); }

    public function get(int $id): array
    {
        return $this->offers->findById($id) ?? throw new ValidationException('Teklif bulunamadı', [], 404);
    }

    // ------------------------------------------------------------------ OLUŞTUR
    public function create(array $d, array|int|null $actor = null): int
    {
        $actor     = $actor === null ? ['id' => (int) ($d['offerer_id'] ?? 0)] : self::actor($actor);
        $offererId = (int) $actor['id'];
        $listing   = $this->listings->findById((int) ($d['listing_id'] ?? 0)) ?? throw new ValidationException('İlan bulunamadı', [], 404);

        if ($listing['status'] !== 'aktif') throw new ValidationException('Bu ilan teklife açık değil');                              // T1
        if ((int) $listing['user_id'] === $offererId) throw new ValidationException('Kendi ilanınıza teklif veremezsiniz');
        if ($this->offers->pendingExists((int) $listing['id'], $offererId)) {                                                      // T2
            throw new ValidationException('Bu ilana zaten bekleyen bir teklifiniz var');
        }

        $offeredId = !empty($d['offered_listing_id']) ? (int) $d['offered_listing_id'] : null;
        $amount    = ($d['offered_amount'] ?? '') === '' || $d['offered_amount'] === null ? null : $d['offered_amount'];
        $note      = trim((string) ($d['note'] ?? '')) ?: null;

        $e = [];
        switch ($listing['listing_type']) {                                                                                       // T3
            case 'satis':
                if ($offeredId) $e['offered_listing_id'] = 'Satılık ilana ürün değil, fiyat teklif edilir';
                if ($amount === null) $e['offered_amount'] = 'Teklif ettiğiniz tutarı yazın';
                // ÖĞRENCİ KURALI (Hafta 7): Satılık ilana ilan fiyatının yarısından az teklif verilemez.
                // Amaç: Satıcıyı çok düşük, ciddi olmayan tekliflerle uğraştırmamak.
                elseif (is_numeric($amount) && $listing['price'] !== null && $amount < $listing['price'] / 2) {
                    $e['offered_amount'] = 'Teklif, ilan fiyatının yarısından (' . number_format($listing['price'] / 2, 0, ',', '.') . ' ₺) az olamaz';
                }
                break;
            case 'takas':
                if (!$offeredId && $amount === null) $e['offered_listing_id'] = 'Karşılığında bir ilanınızı ve/veya tutar önerin';
                break;
            case 'bagis':
                $offeredId = null; $amount = null;   // bağışta sadece talep edilir
                break;
        }
        if ($amount !== null) {                                                                                                     // T5
            if (!is_numeric($amount) || $amount < 0) $e['offered_amount'] = 'Tutar 0 veya daha büyük olmalı';
            elseif ($amount > ListingService::MAX_PRICE) $e['offered_amount'] = 'Tutar en fazla 100.000 ₺ olabilir';
            else $amount = round((float) $amount, 2);
        }
        if ($note && mb_strlen($note) > 255) $e['note'] = 'Not en fazla 255 karakter olabilir';

        if ($offeredId) {                                                                                                           // T4
            $mine = $this->listings->findById($offeredId);
            if (!$mine || (int) $mine['user_id'] !== $offererId) $e['offered_listing_id'] = 'Sadece kendi ilanınızı önerebilirsiniz';
            elseif ($mine['status'] !== 'aktif') $e['offered_listing_id'] = 'Önerdiğiniz ilan yayında olmalı';
            elseif ($offeredId === (int) $listing['id']) $e['offered_listing_id'] = 'Aynı ilan önerilemez';
        }
        if ($e) throw new ValidationException('Teklifte hatalar var', $e);

        $id = $this->offers->create(['listing_id' => $listing['id'], 'offerer_id' => $offererId,
            'offered_listing_id' => $offeredId, 'offered_amount' => $amount, 'note' => $note]);

        Events::emit('offer.created', ['offer_id' => $id, 'listing_id' => (int) $listing['id'], 'listing_title' => $listing['title'],
            'owner_id' => (int) $listing['user_id'], 'offerer_name' => $actor['full_name'] ?? 'Bir kullanıcı']);
        return $id;
    }

    // ------------------------------------------------------------------ KABUL (T7)
    public function accept(int $offerId, array|int $actor): array
    {
        $actor = self::actor($actor);
        $o = $this->get($offerId);
        $this->assertOwner($o, $actor);
        if ($o['status'] !== 'beklemede') throw new ValidationException('Sadece bekleyen teklif kabul edilebilir');
        if ($o['listing_status'] !== 'aktif') throw new ValidationException('İlan artık yayında değil');
        if ($o['offered_listing_id'] && $o['offered_listing_status'] !== 'aktif') {
            throw new ValidationException('Karşılığında önerilen ilan artık yayında değil; teklif geçersiz');
        }

        $reserved = array_values(array_filter([(int) $o['listing_id'], (int) $o['offered_listing_id']]));

        $autoClosed = $this->offers->transaction(function () use ($o, $reserved) {
            $now = $this->now();
            $this->offers->setStatus((int) $o['id'], 'kabul', $now);                     // 1. teklif kabul
            foreach ($reserved as $lid) $this->listings->setStatus($lid, 'rezerve');      // 2. ilanlar rezerve
            $closed = $this->offers->pendingInvolving($reserved, (int) $o['id']);         // 3. geçersizleşen teklifler
            foreach ($closed as $c) {
                // aynı ilana gelen diğer teklifler "red", ilanı başka yerde rezerve olanlar "iptal"
                $this->offers->setStatus((int) $c['id'], (int) $c['listing_id'] === (int) $o['listing_id'] ? 'red' : 'iptal', $now);
            }
            return $closed;
        });

        Events::emit('offer.accepted', ['offer_id' => (int) $o['id'], 'offerer_id' => (int) $o['offerer_id'],
            'listing_id' => (int) $o['listing_id'], 'listing_title' => $o['listing_title'], 'meeting_point' => $o['meeting_point'],
            'auto_closed' => array_map(fn($c) => ['offerer_id' => (int) $c['offerer_id'], 'listing_title' => $c['listing_title']], $autoClosed)]);
        return $this->get($offerId);
    }

    // ------------------------------------------------------------------ RED
    public function reject(int $offerId, array|int $actor): array
    {
        $actor = self::actor($actor);
        $o = $this->get($offerId);
        $this->assertOwner($o, $actor);
        if ($o['status'] !== 'beklemede') throw new ValidationException('Sadece bekleyen teklif reddedilebilir');
        $this->offers->setStatus($offerId, 'red', $this->now());
        Events::emit('offer.rejected', ['offerer_id' => (int) $o['offerer_id'], 'listing_title' => $o['listing_title']]);
        return $this->get($offerId);
    }

    // ------------------------------------------------------------------ GERİ ÇEK (teklif veren)
    public function cancel(int $offerId, array|int $actor): array
    {
        $actor = self::actor($actor);
        $o = $this->get($offerId);
        if ((int) $o['offerer_id'] !== (int) $actor['id']) throw new ValidationException('Teklifi sadece veren kişi geri çekebilir', [], 403);
        if ($o['status'] !== 'beklemede') throw new ValidationException('Sadece bekleyen teklif geri çekilebilir');
        $this->offers->setStatus($offerId, 'iptal', $this->now());
        Events::emit('offer.cancelled', ['owner_id' => (int) $o['listing_owner_id'], 'listing_title' => $o['listing_title']]);
        return $this->get($offerId);
    }

    // ------------------------------------------------------------------ TAMAMLA (iki taraf)
    public function complete(int $offerId, array|int $actor): array
    {
        $actor = self::actor($actor);
        $o = $this->get($offerId);
        $other = $this->assertParty($o, $actor);
        if ($o['status'] !== 'kabul' || $o['listing_status'] !== 'rezerve') throw new ValidationException('Sadece kabul edilmiş ve devam eden takas tamamlanabilir');

        $this->offers->transaction(function () use ($o) {
            $this->listings->setStatus((int) $o['listing_id'], 'tamamlandi');
            if ($o['offered_listing_id']) $this->listings->setStatus((int) $o['offered_listing_id'], 'tamamlandi');
        });
        Events::emit('offer.completed', ['other_id' => $other, 'listing_title' => $o['listing_title']]);
        return $this->get($offerId);
    }

    // ------------------------------------------------------------------ VAZGEÇ (kabulden sonra, iki taraf)
    public function withdraw(int $offerId, array|int $actor): array
    {
        $actor = self::actor($actor);
        $o = $this->get($offerId);
        $other = $this->assertParty($o, $actor);
        if ($o['status'] !== 'kabul' || $o['listing_status'] !== 'rezerve') throw new ValidationException('Sadece devam eden (rezerve) takastan vazgeçilebilir');

        $this->offers->transaction(function () use ($o) {
            $this->offers->setStatus((int) $o['id'], 'iptal', $this->now());
            $this->listings->setStatus((int) $o['listing_id'], 'aktif');
            if ($o['offered_listing_id'] && $o['offered_listing_status'] === 'rezerve') {
                $this->listings->setStatus((int) $o['offered_listing_id'], 'aktif');
            }
        });
        Events::emit('offer.withdrawn', ['other_id' => $other, 'listing_title' => $o['listing_title']]);
        return $this->get($offerId);
    }

    // ------------------------------------------------------------------ LİSTELER
    /** İlan sahibi tüm teklifleri, diğerleri sadece kendi tekliflerini görür. */
    public function forListing(int $listingId, array $viewer): array
    {
        $l = $this->listings->findById($listingId) ?? throw new ValidationException('İlan bulunamadı', [], 404);
        $all = (int) $l['user_id'] === (int) $viewer['id'] || Auth::isAdmin($viewer);
        return array_map([$this, 'decorate'], $this->offers->forListing($listingId, $all ? null : (int) $viewer['id']));
    }

    public function mine(int $userId): array
    {
        return [
            'received' => array_map([$this, 'decorate'], $this->offers->received($userId)),
            'sent'     => array_map([$this, 'decorate'], $this->offers->sent($userId)),
            'pending_received' => $this->offers->countPendingReceived($userId),
        ];
    }

    /** Arayüz için anlaşılır aşama: beklemede / kabul (devam ediyor) / tamamlandi / red / iptal */
    private function decorate(array $o): array
    {
        $o['stage'] = $o['status'] === 'kabul' && $o['listing_status'] === 'tamamlandi' ? 'tamamlandi' : $o['status'];
        return $o;
    }

    // ------------------------------------------------------------------ yetki
    private function assertOwner(array $o, array $actor): void                                                                      // T6
    {
        if ((int) $o['listing_owner_id'] !== (int) $actor['id']) {
            throw new ValidationException('Bu işlemi sadece ilan sahibi yapabilir', [], 403);
        }
    }

    /** İşlemi yapan taraflardan biri mi? Karşı tarafın id'sini döndürür. */
    private function assertParty(array $o, array $actor): int
    {
        $me = (int) $actor['id'];
        if ($me === (int) $o['listing_owner_id']) return (int) $o['offerer_id'];
        if ($me === (int) $o['offerer_id']) return (int) $o['listing_owner_id'];
        throw new ValidationException('Bu takasın tarafı değilsiniz', [], 403);
    }
}
