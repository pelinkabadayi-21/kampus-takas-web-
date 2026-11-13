<?php
/** N-N ilişki: users <-> listings, ara tablo "favorites" */
class FavoriteRepository extends BaseRepository
{
    /** İlanı favorilere ekler (zaten ekliyse tekrar eklemez). */
    public function add(int $userId, int $listingId): void
    {
        // Birleşik PK aynı çiftin iki kez eklenmesini engeller; varsa sessizce geç
        if (!$this->exists($userId, $listingId)) {
            $this->insert('favorites', ['user_id' => $userId, 'listing_id' => $listingId]);
        }
    }

    /** İlanı favorilerden çıkarır. */
    public function remove(int $userId, int $listingId): int
    {
        return $this->execute('DELETE FROM favorites WHERE user_id = ? AND listing_id = ?', [$userId, $listingId]);
    }

    /** Bu kullanıcı bu ilanı favorilemiş mi? */
    public function exists(int $userId, int $listingId): bool
    {
        return (bool) $this->fetchOne('SELECT 1 AS x FROM favorites WHERE user_id = ? AND listing_id = ?', [$userId, $listingId]);
    }

    /** Kullanıcının favori ilanlarını getirir. */
    public function listingsOf(int $userId): array
    {
        return $this->fetchAll(
            'SELECT l.id, l.title, l.price, l.listing_type, f.created_at AS favorited_at
             FROM favorites f JOIN listings l ON l.id = f.listing_id
             WHERE f.user_id = ? ORDER BY f.created_at DESC', [$userId]);
    }
}
