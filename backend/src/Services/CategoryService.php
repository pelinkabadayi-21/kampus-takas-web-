<?php
/** İŞ KATMANI - Kurallar ve iş mantığı burada; SQL veya HTML içermez. */
class CategoryService
{
    /** Kategori deposu alınır. */
    public function __construct(private CategoryRepository $repo) {}

    /** Kategori listesini döndürür. */
    public function list(): array
    {
        return $this->repo->all();
    }
}
