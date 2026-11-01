<?php
/** İŞ KATMANI - Kurallar ve iş mantığı burada; SQL veya HTML içermez. */
class CategoryService
{
    public function __construct(private CategoryRepository $repo) {}

    public function list(): array
    {
        return $this->repo->all();
    }
}
