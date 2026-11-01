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
            $row['images'] = $this->fetchAll(
                'SELECT id, image_url FROM listing_images WHERE listing_id = ? ORDER BY sort_order', [$id]);
        }
        return $row;
    }

    /**
     * Filtreli arama. Tüm filtreler isteğe bağlıdır.
     * $f: q, category_id, listing_type, min_price, max_price, user_id, status, sort, limit, offset
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
                    (SELECT image_url FROM listing_images i WHERE i.listing_id = l.id ORDER BY sort_order LIMIT 1) AS cover_image
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
        $w[] = 'l.status = ?';           $p[] = $f['status'] ?? 'aktif';
        if (!empty($f['q']))            { $w[] = '(l.title LIKE ? OR l.description LIKE ?)'; $p[] = "%{$f['q']}%"; $p[] = "%{$f['q']}%"; }
        if (!empty($f['category_id']))  { $w[] = 'l.category_id = ?';  $p[] = (int) $f['category_id']; }
        if (!empty($f['listing_type'])) { $w[] = 'l.listing_type = ?'; $p[] = $f['listing_type']; }
        if (!empty($f['user_id']))      { $w[] = 'l.user_id = ?';      $p[] = (int) $f['user_id']; }
        if (isset($f['min_price']) && $f['min_price'] !== '') { $w[] = 'l.price >= ?'; $p[] = (float) $f['min_price']; }
        if (isset($f['max_price']) && $f['max_price'] !== '') { $w[] = 'l.price <= ?'; $p[] = (float) $f['max_price']; }
        return ['WHERE ' . implode(' AND ', $w), $p];
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
