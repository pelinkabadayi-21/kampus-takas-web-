<?php
/**
 * Basit yönlendirici.
 * "/api/listings/{id}" gibi parametreli rotaları destekler; {id} değeri fonksiyona sayı olarak geçer.
 */
class Router
{
    private array $routes = [];

    /** GET isteği için rota ekler (veri okuma). */
    public function get(string $p, callable $h): void    { $this->add('GET', $p, $h); }
    /** POST isteği için rota ekler (yeni kayıt). */
    public function post(string $p, callable $h): void   { $this->add('POST', $p, $h); }
    /** PUT isteği için rota ekler (güncelleme). */
    public function put(string $p, callable $h): void    { $this->add('PUT', $p, $h); }
    /** DELETE isteği için rota ekler (silme). */
    public function delete(string $p, callable $h): void { $this->add('DELETE', $p, $h); }

    /** Rotayı kaydeder; "/api/listings/{id}" gibi adresleri, sayıyı yakalayan bir düzenli ifadeye çevirir. */
    private function add(string $method, string $path, callable $handler): void
    {
        // {id} gibi parametreler sayıdır; {action} ise harflerden oluşan bir kelimedir
        $regex = '#^' . preg_replace_callback('#\{(\w+)\}#',
            fn($m) => $m[1] === 'action' ? '([a-z]+)' : '(\d+)', $path) . '$#';
        $this->routes[$method][$regex] = $handler;
    }

    /** Gelen isteğin adresine uyan rotayı bulur ve fonksiyonunu çalıştırır; bulamazsa 404 döner. */
    public function dispatch(string $method, string $uri): void
    {
        $path = rtrim(parse_url($uri, PHP_URL_PATH), '/') ?: '/';
        $pos = strpos($path, '/api');
        if ($pos !== false) $path = substr($path, $pos);

        foreach ($this->routes[$method] ?? [] as $regex => $handler) {
            if (preg_match($regex, $path, $m)) {
                $handler(...array_map(fn($v) => ctype_digit($v) ? (int) $v : $v, array_slice($m, 1)));
                return;
            }
        }
        Response::error("Rota bulunamadı: $method $path", 404);
    }
}
