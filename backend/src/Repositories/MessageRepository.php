<?php
class MessageRepository extends BaseRepository
{
    public function create(int $listingId, int $senderId, int $receiverId, string $body, ?string $createdAt = null): int
    {
        $row = ['listing_id' => $listingId, 'sender_id' => $senderId, 'receiver_id' => $receiverId, 'body' => $body];
        if ($createdAt) $row['created_at'] = $createdAt;     // zaman PHP'den: sunucu saat dilimi farkı olmasın
        return $this->insert('messages', $row);
    }

    public function findById(int $id): ?array
    {
        return $this->fetchOne('SELECT id, listing_id, sender_id, receiver_id, body, is_read, created_at FROM messages WHERE id = ?', [$id]);
    }

    /** İki kullanıcı arasındaki, bir ilana ait konuşma (her iki yön). */
    public function conversation(int $listingId, int $userA, int $userB, int $afterId = 0): array
    {
        return $this->fetchAll(
            'SELECT id, sender_id, receiver_id, body, is_read, created_at FROM messages
             WHERE listing_id = ? AND id > ?
               AND ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))
             ORDER BY id', [$listingId, $afterId, $userA, $userB, $userB, $userA]);
    }

    public function hasConversation(int $listingId, int $userA, int $userB): bool
    {
        return (bool) $this->fetchOne(
            'SELECT 1 AS x FROM messages WHERE listing_id = ?
               AND ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)) LIMIT 1',
            [$listingId, $userA, $userB, $userB, $userA]);
    }

    /**
     * Gelen kutusu: her (ilan, karşı kişi) çifti için tek satır; son mesaj ve okunmamış sayısı.
     */
    public function inbox(int $userId): array
    {
        return $this->fetchAll(
            'SELECT c.listing_id, c.other_id, c.unread, m.body AS last_body, m.sender_id AS last_sender_id,
                    m.created_at AS last_at, l.title AS listing_title, l.status AS listing_status, l.user_id AS listing_owner_id,
                    u.full_name AS other_name,
                    (SELECT image_url FROM listing_images i WHERE i.listing_id = l.id ORDER BY sort_order, id LIMIT 1) AS cover_image
             FROM (
                SELECT listing_id,
                       CASE WHEN sender_id = ? THEN receiver_id ELSE sender_id END AS other_id,
                       MAX(id) AS last_id,
                       SUM(CASE WHEN receiver_id = ? AND is_read = 0 THEN 1 ELSE 0 END) AS unread
                FROM messages
                WHERE sender_id = ? OR receiver_id = ?
                GROUP BY listing_id, CASE WHEN sender_id = ? THEN receiver_id ELSE sender_id END
             ) c
             JOIN messages m ON m.id = c.last_id
             JOIN listings l ON l.id = c.listing_id
             JOIN users u    ON u.id = c.other_id
             ORDER BY c.last_id DESC', [$userId, $userId, $userId, $userId, $userId]);
    }

    public function markRead(int $listingId, int $receiverId, int $senderId): int
    {
        return $this->execute(
            'UPDATE messages SET is_read = 1 WHERE listing_id = ? AND receiver_id = ? AND sender_id = ? AND is_read = 0',
            [$listingId, $receiverId, $senderId]);
    }

    public function unreadCount(int $userId): int
    {
        return (int) $this->fetchOne('SELECT COUNT(*) AS n FROM messages WHERE receiver_id = ? AND is_read = 0', [$userId])['n'];
    }

    /** Son X saniyede gönderilen mesaj sayısı (flood koruması) */
    public function sentSince(int $userId, string $since): int
    {
        return (int) $this->fetchOne('SELECT COUNT(*) AS n FROM messages WHERE sender_id = ? AND created_at >= ?', [$userId, $since])['n'];
    }

    public function delete(int $id): int
    {
        return $this->execute('DELETE FROM messages WHERE id = ?', [$id]);
    }
}
