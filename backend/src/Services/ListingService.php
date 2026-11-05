<?php
/**
 * İŞ KATMANI - İlan kuralları. SQL içermez; veriyi ListingRepository'den alır.
 *
 * İŞ KURALLARI (Hafta 6):
 *  K1  Başlık 3-120, açıklama en fazla 2000 karakter, buluşma noktası en fazla 120 karakter
 *  K2  Satışta fiyat zorunlu; bağışta fiyat olmaz; fiyat 0 - 100.000 ₺ arası
 *  K3  Doğrulanmamış öğrenci en fazla 3, doğrulanmış öğrenci en fazla 20 aktif ilan açabilir
 *  K4  İlanı sadece sahibi veya yönetici değiştirebilir/silebilir
 *  K5  Durum geçişleri: sahip "aktif / kaldirildi / tamamlandi" seçebilir;
 *      "rezerve" sadece takas teklifi kabul edilince sistem tarafından verilir;
 *      tamamlanan ilan tekrar aktif edilemez (yönetici hariç)
 *  K6  Aynı kullanıcının aynı başlıkta iki aktif ilanı olamaz (mükerrer ilan)
 *  K8  (Öğrenci kuralı) Başlık tamamen büyük harfle yazılamaz
 *  K7  "ai_suggested_price" kullanıcı tarafından yazılamaz; yapay zekâ modülü doldurur
 */
class ListingService
{
    public const CONDITIONS = ['yeni', 'cok_iyi', 'iyi', 'orta', 'yipranmis'];
    public const TYPES      = ['satis', 'takas', 'bagis'];
    public const STATUSES   = ['aktif', 'rezerve', 'tamamlandi', 'kaldirildi'];
    private const OWNER_SETTABLE_STATUSES = ['aktif', 'kaldirildi', 'tamamlandi'];
    public const MAX_PRICE  = 100000;
    public const LIMIT_UNVERIFIED = 3;
    public const LIMIT_VERIFIED   = 20;

    /** Konum bazlı buluşma: önerilen güvenli kampüs noktaları (kullanıcı başka yer de yazabilir) */
    public const MEETING_POINTS = [
        'Merkez Kütüphane önü', 'Yemekhane girişi', 'MYO kantini', 'Rektörlük önü',
        'Kız yurdu kapısı', 'Erkek yurdu kapısı', 'Kampüs otobüs durağı', 'Spor salonu girişi',
    ];

    /** Formlar için seçenekler: arayüz bu listeleri buradan alır, böylece kurallar tek yerde durur. */
    public static function options(): array
    {
        return [
            'conditions'     => ['yeni' => 'Yeni', 'cok_iyi' => 'Çok iyi', 'iyi' => 'İyi', 'orta' => 'Orta', 'yipranmis' => 'Yıpranmış'],
            'types'          => ['satis' => 'Satılık', 'takas' => 'Takas', 'bagis' => 'Bağış'],
            'meeting_points' => self::MEETING_POINTS,
            'max_price'      => self::MAX_PRICE,
            'max_images'     => ImageService::MAX_IMAGES,
            'max_image_mb'   => ImageService::MAX_BYTES / 1024 / 1024,
            'limits'         => ['unverified' => self::LIMIT_UNVERIFIED, 'verified' => self::LIMIT_VERIFIED],
        ];
    }

    public function __construct(
        private ListingRepository $listings,
        private CategoryRepository $categories,
        private ?ImageService $images = null
    ) {}

    public function search(array $filters): array
    {
        return [
            'items' => $this->listings->search($filters),
            'total' => $this->listings->count($filters),
        ];
    }

    /** Kullanıcının kendi ilanları: tüm durumlar (aktif, rezerve, kaldırıldı...) */
    public function mine(int $userId): array
    {
        return $this->search(['user_id' => $userId, 'status' => 'hepsi', 'limit' => 50]);
    }

    public function get(int $id): array
    {
        return $this->listings->findById($id) ?? throw new ValidationException('İlan bulunamadı', [], 404);
    }

