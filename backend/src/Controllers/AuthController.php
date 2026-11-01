<?php
/** SUNUM KATMANI - /api/auth/* uçları */
class AuthController
{
    public function __construct(private AuthService $auth, private array $cfg) {}

    // POST /api/auth/register
    public function register(): void
    {
        $user = $this->auth->register(Request::json());
        Response::json(['success' => true, 'message' => 'Kayıt başarılı, giriş yapabilirsiniz', 'user' => $user], 201);
    }

    // POST /api/auth/login
    public function login(): void
    {
        $d = Request::json();
        [$token, $user] = $this->auth->login((string) ($d['email'] ?? ''), (string) ($d['password'] ?? ''),
                                             $_SERVER['HTTP_USER_AGENT'] ?? null);
        $this->setCookie($token, time() + 3600 * (int) $this->cfg['token_ttl_hours']);

        // Token yanıtta da döner: Postman gibi araçlarla "Authorization: Bearer" ile test edilebilsin.
        // Tarayıcıdaki site bunu SAKLAMAZ, HttpOnly çerezi kullanır.
        Response::json(['success' => true, 'user' => $user, 'token' => $token,
                        'expires_in' => 3600 * (int) $this->cfg['token_ttl_hours']]);
    }

    // POST /api/auth/logout
    public function logout(): void
    {
        $this->auth->logout(Auth::token($this->cfg['cookie_name']));
        $this->setCookie('', time() - 3600);
        Response::json(['success' => true, 'message' => 'Çıkış yapıldı']);
    }

    // GET /api/auth/me
    public function me(): void
    {
        Response::json(['success' => true, 'user' => Auth::requireUser()]);
    }

    private function setCookie(string $value, int $expires): void
    {
        setcookie($this->cfg['cookie_name'], $value, [
            'expires'  => $expires,
            'path'     => '/',
            'httponly' => true,       // JavaScript okuyamaz -> XSS ile token çalınamaz
            'secure'   => (bool) $this->cfg['cookie_secure'],
            'samesite' => 'Lax',      // başka sitelerden gelen POST isteklerine çerez eklenmez (CSRF koruması)
        ]);
    }
}
