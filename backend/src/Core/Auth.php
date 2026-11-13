<?php
/**
 * YETKİLENDİRME KATMANI
 * Her istekte oturum anahtarını okur, kullanıcıyı belirler ve korumalı uçlarda kontrol yapar.
 * Kural: Yetki kontrolü HER ZAMAN sunucuda yapılır. Arayüzde butonu gizlemek güvenlik değildir.
 */
class Auth
{
    // Bu istekte giriş yapmış kullanıcı (bir kez bulunur, sonra hatırlanır)
    private static ?array $user = null;
    private static bool $resolved = false;
    private static ?Closure $resolver = null;

    /** index.php kullanıcıyı oturum anahtarından bulacak fonksiyonu verir (veritabanına sadece gerekirse bağlanılır). */
    public static function setResolver(Closure $fn): void { self::$resolver = $fn; }

    /** Oturum anahtarı önce HttpOnly çerezden, yoksa "Authorization: Bearer ..." başlığından okunur. */
    public static function token(string $cookieName = 'kt_token'): ?string
    {
        // Önce tarayıcının gönderdiği HttpOnly çereze bak
        if (!empty($_COOKIE[$cookieName])) return $_COOKIE[$cookieName];
        // Çerez yoksa "Authorization: Bearer <oturum anahtarı>" başlığına bak (Postman gibi araçlar için)
        $h = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        return preg_match('/^Bearer\s+(\S+)$/i', $h, $m) ? $m[1] : null;
    }

    /** Giriş yapmış kullanıcı ya da null */
    public static function user(): ?array
    {
        // Kullanıcıyı sadece ilk sorulduğunda veritabanından bul
        if (!self::$resolved) {
            self::$resolved = true;
            // index.php'nin verdiği fonksiyonla oturum anahtarından kullanıcıyı bul
            self::$user = self::$resolver ? (self::$resolver)() : null;
        }
        return self::$user;
    }

    /** Giriş zorunlu: yoksa 401 */
    public static function requireUser(): array
    {
        // Giriş yoksa 401 hatası
        return self::user() ?? throw new ValidationException('Bu işlem için giriş yapmalısınız', [], 401);
    }

    /** Belirli rol zorunlu: giriş yoksa 401, rol yetmiyorsa 403 */
    public static function requireRole(string ...$roles): array
    {
        // Önce giriş yapılmış mı kontrol et
        $u = self::requireUser();
        // Kullanıcının rolü izin verilen roller arasında değilse 403
        if (!in_array($u['role'], $roles, true)) {
            throw new ValidationException('Bu sayfaya erişim yetkiniz yok', [], 403);
        }
        return $u;
    }

    /** Kullanıcı yönetici mi? (role = yonetici) */
    public static function isAdmin(?array $u = null): bool
    {
        // Kullanıcı verilmediyse giriş yapmış kişiyi kullan
        $u ??= self::user();
        return ($u['role'] ?? null) === 'yonetici';
    }
}
