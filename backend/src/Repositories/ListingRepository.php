<?php
/** VERİ KATMANI - İlan ve ilan fotoğrafı tablolarının SQL sorguları (ekle, bul, ara, güncelle, sil). */
class ListingRepository extends BaseRepository
{
    // Güncellenmesine izin verilen sütunlar (user_id yok: ilanın sahibi değiştirilemez)
    private const EDITABLE = ['category_id', 'title', 'description', 'item_condition', 'listing_type',
                              'price', 'ai_suggested_price', 'meeting_point', 'status'];

    // ---------- CREATE ----------
    public function create(array $d): int
    {
        // Gelen veriden sadece tabloya ait sütunları al
        $row = array_intersect_key($d, array_flip(array_merge(['user_id'], self::EDITABLE)));
        // Oluşturulma zamanını PHP'den yaz (saat dilimi tutarlı olsun)
        $row['created_at'] = date('Y-m-d H:i:s');
        return $this->insert('listings', $row);
    }

    /** İlana fotoğraf kaydı ekler (dosya adresi + sıra numarası). */
    public function addImage(int $listingId, string $url, int $order = 0): int
    {
        return $this->insert('listing_images', ['listing_id' => $listingId, 'image_url' => $url, 'sort_order' => $order]);
    }

    /** id'ye göre tek fotoğraf kaydını getirir. */
    public function findImage(int $imageId): ?array
    {
        return $this->fetchOne('SELECT id, listing_id, image_url, sort_order FROM listing_images WHERE id = ?', [$imageId]);
    }

    /** Bir ilanın bütün fotoğraflarını sırasıyla getirir. */
    public function imagesOf(int $listingId): array
    {
        return $this->fetchAll('SELECT id, image_url, sort_order FROM listing_images WHERE listing_id = ? ORDER BY sort_order, id', [$listingId]);
    }

    /** Yeni eklenecek fotoğrafın sıra numarasını bulur. */
    public function nextImageOrder(int $listingId): int
    {
        // En büyük sıra numarasının bir fazlası; hiç fotoğraf yoksa 0
        return (int) $this->fetchOne('SELECT COALESCE(MAX(sort_order) + 1, 0) AS n FROM listing_images WHERE listing_id = ?', [$listingId])['n'];
    }

    /** Fotoğraf kaydını siler. */
    public function deleteImage(int $imageId): int
    {
        return $this->execute('DELETE FROM listing_images WHERE id = ?', [$imageId]);
    }

    /** Seçilen fotoğrafı 0. sıraya alır, diğerlerini birer kaydırır. */
    public function setCoverImage(int $listingId, int $imageId): void
    {
        // Kapak dışındaki fotoğraflar 1, 2, 3... sırasını alır
        $order = 1;
        // Her fotoğrafı dolaş: seçilen fotoğraf 0 (kapak), diğerleri sırayla
        foreach ($this->imagesOf($listingId) as $img) {
            $this->execute('UPDATE listing_images SET sort_order = ? WHERE id = ?',
                           [(int) $img['id'] === $imageId ? 0 : $order++, $img['id']]);
        }
    }

    /** Kullanıcının yayındaki (aktif) ilan sayısı - ilan limiti kuralı için. */
    public function countActiveByUser(int $userId): int
    {
        return (int) $this->fetchOne("SELECT COUNT(*) AS n FROM listings WHERE user_id = ? AND status = 'aktif'", [$userId])['n'];
    }

    /** Kullanıcının aynı başlıkta aktif ilanı var mı - mükerrer ilan kuralı için. */
    public function activeTitleExists(int $userId, string $title, ?int $exceptId = null): bool
    {
        return (bool) $this->fetchOne(
            // Büyük/küçük harfe bakmadan aynı başlık var mı (düzenlenen ilanın kendisi hariç)
            "SELECT 1 AS x FROM listings WHERE user_id = ? AND status = 'aktif' AND LOWER(title) = LOWER(?) AND id <> ?",
            [$userId, $title, $exceptId ?? 0]);
    }

