<?php
/** SUNUM KATMANI - İlan CRUD uç noktaları */
class ListingController
{
    public function __construct(private ListingService $service) {}

    // GET /api/listings?q=&category_id=&listing_type=&min_price=&max_price=&sort=&limit=&offset=   (herkese açık)
    public function index(): void
    {
        $allowed = ['q', 'category_id', 'listing_type', 'min_price', 'max_price', 'user_id', 'sort', 'limit', 'offset'];
        Response::json(['success' => true] + $this->service->search(array_intersect_key($_GET, array_flip($allowed))));
    }

    // GET /api/listings/{id}   (herkese açık)
    public function show(int $id): void
    {
        Response::json(['success' => true, 'data' => $this->service->get($id)]);
    }

    // POST /api/listings   (giriş gerekli)
    public function store(): void
    {
        $user = Auth::requireUser();
        $data = Request::json();
        $data['user_id'] = $user['id'];           // ilan sahibi gövdeden DEĞİL, oturumdan alınır
        Response::json(['success' => true, 'data' => $this->service->create($data)], 201);
    }

    // PUT /api/listings/{id}   (ilan sahibi veya yönetici)
    public function update(int $id): void
    {
        $user = Auth::requireUser();
        Response::json(['success' => true, 'data' => $this->service->update($id, Request::json(), $user)]);
    }

    // DELETE /api/listings/{id}   (ilan sahibi veya yönetici)
    public function destroy(int $id): void
    {
        $this->service->delete($id, Auth::requireUser());
        Response::json(['success' => true, 'message' => 'İlan silindi']);
    }
}
