<?php
/** İŞ KATMANI - İlan kuralları. SQL içermez; veriyi ListingRepository'den alır. */
class ListingService
{
    private const CONDITIONS = ['yeni', 'cok_iyi', 'iyi', 'orta', 'yipranmis'];
    private const TYPES      = ['satis', 'takas', 'bagis'];
    private const STATUSES   = ['aktif', 'rezerve', 'tamamlandi', 'kaldirildi'];

    public function __construct(private ListingRepository $listings, private CategoryRepository $categories) {}

    public function search(array $filters): array
    {
        return [
            'items' => $this->listings->search($filters),
            'total' => $this->listings->count($filters),
        ];
    }

    public function get(int $id): array
    {
        return $this->listings->findById($id) ?? throw new ValidationException('İlan bulunamadı', [], 404);
    }

    public function create(array $d): array
    {
        $d = $this->validate($d, true);
        $id = $this->listings->create($d);
        return $this->get($id);
    }

    /** $actor verilirse sadece ilan sahibi veya yönetici güncelleyebilir. */
    public function update(int $id, array $d, ?array $actor = null): array
    {
        $current = $this->get($id);
        $this->assertCanModify($current, $actor);
        unset($d['user_id']);                           // ilanın sahibi değiştirilemez
        $merged  = array_merge($current, $d);           // kısmi güncellemede kuralları tüm ilana uygula
        $clean   = $this->validate($merged, false);
        $this->listings->update($id, array_intersect_key($clean, $d));
        return $this->get($id);
    }

    public function delete(int $id, ?array $actor = null): void
    {
        $this->assertCanModify($this->get($id), $actor);
        $this->listings->delete($id);
    }

    /** Sahiplik kontrolü: başkasının ilanını sadece yönetici değiştirebilir. */
    private function assertCanModify(array $listing, ?array $actor): void
    {
        if ($actor === null) return;                    // iç kullanım (seed, test)
        if ((int) $listing['user_id'] === (int) $actor['id'] || Auth::isAdmin($actor)) return;
        throw new ValidationException('Bu ilanı sadece sahibi veya yönetici değiştirebilir', [], 403);
    }

    private function validate(array $d, bool $isNew): array
    {
        $e = [];
        $d['title'] = trim((string) ($d['title'] ?? ''));
        if (mb_strlen($d['title']) < 3 || mb_strlen($d['title']) > 120) $e['title'] = 'Başlık 3-120 karakter olmalı';

        if ($isNew && empty($d['user_id'])) $e['user_id'] = 'İlan sahibi gerekli';
        if (empty($d['category_id']) || !$this->categories->findById((int) $d['category_id'])) $e['category_id'] = 'Geçerli bir kategori seçin';

        $d['listing_type']   = $d['listing_type']   ?? 'satis';
        $d['item_condition'] = $d['item_condition'] ?? 'iyi';
        if (!in_array($d['listing_type'], self::TYPES, true))        $e['listing_type']   = 'Geçersiz ilan türü';
        if (!in_array($d['item_condition'], self::CONDITIONS, true)) $e['item_condition'] = 'Geçersiz durum';
        if (isset($d['status']) && !in_array($d['status'], self::STATUSES, true)) $e['status'] = 'Geçersiz ilan durumu';

        // Fiyat kuralları (veritabanındaki CHECK kısıtının uygulama tarafı)
        $price = ($d['price'] ?? '') === '' ? null : $d['price'];
        if ($d['listing_type'] === 'bagis') {
            $price = null;                                  // bağışta fiyat olmaz
        } elseif ($d['listing_type'] === 'satis' && $price === null) {
            $e['price'] = 'Satış ilanında fiyat zorunlu';
        }
        if ($price !== null && (!is_numeric($price) || $price < 0)) $e['price'] = 'Fiyat 0 veya daha büyük bir sayı olmalı';
        $d['price'] = $price;

        if ($e) throw new ValidationException('Formda hatalar var', $e);
        return $d;
    }
}