    // ---------- READ ----------
    public function findById(int $id): ?array
    {
        $row = $this->fetchOne(
            'SELECT l.*, c.name AS category_name, u.full_name AS owner_name, u.is_verified AS owner_verified,
                    (SELECT ROUND(AVG(r.rating), 1) FROM reviews r WHERE r.reviewed_id = l.user_id) AS owner_rating,
                    (SELECT COUNT(*) FROM reviews r WHERE r.reviewed_id = l.user_id) AS owner_review_count
             FROM listings l
             JOIN categories c ON c.id = l.category_id
             JOIN users u      ON u.id = l.user_id
             WHERE l.id = ?', [$id]);
        // İlan bulunduysa fotoğraflarını da ekle
        if ($row) {
            // Fotoğraflar ayrı tabloda (1 ilan -> N fotoğraf)
            $row['images'] = $this->imagesOf($id);
        }
        return $row;
    }

    /**
     * Filtreli arama. Tüm filtreler isteğe bağlıdır.
     * $f: q, category_id, listing_type, item_condition, min_price, max_price, user_id,
     *     status ('hepsi' = tüm durumlar), sort, limit, offset
     */
    public function search(array $f = []): array
    {
        [$where, $params] = $this->buildWhere($f);

        // Sıralama sadece bu seçeneklerden biri olabilir; kullanıcı SQL'e kendi metnini ekleyemez
        $sort = match ($f['sort'] ?? 'yeni') {
            'ucuz'  => 'l.price IS NULL, l.price ASC',
            'pahali'=> 'l.price DESC',
            'eski'  => 'l.created_at ASC',
            default => 'l.created_at DESC, l.id DESC',
        };
        // Sayfa başına 1-50 ilan (varsayılan 12)
        $limit  = max(1, min(50, (int) ($f['limit'] ?? 12)));
        // Kaçıncı ilandan başlanacağı (sayfalama)
        $offset = max(0, (int) ($f['offset'] ?? 0));

        return $this->fetchAll(
            "SELECT l.id, l.title, l.price, l.listing_type, l.item_condition, l.status, l.meeting_point,
                    l.created_at, c.name AS category_name, u.full_name AS owner_name,
                    (SELECT image_url FROM listing_images i WHERE i.listing_id = l.id ORDER BY sort_order, id LIMIT 1) AS cover_image,
                    (SELECT COUNT(*) FROM listing_images i WHERE i.listing_id = l.id) AS image_count
             FROM listings l
             JOIN categories c ON c.id = l.category_id
             JOIN users u      ON u.id = l.user_id
             $where
             ORDER BY $sort
             LIMIT $limit OFFSET $offset", $params);
    }

    /** Filtrelere uyan toplam ilan sayısı (sayfalama için). */
    public function count(array $f = []): int
    {
        [$where, $params] = $this->buildWhere($f);
        // Aynı filtrelerle toplam ilan sayısı
        return (int) $this->fetchOne("SELECT COUNT(*) AS n FROM listings l $where", $params)['n'];
    }

    /** Arama ve filtrelerden SQL WHERE koşulunu ve parametrelerini oluşturur (değerler ? ile güvenli gönderilir). */
    private function buildWhere(array $f): array
    {
        // $w: WHERE koşulları, $p: bu koşulların değerleri
        $w = []; $p = [];
        // Varsayılan olarak sadece yayındaki ilanlar; "hepsi" verilirse tüm durumlar
        $status = $f['status'] ?? 'aktif';
        if ($status !== 'hepsi') { $w[] = 'l.status = ?'; $p[] = $status; }
        // Arama kelimesi: başlıkta veya açıklamada geçsin (% = herhangi bir metin)
        if (!empty($f['q']))            { $w[] = '(l.title LIKE ? OR l.description LIKE ?)'; $p[] = "%{$f['q']}%"; $p[] = "%{$f['q']}%"; }
        // Kategori filtresi
        if (!empty($f['category_id']))  { $w[] = 'l.category_id = ?';  $p[] = (int) $f['category_id']; }
        // Tür filtresi (satis / takas / bagis)
        if (!empty($f['listing_type'])) { $w[] = 'l.listing_type = ?'; $p[] = $f['listing_type']; }
        // Ürün durumu filtresi (yeni, iyi...)
        if (!empty($f['item_condition'])) { $w[] = 'l.item_condition = ?'; $p[] = $f['item_condition']; }
        // Belirli bir kullanıcının ilanları
        if (!empty($f['user_id']))      { $w[] = 'l.user_id = ?';      $p[] = (int) $f['user_id']; }
        // En düşük fiyat
        if (isset($f['min_price']) && $f['min_price'] !== '') { $w[] = 'l.price >= ?'; $p[] = (float) $f['min_price']; }
        // En yüksek fiyat
        if (isset($f['max_price']) && $f['max_price'] !== '') { $w[] = 'l.price <= ?'; $p[] = (float) $f['max_price']; }
        // Koşulları AND ile birleştir; hiç koşul yoksa WHERE yazma
        return [$w ? 'WHERE ' . implode(' AND ', $w) : '', $p];
    }

    // ---------- GÜNCELLEME ----------
    public function update(int $id, array $d): int
    {
        // Sadece EDITABLE listesindeki sütunlar güncellenir
        return $this->updateById('listings', $id, $d, self::EDITABLE);
    }

    /** İlanın durumunu değiştirir (aktif / rezerve / tamamlandi / kaldirildi). */
    public function setStatus(int $id, string $status): int
    {
        return $this->execute('UPDATE listings SET status = ? WHERE id = ?', [$status, $id]);
    }

    // ---------- SİLME ----------
    public function delete(int $id): int
    {
        // Fotoğraflar, mesajlar, favoriler ON DELETE CASCADE ile otomatik silinir
        return $this->execute('DELETE FROM listings WHERE id = ?', [$id]);
    }
}
