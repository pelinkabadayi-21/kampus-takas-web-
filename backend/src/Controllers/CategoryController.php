<?php
class CategoryController
{
    public function __construct(private CategoryService $service) {}

    public function index(): void
    {
        Response::json(['success' => true, 'data' => $this->service->list()]);
    }
}
