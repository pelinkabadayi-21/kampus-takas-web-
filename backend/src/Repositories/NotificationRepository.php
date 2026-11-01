<?php
class NotificationRepository extends BaseRepository
{
    public function create(int $userId, string $type, string $content, ?string $link = null): int
    {
        return $this->insert('notifications', ['user_id' => $userId, 'type' => $type, 'content' => $content, 'link' => $link]);
    }

    public function forUser(int $userId, bool $onlyUnread = false): array
    {
        return $this->fetchAll(
            'SELECT id, type, content, link, is_read, created_at FROM notifications WHERE user_id = ?'
            . ($onlyUnread ? ' AND is_read = 0' : '') . ' ORDER BY created_at DESC, id DESC LIMIT 50', [$userId]);
    }

    public function markAllRead(int $userId): int
    {
        return $this->execute('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0', [$userId]);
    }

    public function delete(int $id): int
    {
        return $this->execute('DELETE FROM notifications WHERE id = ?', [$id]);
    }
}
