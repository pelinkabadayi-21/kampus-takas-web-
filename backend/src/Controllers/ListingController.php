<?php
/** SUNUM KATMANI - İlan CRUD uç noktaları */
class ListingController
{
    public function __construct(private ListingService $service) {}

    // GET /api/listings?q=&category_id=&listing_type=&min_price=&max_price=&sort=&limit=&offset=
    public function index(): void
    {
        $allowed = ['q', 'category_id', 'listing_type', 'min_price', 'max_price', 'user_id', 'sort', 'limit', 'offset'];
        Response::json(['success' => true] + $this->service->search(array_intersect_key($_GET, array_flip($allowed))));
    }

    // GET /api/listings/{id}
    public function show(int $id): void
    {
        Response::json(['success' => true, 'data' => $this->service->get($id)]);
    }

    // POST /api/listings   (JSON gövde)
    public function store(): void
    {
        // NOT: Giriş sistemi gelene kadar user_id gövdeden alınıyor; ileride oturumdan alınacak.
        Response::json(['success' => true, 'data' => $this->service->create(Request::json())], 201);
    }

    // PUT /api/listings/{id}
    public function update(int $id): void
    {
        Response::json(['success' => true, 'data' => $this->service->update($id, Request::json())]);
    }

    // DELETE /api/listings/{id}
    public function destroy(int $id): void
    {
        $this->service->delete($id);
        Response::json(['success' => true, 'message' => 'İlan silindi']);
    }
}
