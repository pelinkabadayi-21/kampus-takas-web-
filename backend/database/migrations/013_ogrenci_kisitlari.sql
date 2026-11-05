-- 013: ÖĞRENCİ BÖLÜMÜ (Hafta 4) — elle yazılan kısıt
--
-- Kısıt: İlan başlığı en az 3, en fazla 120 karakter olmalı.
-- Neden: Bu kural ListingService içinde de kontrol ediliyor; ama phpMyAdmin'den veya başka bir
-- programdan doğrudan veritabanına yazılırsa servis devreye girmez. CHECK kısıtı, kuralı
-- veritabanının kendisine koyar; böylece kural hangi yoldan veri gelirse gelsin uygulanır.
-- CHAR_LENGTH karakter sayar (LENGTH bayt sayar; Türkçe "ş", "ğ" 2 bayt olduğu için yanlış sonuç verirdi).
ALTER TABLE listings ADD CONSTRAINT chk_listing_title_len CHECK (CHAR_LENGTH(title) BETWEEN 3 AND 120);