    /** Herkese açık görüntüleme: kaldırılmış ilanı sadece sahibi ve yönetici görebilir. */
    public function getVisible(int $id, ?array $viewer): array
    {
        $l = $this->get($id);
        $isOwner = $viewer && (int) $viewer['id'] === (int) $l['user_id'];
        if ($l['status'] === 'kaldirildi' && !$isOwner && !Auth::isAdmin($viewer)) {
            throw new ValidationException('İlan bulunamadı', [], 404);
        }
        $l['can_edit'] = $isOwner || Auth::isAdmin($viewer);
        return $l;
    }

    /**
     * $actor: ilanı açan kullanıcı (oturumdan). Verilmezse (seed/test) limit kontrolü yapılmaz.
     */
    public function create(array $d, ?array $actor = null): array
    {
        unset($d['ai_suggested_price'], $d['status']);                 // K7, yeni ilan her zaman aktif
        if ($actor) {
            $d['user_id'] = $actor['id'];
            $this->assertUnderLimit($actor);                            // K3
        }
        $d = $this->validate($d, true);
        $this->assertNotDuplicate((int) $d['user_id'], $d['title']);    // K6
        $id = $this->listings->create($d);
        return $this->get($id);
    }

    /** $actor verilirse sadece ilan sahibi veya yönetici güncelleyebilir. */
    public function update(int $id, array $d, ?array $actor = null): array
    {
        $current = $this->get($id);
        $this->assertCanModify($current, $actor);                       // K4
        unset($d['user_id'], $d['id']);                                 // ilanın sahibi değiştirilemez
        if ($actor) unset($d['ai_suggested_price']);                    // K7

        if (isset($d['status']) && $d['status'] !== $current['status']) {
            $this->assertStatusChange($current, $d['status'], $actor);  // K5
        }
        if (isset($d['title']) && mb_strtolower(trim($d['title'])) !== mb_strtolower($current['title'])) {
            $this->assertNotDuplicate((int) $current['user_id'], $d['title'], $id);
        }

        $merged = array_merge($current, $d);                            // kısmi güncellemede kuralları tüm ilana uygula
        $clean  = $this->validate($merged, false);

        $changed = array_intersect_key($clean, $d);
        // Tür değişince fiyat da değişmiş olabilir (ör. bağışa çevrilince NULL olur)
        if (isset($d['listing_type']) || array_key_exists('price', $d)) $changed['price'] = $clean['price'];
        $this->listings->update($id, $changed);
        return $this->get($id);
    }

    public function delete(int $id, ?array $actor = null): void
    {
        $this->assertCanModify($this->get($id), $actor);
        $this->images?->deleteFilesOfListing($id);      // diskteki fotoğraflar (DB satırları CASCADE ile silinir)
        $this->listings->delete($id);
    }

    // ------------------------------------------------------------------ kurallar
    /** K4 Sahiplik kontrolü: başkasının ilanını sadece yönetici değiştirebilir. */
    public function assertCanModify(array $listing, ?array $actor): void
    {
        if ($actor === null) return;                    // iç kullanım (seed, test)
        if ((int) $listing['user_id'] === (int) $actor['id'] || Auth::isAdmin($actor)) return;
        throw new ValidationException('Bu ilanı sadece sahibi veya yönetici değiştirebilir', [], 403);
    }

    /** K3 */
    private function assertUnderLimit(array $actor): void
    {
        if (Auth::isAdmin($actor)) return;
        $limit = !empty($actor['is_verified']) ? self::LIMIT_VERIFIED : self::LIMIT_UNVERIFIED;
        if ($this->listings->countActiveByUser((int) $actor['id']) >= $limit) {
            $msg = "En fazla $limit aktif ilanınız olabilir.";
            if (empty($actor['is_verified'])) $msg .= ' Öğrenci doğrulaması yapılan hesaplar ' . self::LIMIT_VERIFIED . ' ilana kadar açabilir.';
            throw new ValidationException($msg, [], 422);
        }
    }

