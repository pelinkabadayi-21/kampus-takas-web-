<?php
class TradeOfferRepository extends BaseRepository
{
    public function create(array $d): int
    {
        return $this->insert('trade_offers', array_intersect_key($d,
            array_flip(['listing_id', 'offerer_id', 'offered_listing_id', 'offered_amount', 'note'])));
    }

    public function findById(int $id): ?array
    {
        return $this->fetchOne(
            'SELECT o.*, l.user_id AS listing_owner_id, l.title AS listing_title
             FROM trade_offers o JOIN listings l ON l.id = o.listing_id WHERE o.id = ?', [$id]);
    }

    public function forListing(int $listingId, ?string $status = null): array
    {
        $sql = 'SELECT o.*, u.full_name AS offerer_name, ol.title AS offered_listing_title
                FROM trade_offers o
                JOIN users u          ON u.id = o.offerer_id
                LEFT JOIN listings ol ON ol.id = o.offered_listing_id
                WHERE o.listing_id = ?';
        $p = [$listingId];
        if ($status) { $sql .= ' AND o.status = ?'; $p[] = $status; }
        return $this->fetchAll($sql . ' ORDER BY o.created_at DESC', $p);
    }

    public function setStatus(int $id, string $status): int
    {
        return $this->execute('UPDATE trade_offers SET status = ? WHERE id = ?', [$status, $id]);
    }

    /** Kabul edilen teklif dışındaki bekleyen teklifleri reddet. */
    public function rejectOthers(int $listingId, int $exceptOfferId): int
    {
        return $this->execute(
            "UPDATE trade_offers SET status = 'red' WHERE listing_id = ? AND id <> ? AND status = 'beklemede'",
            [$listingId, $exceptOfferId]);
    }

    public function delete(int $id): int
    {
        return $this->execute('DELETE FROM trade_offers WHERE id = ?', [$id]);
    }
}
