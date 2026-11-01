<?php
class ReviewRepository extends BaseRepository
{
    public function create(array $d): int
    {
        return $this->insert('reviews', array_intersect_key($d,
            array_flip(['trade_offer_id', 'reviewer_id', 'reviewed_id', 'rating', 'comment'])));
    }

    public function forUser(int $userId): array
    {
        return $this->fetchAll(
            'SELECT r.rating, r.comment, r.created_at, u.full_name AS reviewer_name
             FROM reviews r JOIN users u ON u.id = r.reviewer_id
             WHERE r.reviewed_id = ? ORDER BY r.created_at DESC', [$userId]);
    }

    /** Kullanıcının ortalama puanı ve yorum sayısı */
    public function summary(int $userId): array
    {
        $r = $this->fetchOne('SELECT ROUND(AVG(rating), 1) AS average, COUNT(*) AS total FROM reviews WHERE reviewed_id = ?', [$userId]);
        return ['average' => $r['average'] !== null ? (float) $r['average'] : null, 'total' => (int) $r['total']];
    }

    public function delete(int $id): int
    {
        return $this->execute('DELETE FROM reviews WHERE id = ?', [$id]);
    }
}
