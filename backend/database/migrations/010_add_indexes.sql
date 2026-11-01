-- 010: Sorgu performansı için indeksler
-- Not: Yabancı anahtar sütunlarına InnoDB zaten otomatik indeks koyar.
-- Buradakiler, sitede EN SIK çalışacak sorgulara göre seçildi.

-- "Kategorideki aktif ilanları en yeniden eskiye listele" (ana liste sorgusu)
CREATE INDEX idx_listings_cat_status_date ON listings (category_id, status, created_at);

-- "Tüm aktif ilanlar, en yeniler" (kategori seçilmeden)
CREATE INDEX idx_listings_status_date ON listings (status, created_at);

-- Fiyat aralığı filtresi ve fiyata göre sıralama
CREATE INDEX idx_listings_price ON listings (price);

-- Bir ilan üzerindeki konuşmayı tarih sırasıyla getirme
CREATE INDEX idx_messages_conversation ON messages (listing_id, sender_id, receiver_id, created_at);

-- "Okunmamış mesaj sayım"
CREATE INDEX idx_messages_unread ON messages (receiver_id, is_read);

-- Bir ilana gelen bekleyen teklifler
CREATE INDEX idx_offers_listing_status ON trade_offers (listing_id, status);

-- Bildirim zili: okunmamış bildirimler
CREATE INDEX idx_notifications_unread ON notifications (user_id, is_read, created_at);

-- Bilinçli olarak EKLENMEYENLER:
--  * listings.title  -> aramada LIKE '%kelime%' kullanılıyor; baştaki % yüzünden normal indeks işe yaramaz.
--  * is_verified gibi sadece 0/1 alan sütunlar tek başına -> seçicilik çok düşük.
