<?php
class ListingRepository extends BaseRepository
{
    private const EDITABLE = ['category_id', 'title', 'description', 'item_condition', 'listing_type',
                              'price', 'ai_suggested_price', 'meeting_point', 'status'];

    // ---------- CREATE ----------
    public function create(array $d): int
    {
        return $this->insert('listings', array_intersect_key($d, array_flip(array_merge(['user_id'], self::EDITABLE))));
    }

    public function addImage(int $listingId, string $url, int $order = 0): int
    {
        return $this->insert('listing_images', ['listing_id' => $listingId, 'image_url' => $url, 'sort_order' => $order]);
    }

    public function findImage(int $imageId): ?array
    {
        return $this->fetchOne('SELECT id, listing_id, image_url, sort_order FROM listing_images WHERE id = ?', [$imageId]);
    }

    public function imagesOf(int $listingId): array
    {
        return $this->fetchAll('SELECT id, image_url, sort_order FROM listing_images WHERE listing_id = ? ORDER BY sort_order, id', [$listingId]);
    }

    public function nextImageOrder(int $listingId): int
    {
        return (int) $this->fetchOne('SELECT COALESCE(MAX(sort_order) + 1, 0) AS n FROM listing_images WHERE listing_id = ?', [$listingId])['n'];
    }

    public function deleteImage(int $imageId): int
    {
        return $this->execute('DELETE FROM listing_images WHERE id = ?', [$imageId]);
    }

    /** Seçilen fotoğrafı 0. sıraya alır, diğerlerini birer kaydırır. */
    public function setCoverImage(int $listingId, int $imageId): void
    {
        $order = 1;
        foreach ($this->imagesOf($listingId) as $img) {
            $this->execute('UPDATE listing_images SET sort_order = ? WHERE id = ?',
                           [(int) $img['id'] === $imageId ? 0 : $order++, $img['id']]);
        }
    }

    public function countActiveByUser(int $userId): int
    {
        return (int) $this->fetchOne("SELECT COUNT(*) AS n FROM listings WHERE user_id = ? AND status = 'aktif'", [$userId])['n'];
    }

    public function activeTitleExists(int $userId, string $title, ?int $exceptId = null): bool
    {
        return (bool) $this->fetchOne(
            "SELECT 1 AS x FROM listings WHERE user_id = ? AND status = 'aktif' AND LOWER(title) = LOWER(?) AND id <> ?",
            [$userId, $title, $exceptId ?? 0]);
    }

    // ---------- READ ----------
    public function findById(int $id): ?array
    {
        $row = $this->fetchOne(
            'SELECT l.*, c.name AS category_name, u.full_name AS owner_name, u.is_verified AS owner_verified
             FROM listings l
             JOIN categories c ON c.id = l.category_id
             JOIN users u      ON u.id = l.user_id
             WHERE l.id = ?', [$id]);
        if ($row) {
            $row['images'] = $this->imagesOf($id);
        }
        return $row;
    }

    /**
     * Filtreli arama. Tüm filtreler isteğe bağlıdır.
     * $f: q, category_id, listing_type, item_condition, min_price, max_price, user_id,
     *     status ('hepsi' = tüm durumlar), sort, limit, offset
     */
    public function search(array $f = []): array
    {
        [$where, $params] = $this->buildWhere($f);

        $sort = match ($f['sort'] ?? 'yeni') {
            'ucuz'  => 'l.price IS NULL, l.price ASC',
            'pahali'=> 'l.price DESC',
            'eski'  => 'l.created_at ASC',
            default => 'l.created_at DESC, l.id DESC',
        };
        $limit  = max(1, min(50, (int) ($f['limit'] ?? 12)));
        $offset = max(0, (int) ($f['offset'] ?? 0));

        return $this->fetchAll(
            "SELECT l.id, l.title, l.price, l.listing_type, l.item_condition, l.status, l.meeting_point,
                    l.created_at, c.name AS category_name, u.full_name AS owner_name,
                    (SELECT image_url FROM listing_images i WHERE i.listing_id = l.id ORDER BY sort_order, id LIMIT 1) AS cover_image,
                    (SELECT COUNT(*) FROM listing_images i WHERE i.listing_id = l.id) AS image_count
             FROM listings l
             JOIN categories c ON c.id = l.category_id
             JOIN users u      ON u.id = l.user_id
             $where
             ORDER BY $sort
             LIMIT $limit OFFSET $offset", $params);
    }

    public function count(array $f = []): int
    {
        [$where, $params] = $this->buildWhere($f);
        return (int) $this->fetchOne("SELECT COUNT(*) AS n FROM listings l $where", $params)['n'];
    }

    private function buildWhere(array $f): array
    {
        $w = []; $p = [];
        $status = $f['status'] ?? 'aktif';
        if ($status !== 'hepsi') { $w[] = 'l.status = ?'; $p[] = $status; }
        if (!empty($f['q']))            { $w[] = '(l.title LIKE ? OR l.description LIKE ?)'; $p[] = "%{$f['q']}%"; $p[] = "%{$f['q']}%"; }
        if (!empty($f['category_id']))  { $w[] = 'l.category_id = ?';  $p[] = (int) $f['category_id']; }
        if (!empty($f['listing_type'])) { $w[] = 'l.listing_type = ?'; $p[] = $f['listing_type']; }
        if (!empty($f['item_condition'])) { $w[] = 'l.item_condition = ?'; $p[] = $f['item_condition']; }
        if (!empty($f['user_id']))      { $w[] = 'l.user_id = ?';      $p[] = (int) $f['user_id']; }
        if (isset($f['min_price']) && $f['min_price'] !== '') { $w[] = 'l.price >= ?'; $p[] = (float) $f['min_price']; }
        if (isset($f['max_price']) && $f['max_price'] !== '') { $w[] = 'l.price <= ?'; $p[] = (float) $f['max_price']; }
        return [$w ? 'WHERE ' . implode(' AND ', $w) : '', $p];
    }

    // ---------- UPDATE ----------
    public function update(int $id, array $d): int
    {
        return $this->updateById('listings', $id, $d, self::EDITABLE);
    }

    public function setStatus(int $id, string $status): int
    {
        return $this->execute('UPDATE listings SET status = ? WHERE id = ?', [$status, $id]);
    }

    // ---------- DELETE ----------
    public function delete(int $id): int
    {
        // Fotoğraflar, mesajlar, favoriler ON DELETE CASCADE ile otomatik silinir
        return $this->execute('DELETE FROM listings WHERE id = ?', [$id]);
    }
}
