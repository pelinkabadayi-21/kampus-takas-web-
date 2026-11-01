<?php
class CategoryRepository extends BaseRepository
{
    public function all(): array
    {
        return $this->fetchAll(
            'SELECT c.id, c.name, c.slug,
                    (SELECT COUNT(*) FROM listings l WHERE l.category_id = c.id AND l.status = \'aktif\') AS listing_count
             FROM categories c ORDER BY c.name'
        );
    }

    public function findById(int $id): ?array
    {
        return $this->fetchOne('SELECT id, name, slug FROM categories WHERE id = ?', [$id]);
    }

    public function create(string $name, string $slug): int
    {
        return $this->insert('categories', ['name' => $name, 'slug' => $slug]);
    }
}
