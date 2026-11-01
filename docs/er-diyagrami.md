# ER Diyagramı – Kampüs Takas

> GitHub bu diyagramı otomatik çizer. Tablo tanımlarının tamamı: [`backend/database/schema.sql`](../backend/database/schema.sql)

```mermaid
erDiagram
    USERS ||--o{ LISTINGS : "ilan verir"
    CATEGORIES ||--o{ LISTINGS : "sınıflandırır"
    LISTINGS ||--o{ LISTING_IMAGES : "fotoğrafları"
    USERS ||--o{ FAVORITES : "favoriler"
    LISTINGS ||--o{ FAVORITES : "favorilenir"
    LISTINGS ||--o{ MESSAGES : "hakkında"
    USERS ||--o{ MESSAGES : "gönderir / alır"
    LISTINGS ||--o{ TRADE_OFFERS : "teklif alır"
    USERS ||--o{ TRADE_OFFERS : "teklif yapar"
    TRADE_OFFERS ||--o{ REVIEWS : "sonrası"
    USERS ||--o{ REVIEWS : "yazar / alır"
    USERS ||--o{ NOTIFICATIONS : "alır"

    USERS {
        int id PK
        varchar full_name
        varchar email UK
        varchar password_hash
        varchar student_no UK
        varchar department
        bool is_verified
    }
    CATEGORIES {
        int id PK
        varchar name UK
        varchar slug UK
    }
    LISTINGS {
        int id PK
        int user_id FK
        int category_id FK
        varchar title
        text description
        enum item_condition
        enum listing_type "satis/takas/bagis"
        decimal price
        decimal ai_suggested_price
        varchar meeting_point
        enum status
    }
    LISTING_IMAGES {
        int id PK
        int listing_id FK
        varchar image_url
    }
    FAVORITES {
        int user_id PK,FK
        int listing_id PK,FK
    }
    MESSAGES {
        int id PK
        int listing_id FK
        int sender_id FK
        int receiver_id FK
        text body
        bool is_read
    }
    TRADE_OFFERS {
        int id PK
        int listing_id FK
        int offerer_id FK
        int offered_listing_id FK
        decimal offered_amount
        enum status
    }
    REVIEWS {
        int id PK
        int trade_offer_id FK
        int reviewer_id FK
        int reviewed_id FK
        tinyint rating "1-5"
        varchar comment
    }
    NOTIFICATIONS {
        int id PK
        int user_id FK
        enum type
        varchar content
        bool is_read
    }
```

## İlişki Türleri

| Tür | Örnek | Açıklama |
|---|---|---|
| **1-1** | (ileride) `users` – profil ayarları | Bir kullanıcının tek bir ayar kaydı olur. Bu projede ayrı tabloya gerek görülmedi, alanlar `users` içinde. |
| **1-N** | `users` → `listings` | Bir öğrenci çok ilan verebilir, her ilan tek bir öğrenciye aittir (`listings.user_id`). |
| **1-N** | `categories` → `listings` | Bir kategoride çok ilan olur, ilan tek kategoridedir. |
| **N-N** | `users` ↔ `listings` (favori) | Bir öğrenci çok ilanı, bir ilan çok öğrenci tarafından favorilenir. Ara tablo: `favorites` (birleşik birincil anahtar). |

## Normalizasyon

- **1NF:** Her hücrede tek değer var. İlan fotoğrafları virgülle tek sütuna yazılmadı, `listing_images` tablosuna ayrıldı.
- **2NF:** Birleşik anahtarlı `favorites` tablosunda anahtarın yarısına bağlı sütun yok.
- **3NF:** Kategori adı ilanın içinde tekrar yazılmıyor; `category_id` ile `categories` tablosuna bağlanıyor. Kullanıcı adı mesajlara kopyalanmıyor, `sender_id` ile bağlanıyor.
