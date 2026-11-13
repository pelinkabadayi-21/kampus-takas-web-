<?php
/** SUNUM KATMANI - /api/auth/* uçları */
class AuthController
{
    /** Giriş/kayıt işlerini yapan servis ve oturum ayarları alınır. */
    public function __construct(private AuthService $auth, private array $cfg) {}

    // POST /api/auth/register
    public function register(): void
    {
        // Formdan gelen JSON'u servise ver; kurallar serviste kontrol edilir
        $user = $this->auth->register(Request::json());
        Response::json(['success' => true, 'message' => 'Kayıt başarılı, giriş yapabilirsiniz', 'user' => $user], 201);
    }

    // POST /api/auth/login
    public function login(): void
    {
        $d = Request::json();
        // E-posta + parola doğruysa servis bir oturum anahtarı ve kullanıcı bilgisi döndürür
        [$token, $user] = $this->auth->login((string) ($d['email'] ?? ''), (string) ($d['password'] ?? ''),
                                             $_SERVER['HTTP_USER_AGENT'] ?? null);
        // Oturum anahtarını HttpOnly çereze yaz (8 saat geçerli)
        $this->setCookie($token, time() + 3600 * (int) $this->cfg['token_ttl_hours']);

        // Oturum anahtarı yanıtta da döner: Postman gibi araçlarla "Authorization: Bearer" ile test edilebilsin.
        // Tarayıcıdaki site bunu SAKLAMAZ, HttpOnly çerezi kullanır.
        Response::json(['success' => true, 'user' => $user, 'token' => $token,
                        'expires_in' => 3600 * (int) $this->cfg['token_ttl_hours']]);
    }

    // POST /api/auth/logout
    public function logout(): void
    {
        // Oturum anahtarını veritabanından sil, çerezi geçmiş tarihle sıfırla
        $this->auth->logout(Auth::token($this->cfg['cookie_name']));
        $this->setCookie('', time() - 3600);
        Response::json(['success' => true, 'message' => 'Çıkış yapıldı']);
    }

    // GET /api/auth/me
    public function me(): void
    {
        // Giriş yapmış kullanıcının bilgilerini döndür (giriş yoksa 401)
        Response::json(['success' => true, 'user' => Auth::requireUser()]);
    }

    /** Oturum anahtarını tarayıcıya güvenli çerez olarak yazar (HttpOnly: JavaScript okuyamaz, SameSite: başka sitelerden gönderilmez). */
    private function setCookie(string $value, int $expires): void
    {
        setcookie($this->cfg['cookie_name'], $value, [
            'expires'  => $expires,
            'path'     => '/',
            'httponly' => true,       // JavaScript okuyamaz -> XSS ile oturum anahtarı çalınamaz
            'secure'   => (bool) $this->cfg['cookie_secure'],
            'samesite' => 'Lax',      // başka sitelerden gelen POST isteklerine çerez eklenmez (CSRF koruması)
        ]);
    }
}
