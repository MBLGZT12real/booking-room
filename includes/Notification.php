<?php
// ============================================================
// Notification Helper Class
// ============================================================

class Notification
{
    /**
     * Create notification for all admin/superadmin users
     */
    public static function createForAdmins(
        string $type,
        string $title,
        string $message,
        ?int $bookingId = null
    ): void {
        $db = db();

        // Get all active admin and superadmin users
        $admins = $db->fetchAll(
            "SELECT u.id FROM users u
             JOIN roles r ON u.role_id = r.id
             WHERE r.slug IN ('superadmin', 'admin', 'operator', 'staff') AND u.is_active = 1"
        );

        foreach ($admins as $admin) {
            $db->insert('notifications', [
                'user_id'    => $admin['id'],
                'booking_id' => $bookingId,
                'type'       => $type,
                'title'      => $title,
                'message'    => $message,
                'is_read'    => 0,
            ]);
        }
    }

    /**
     * Create notification for specific user
     */
    public static function createForUser(
        int $userId,
        string $type,
        string $title,
        string $message,
        ?int $bookingId = null
    ): void {
        db()->insert('notifications', [
            'user_id'    => $userId,
            'booking_id' => $bookingId,
            'type'       => $type,
            'title'      => $title,
            'message'    => $message,
            'is_read'    => 0,
        ]);
    }

    /**
     * Get unread count for current user
     */
    public static function getUnreadCount(?int $userId = null): int
    {
        if (!$userId) $userId = Auth::id();
        if (!$userId) return 0;

        return (int) db()->fetchColumn(
            "SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0",
            [$userId]
        );
    }

    /**
     * Get recent unread notifications for current user
     */
    public static function getUnread(int $limit = 10, ?int $userId = null): array
    {
        if (!$userId) $userId = Auth::id();
        if (!$userId) return [];

        return db()->fetchAll(
            "SELECT n.*, b.booking_code FROM notifications n
             LEFT JOIN bookings b ON n.booking_id = b.id
             WHERE n.user_id = ? AND n.is_read = 0
             ORDER BY n.created_at DESC
             LIMIT ?",
            [$userId, $limit]
        );
    }

    /**
     * Get all notifications for current user (with pagination)
     */
    public static function getAll(int $offset = 0, int $limit = 20, ?int $userId = null): array
    {
        if (!$userId) $userId = Auth::id();
        if (!$userId) return [];

        return db()->fetchAll(
            "SELECT n.*, b.booking_code, r.name as room_name
             FROM notifications n
             LEFT JOIN bookings b ON n.booking_id = b.id
             LEFT JOIN rooms r ON b.room_id = r.id
             WHERE n.user_id = ?
             ORDER BY n.created_at DESC
             LIMIT ? OFFSET ?",
            [$userId, $limit, $offset]
        );
    }

    /**
     * Get count all notifications for user
     */
    public static function countAll(?int $userId = null): int
    {
        if (!$userId) $userId = Auth::id();
        if (!$userId) return 0;
        return (int) db()->fetchColumn(
            "SELECT COUNT(*) FROM notifications WHERE user_id = ?",
            [$userId]
        );
    }

    /**
     * Mark specific notification as read
     */
    public static function markRead(int $notifId, ?int $userId = null): void
    {
        if (!$userId) $userId = Auth::id();

        db()->query(
            "UPDATE notifications SET is_read = 1, read_at = NOW()
             WHERE id = ? AND user_id = ?",
            [$notifId, $userId]
        );
    }

    /**
     * Mark all notifications as read for current user
     */
    public static function markAllRead(?int $userId = null): void
    {
        if (!$userId) $userId = Auth::id();
        if (!$userId) return;

        db()->query(
            "UPDATE notifications SET is_read = 1, read_at = NOW()
             WHERE user_id = ? AND is_read = 0",
            [$userId]
        );
    }

    /**
     * Get new notifications since a given ID (for SSE polling)
     */
    public static function getSinceId(int $lastId, ?int $userId = null): array
    {
        if (!$userId) $userId = Auth::id();
        if (!$userId) return [];

        return db()->fetchAll(
            "SELECT n.*, b.booking_code FROM notifications n
             LEFT JOIN bookings b ON n.booking_id = b.id
             WHERE n.user_id = ? AND n.id > ? AND n.is_read = 0
             ORDER BY n.created_at DESC",
            [$userId, $lastId]
        );
    }

    /**
     * Delete old read notifications (cleanup)
     */
    public static function cleanup(int $daysOld = 30): void
    {
        db()->query(
            "DELETE FROM notifications WHERE is_read = 1 AND created_at < DATE_SUB(NOW(), INTERVAL ? DAY)",
            [$daysOld]
        );
    }
}
