<?php
/** SUNUM KATMANI - İlan ve fotoğraf uç noktaları */
class ListingController
{
    public function __construct(private ListingService $service, private ImageService $images) {}

    // GET /api/listings?q=&category_id=&listing_type=&item_condition=&min_price=&max_price=&sort=&limit=&offset=   (herkese açık, sadece aktif ilanlar)
    public function index(): void
    {
        $allowed = ['q', 'category_id', 'listing_type', 'item_condition', 'min_price', 'max_price', 'user_id', 'sort', 'limit', 'offset'];
        Response::json(['success' => true] + $this->service->search(array_intersect_key($_GET, array_flip($allowed))));
    }

    // GET /api/listings/options   (form seçenekleri)
    public function options(): void
    {
        Response::json(['success' => true, 'data' => ListingService::options()]);
    }

    // GET /api/me/listings   (giriş gerekli: kendi ilanlarım, tüm durumlar)
    public function mine(): void
    {
        Response::json(['success' => true] + $this->service->mine((int) Auth::requireUser()['id']));
    }

    // GET /api/listings/{id}   (herkese açık; kaldırılmış ilanı sadece sahibi/yönetici görür)
    public function show(int $id): void
    {
        Response::json(['success' => true, 'data' => $this->service->getVisible($id, Auth::user())]);
    }

    // POST /api/listings   (giriş gerekli)
    public function store(): void
    {
        $user = Auth::requireUser();
        Response::json(['success' => true, 'data' => $this->service->create(Request::json(), $user)], 201);
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

    // POST /api/listings/{id}/images   (multipart/form-data, alan adı: image)
    public function uploadImage(int $id): void
    {
        $user = Auth::requireUser();
        if (empty($_FILES['image'])) {
            // post_max_size aşılırsa PHP $_FILES'ı tamamen boş bırakır
            throw new ValidationException(($_SERVER['CONTENT_LENGTH'] ?? 0) > 0 ? 'Fotoğraf çok büyük (en fazla 5 MB)' : 'Fotoğraf seçilmedi');
        }
        $img = $this->images->upload($id, $_FILES['image'], $this->service, $user);
        Response::json(['success' => true, 'data' => $img], 201);
    }

    // DELETE /api/listings/{id}/images/{imageId}
    public function deleteImage(int $id, int $imageId): void
    {
        $this->images->delete($id, $imageId, $this->service, Auth::requireUser());
        Response::json(['success' => true, 'message' => 'Fotoğraf silindi']);
    }

    // PUT /api/listings/{id}/images/{imageId}/cover
    public function coverImage(int $id, int $imageId): void
    {
        $this->images->makeCover($id, $imageId, $this->service, Auth::requireUser());
        Response::json(['success' => true, 'data' => $this->service->get($id)['images']]);
    }
}