    /** K5 */
    private function assertStatusChange(array $l, string $to, ?array $actor): void
    {
        if (!in_array($to, self::STATUSES, true)) throw new ValidationException('Geçersiz ilan durumu', ['status' => 'Geçersiz ilan durumu']);
        if ($actor === null || Auth::isAdmin($actor)) return;

        if (!in_array($to, self::OWNER_SETTABLE_STATUSES, true)) {
            throw new ValidationException('"Rezerve" durumu sadece bir takas teklifi kabul edildiğinde verilir', ['status' => 'Bu durumu elle seçemezsiniz']);
        }
        if ($l['status'] === 'tamamlandi') {
            throw new ValidationException('Tamamlanan ilan tekrar yayına alınamaz; yeni ilan açın', ['status' => 'Tamamlanan ilan değiştirilemez']);
        }
        if ($to === 'aktif') $this->assertUnderLimit($actor);   // tekrar yayına alma da limite sayılır
    }

    /** K6 */
    private function assertNotDuplicate(int $userId, string $title, ?int $exceptId = null): void
    {
        if ($this->listings->activeTitleExists($userId, trim($title), $exceptId)) {
            throw new ValidationException('Bu başlıkta aktif bir ilanınız zaten var', ['title' => 'Aynı başlıkta aktif ilanınız var']);
        }
    }

    /** K1, K2 */
    private function validate(array $d, bool $isNew): array
    {
        $e = [];
        $d['title'] = trim((string) ($d['title'] ?? ''));
        if (mb_strlen($d['title']) < 3 || mb_strlen($d['title']) > 120) $e['title'] = 'Başlık 3-120 karakter olmalı';
        // ÖĞRENCİ KURALI (Hafta 6) K8: Başlık tamamen BÜYÜK HARFLE yazılamaz.
        // Amaç: Bağıran, dikkat çekmeye çalışan başlıkları engelleyip katalogda okunabilirliği korumak.
        // Not: 6'dan az harf içeren kısa başlıklar (ör. "USB 64 GB") kuraldan etkilenmez; rakamlar harf sayılmaz.
        elseif (preg_match_all('/\p{L}/u', $d['title']) >= 6 && $d['title'] === mb_strtoupper($d['title'], 'UTF-8')) {
            $e['title'] = 'Başlık tamamen büyük harfle yazılamaz';
        }

        $d['description'] = trim((string) ($d['description'] ?? '')) ?: null;
        if ($d['description'] && mb_strlen($d['description']) > 2000) $e['description'] = 'Açıklama en fazla 2000 karakter olabilir';

        $d['meeting_point'] = trim((string) ($d['meeting_point'] ?? '')) ?: null;
        if ($d['meeting_point'] && mb_strlen($d['meeting_point']) > 120) $e['meeting_point'] = 'Buluşma noktası en fazla 120 karakter';

        if ($isNew && empty($d['user_id'])) $e['user_id'] = 'İlan sahibi gerekli';
        if (empty($d['category_id']) || !$this->categories->findById((int) $d['category_id'])) $e['category_id'] = 'Geçerli bir kategori seçin';

        $d['listing_type']   = $d['listing_type']   ?? 'satis';
        $d['item_condition'] = $d['item_condition'] ?? 'iyi';
        if (!in_array($d['listing_type'], self::TYPES, true))        $e['listing_type']   = 'Geçersiz ilan türü';
        if (!in_array($d['item_condition'], self::CONDITIONS, true)) $e['item_condition'] = 'Geçersiz ürün durumu';

        // Fiyat kuralları (veritabanındaki CHECK kısıtının uygulama tarafı)
        $price = ($d['price'] ?? '') === '' ? null : $d['price'];
        if ($d['listing_type'] === 'bagis') {
            $price = null;                                  // bağışta fiyat olmaz
        } elseif ($d['listing_type'] === 'satis' && $price === null) {
            $e['price'] = 'Satış ilanında fiyat zorunlu';
        }
        if ($price !== null) {
            if (!is_numeric($price) || $price < 0)         $e['price'] = 'Fiyat 0 veya daha büyük bir sayı olmalı';
            elseif ($price > self::MAX_PRICE)              $e['price'] = 'Fiyat en fazla ' . number_format(self::MAX_PRICE, 0, ',', '.') . ' ₺ olabilir';
            else $price = round((float) $price, 2);
        }
        $d['price'] = $price;

        if ($e) throw new ValidationException('Formda hatalar var', $e);
        return $d;
    }
}
