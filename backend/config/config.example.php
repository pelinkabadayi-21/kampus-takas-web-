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
    'ai' => [
        'api_key' => '',          // Yapay zekâ fiyat önerisi (ileriki haftalar)
    ],
];
