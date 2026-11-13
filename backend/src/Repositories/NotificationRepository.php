<?php
/** VERİ KATMANI - Bildirim tablosunun SQL sorguları (oluştur, listele, okundu yap). */
class NotificationRepository extends BaseRepository
{
    /** Kullanıcıya yeni bildirim ekler. */
    public function create(int $userId, string $type, string $content, ?string $link = null): int
    {
        return $this->insert('notifications', [
            // Bildirim metni en fazla 255 karakter
            'user_id' => $userId, 'type' => $type, 'content' => mb_substr($content, 0, 255), 'link' => $link,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /** Kullanıcının son bildirimlerini getirir (istenirse sadece okunmamışları). */
    public function forUser(int $userId, bool $onlyUnread = false, int $limit = 50): array
    {
        // İstenirse sadece okunmamışlar; en fazla 100 kayıt
        return $this->fetchAll(
            'SELECT id, type, content, link, is_read, created_at FROM notifications WHERE user_id = ?'
            . ($onlyUnread ? ' AND is_read = 0' : '') . ' ORDER BY id DESC LIMIT ' . max(1, min(100, $limit)), [$userId]);
    }

    /** Okunmamış bildirim sayısı (menüdeki zil için). */
    public function unreadCount(int $userId): int
    {
        return (int) $this->fetchOne('SELECT COUNT(*) AS n FROM notifications WHERE user_id = ? AND is_read = 0', [$userId])['n'];
    }

    /** Sadece kendi bildirimini okundu yapabilir (user_id koşulu) */
    public function markRead(int $id, int $userId): int
    {
        return $this->execute('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?', [$id, $userId]);
    }

    /** Kullanıcının bütün bildirimlerini okundu yapar. */
    public function markAllRead(int $userId): int
    {
        return $this->execute('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0', [$userId]);
    }

    /** Bildirimi siler. */
    public function delete(int $id): int
    {
        return $this->execute('DELETE FROM notifications WHERE id = ?', [$id]);
    }
}
