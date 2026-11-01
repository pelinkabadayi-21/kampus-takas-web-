-- 012: Mesajlaşma ve takas teklifi iyileştirmeleri (Hafta 7)

-- Teklife ne zaman yanıt verildiği (kabul/red/iptal anı)
ALTER TABLE trade_offers ADD COLUMN responded_at DATETIME NULL;

-- "Gönderdiğim teklifler" ve "bu kişinin bekleyen teklifi var mı?" sorguları için
CREATE INDEX idx_offers_offerer_status ON trade_offers (offerer_id, status);

-- Bir ilanın karşılığında önerildiği teklifleri bulmak için (ilan rezerve olunca o teklifler iptal edilir)
CREATE INDEX idx_offers_offered_listing ON trade_offers (offered_listing_id, status);

-- Gelen kutusu: kullanıcının gönderdiği mesajlar (aldıkları için idx_messages_unread zaten var)
CREATE INDEX idx_messages_sender_date ON messages (sender_id, created_at);
