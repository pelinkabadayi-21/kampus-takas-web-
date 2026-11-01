<?php
/** Sınıfları otomatik yükler (API, migrate ve seed betikleri ortak kullanır). */
spl_autoload_register(function (string $class) {
    foreach (['Core', 'Controllers', 'Services', 'Repositories'] as $dir) {
        $file = __DIR__ . "/$dir/$class.php";
        if (file_exists($file)) { require $file; return; }
    }
});
