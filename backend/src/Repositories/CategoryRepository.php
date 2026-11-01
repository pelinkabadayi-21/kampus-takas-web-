<?php
/** VERİ KATMANI - Sadece SQL sorguları burada yazılır. */
class CategoryRepository
{
    public function __construct(private PDO $db) {}

    public function all(): array
    {
        return $this->db->query('SELECT id, name, slug FROM categories ORDER BY name')->fetchAll();
    }
}
