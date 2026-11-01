<?php
class MessageRepository extends BaseRepository
{
    public function create(int $listingId, int $senderId, int $receiverId, string $body): int
    {
        return $this->insert('messages', [
            'listing_id' => $listingId, 'sender_id' => $senderId,
            'receiver_id' => $receiverId, 'body' => $body,
        ]);
    }

    /** İki kullanıcı arasındaki, bir ilana ait konuşma (her iki yön). */
    public function conversation(int $listingId, int $userA, int $userB): array
    {
        return $this->fetchAll(
            'SELECT id, sender_id, receiver_id, body, is_read, created_at FROM messages
             WHERE listing_id = ?
               AND ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))
             ORDER BY created_at, id', [$listingId, $userA, $userB, $userB, $userA]);
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

    public function delete(int $id): int
    {
        return $this->execute('DELETE FROM messages WHERE id = ?', [$id]);
    }
}
