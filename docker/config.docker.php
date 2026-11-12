<?php
// Canlı sunucu (Docker/Render) ayarları. start.sh bu dosyayı backend/config/config.php olarak kopyalar
// ve __DB_PASS__ yerine her açılışta rastgele üretilen veritabanı şifresini yazar.
return [
    'db' => [
        'host'    => '127.0.0.1',
        'port'    => 3306,
        'name'    => 'kampus_takas',
        'user'    => 'kampus',
        'pass'    => '__DB_PASS__',
        'charset' => 'utf8mb4',
    ],
    'app' => [
        'name'           => 'Kampüs Takas',
        'debug'          => false,      // canlıda hata ayrıntıları ziyaretçiye gösterilmez
        'allowed_origin' => '*',
    ],
    'auth' => [
        'token_ttl_hours'       => 8,
        'cookie_name'           => 'kt_token',
        'cookie_secure'         => true,   // site HTTPS üzerinden yayında
        'max_failed_logins'     => 5,
        'lock_minutes'          => 15,
        'student_email_domains' => ['bingol.edu.tr', 'ogrenci.example.com'],
    ],
    'ai' => [
        'api_key' => '',
    ],
];
