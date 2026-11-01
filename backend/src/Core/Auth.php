<?php
/**
 * YETKİLENDİRME KATMANI
 * Her istekte token'ı okur, kullanıcıyı belirler ve korumalı uçlarda kontrol yapar.
 * Kural: Yetki kontrolü HER ZAMAN sunucuda yapılır. Arayüzde butonu gizlemek güvenlik değildir.
 */
class Auth
{
    private static ?array $user = null;
    private static bool $resolved = false;
    private static ?Closure $resolver = null;

    /** index.php kullanıcıyı token'dan bulacak fonksiyonu verir (DB'ye sadece gerekirse bağlanılır). */
    public static function setResolver(Closure $fn): void { self::$resolver = $fn; }

    /** Token önce HttpOnly çerezden, yoksa "Authorization: Bearer ..." başlığından okunur. */
    public static function token(string $cookieName = 'kt_token'): ?string
    {
        if (!empty($_COOKIE[$cookieName])) return $_COOKIE[$cookieName];
        $h = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        return preg_match('/^Bearer\s+(\S+)$/i', $h, $m) ? $m[1] : null;
    }

    /** Giriş yapmış kullanıcı ya da null */
    public static function user(): ?array
    {
        if (!self::$resolved) {
            self::$resolved = true;
            self::$user = self::$resolver ? (self::$resolver)() : null;
        }
        return self::$user;
    }

    /** Giriş zorunlu: yoksa 401 */
    public static function requireUser(): array
    {
        return self::user() ?? throw new ValidationException('Bu işlem için giriş yapmalısınız', [], 401);
    }

    /** Belirli rol zorunlu: giriş yoksa 401, rol yetmiyorsa 403 */
    public static function requireRole(string ...$roles): array
    {
        $u = self::requireUser();
        if (!in_array($u['role'], $roles, true)) {
            throw new ValidationException('Bu sayfaya erişim yetkiniz yok', [], 403);
        }
        return $u;
    }

    public static function isAdmin(?array $u = null): bool
    {
        $u ??= self::user();
        return ($u['role'] ?? null) === 'yonetici';
    }
}
