<?php
/** SUNUM KATMANI - Kategori listesini JSON olarak döndüren uç (GET /api/categories). */
class CategoryController
{
    /** Kategori iş kurallarını yapan servis alınır. */
    public function __construct(private CategoryService $service) {}

    /** GET /api/categories - Tüm kategorileri (ilan sayılarıyla) JSON olarak döndürür. */
    public function index(): void
    {
        Response::json(['success' => true, 'data' => $this->service->list()]);
    }
}
