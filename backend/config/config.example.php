<?php
// Bu dosyayı "config.php" adıyla kopyalayıp kendi bilgilerinizi girin.
// config.php .gitignore'da olduğu için GitHub'a yüklenmez (şifreler gizli kalır).
return [
    'db' => [
        'host'    => '127.0.0.1',
        'port'    => 3306,
        'name'    => 'kampus_takas',
        'user'    => 'root',
        'pass'    => '',          // XAMPP varsayılanı boş
        'charset' => 'utf8mb4',
    ],
    'app' => [
        'name'          => 'Kampüs Takas',
        'debug'         => true,
        'allowed_origin'=> '*',   // frontend adresi (geliştirmede *)
    ],
    'auth' => [
        'token_ttl_hours'   => 8,       // oturum süresi; sonra tekrar giriş gerekir
        'cookie_name'       => 'kt_token',
        'cookie_secure'     => false,   // HTTPS'e geçince true yapın
        'max_failed_logins' => 5,       // bu kadar hatalı denemeden sonra hesap kilitlenir
        'lock_minutes'      => 15,
        // Bu alan adlarıyla kayıt olanlar "doğrulanmış öğrenci" sayılır
        'student_email_domains' => ['bingol.edu.tr', 'ogrenci.example.com'],
    ],
    // Hafta 9 – Harici API'ler (Cloudinary görsel depolama, Brevo e-posta).
    // API ANAHTARLARI BURAYA YAZILMAZ: sunucunun ortam değişkenlerinden veya backend/.env dosyasından
    // okunur (örnek: backend/.env.example). Anahtar yoksa o özellik kapalı çalışır, site hata vermez.
    'integrations' => [
        'timeout' => 8,     // harici servisin cevabı için en fazla kaç saniye beklenir
        'retries' => 2,     // geçici hatada (zaman aşımı, 429, 5xx) en fazla kaç kez daha denenir
    ],
    // Hafta 10 – Yapay zekâ önerisi: GEMINI_API_KEY ve (isteğe bağlı) AI_MODEL ortam değişkeninden okunur.
    // Anahtar yoksa öneriler kural tabanlı yapılır (kategori anahtar kelimeyle, fiyat site medyanıyla).
];
