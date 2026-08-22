<?php

declare(strict_types=1);

final class ContactRepository
{
    public static function create(string $name, string $email, string $message): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO contact_messages (name, email, message) VALUES (?, ?, ?)'
        );
        $stmt->execute([trim($name), trim($email), trim($message)]);
        return (int) Database::pdo()->lastInsertId();
    }

    public static function listRecent(int $limit = 50): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM contact_messages ORDER BY created_at DESC LIMIT ?'
        );
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function countUnread(): int
    {
        return (int) Database::pdo()->query('SELECT COUNT(*) FROM contact_messages WHERE is_read = 0')->fetchColumn();
    }

    public static function markRead(int $id): void
    {
        $stmt = Database::pdo()->prepare('UPDATE contact_messages SET is_read = 1 WHERE id = ?');
        $stmt->execute([$id]);
    }
}
