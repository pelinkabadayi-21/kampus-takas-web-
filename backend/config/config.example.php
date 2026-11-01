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
    'ai' => [
        'api_key' => '',          // Yapay zekâ fiyat önerisi (ileriki haftalar)
    ],
];
