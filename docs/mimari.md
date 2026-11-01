# Mimari Şema – Katmanlı Mimari

Proje **frontend** ve **backend** olarak ikiye ayrılır. Backend kendi içinde üç katmandan oluşur: **sunum → iş → veri**.

```mermaid
flowchart TB
    subgraph FE["🖥️ FRONTEND  (frontend/)"]
        HTML["HTML sayfaları<br/>index.html, pages/*.html"]
        JS["JavaScript<br/>js/api.js → fetch()"]
        HTML --> JS
    end

    subgraph BE["⚙️ BACKEND  (backend/)"]
        IDX["public/index.php<br/>Giriş noktası + Router"]
        C["SUNUM KATMANI<br/>src/Controllers<br/>İsteği al, JSON döndür"]
        S["İŞ KATMANI<br/>src/Services<br/>Kurallar: fiyat kontrolü, teklif durumu, yetki"]
        R["VERİ KATMANI<br/>src/Repositories<br/>Sadece SQL sorguları (PDO)"]
        IDX --> C --> S --> R
    end

    DB[("🗄️ MySQL<br/>kampus_takas")]
    AI["🤖 Yapay Zekâ API<br/>(fiyat/kategori önerisi)"]

    JS -- "HTTP / JSON<br/>GET /api/categories" --> IDX
    R --> DB
    S -. "ileriki haftalar" .-> AI
```

## Bir isteğin yolculuğu (örnek: kategorileri listele)

1. `frontend/js/app.js` → `apiGet('/categories')` çağırır.
2. `backend/public/index.php` rotayı bulur → `CategoryController`.
3. `CategoryController` → `CategoryService::list()` çağırır.
4. `CategoryService` → `CategoryRepository::all()` çağırır.
5. Repository SQL'i çalıştırır, sonuç aynı yoldan geri döner ve JSON olarak frontend'e gider.

## Klasör yapısı ve gerekçesi

```
kampus-takas-web-/
├── backend/
│   ├── config/          → Ayarlar. config.php gizli (.gitignore), örneği config.example.php
│   ├── database/        → migrations/ (numaralı .sql), seeds/ (örnek veri), migrate.php, schema.sql
│   ├── tests/           → crud_test.php
│   ├── public/          → Dışarıya açık tek klasör: index.php, .htaccess, uploads/
│   └── src/
│       ├── Core/        → Ortak araçlar: Database, Router, Response
│       ├── Controllers/ → Sunum katmanı
│       ├── Services/    → İş katmanı
│       └── Repositories/→ Veri katmanı
├── frontend/
│   ├── index.html
│   ├── pages/           → Diğer sayfalar
│   ├── css/  js/  assets/
└── docs/                → ER diyagramı, mimari, haftalık raporlar
```

**Neden bu yapı?**
- Sadece `public/` dışarı açık; `config/` ve `src/` tarayıcıdan doğrudan okunamaz (güvenlik).
- Her katmanın tek görevi var: SQL değişince sadece Repository, kural değişince sadece Service değişir.
- Frontend backend'den ayrı olduğu için ileride React/mobil uygulama aynı API'yi kullanabilir.
