<?php
/**
 * Basit yönlendirici.
 * "/api/listings/{id}" gibi parametreli rotaları destekler; {id} değeri fonksiyona sayı olarak geçer.
 */
class Router
{
    private array $routes = [];

    public function get(string $p, callable $h): void    { $this->add('GET', $p, $h); }
    public function post(string $p, callable $h): void   { $this->add('POST', $p, $h); }
    public function put(string $p, callable $h): void    { $this->add('PUT', $p, $h); }
    public function delete(string $p, callable $h): void { $this->add('DELETE', $p, $h); }

    private function add(string $method, string $path, callable $handler): void
    {
        $regex = '#^' . preg_replace('#\{(\w+)\}#', '(\d+)', $path) . '$#';
        $this->routes[$method][$regex] = $handler;
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = rtrim(parse_url($uri, PHP_URL_PATH), '/') ?: '/';
        $pos = strpos($path, '/api');
        if ($pos !== false) $path = substr($path, $pos);

        foreach ($this->routes[$method] ?? [] as $regex => $handler) {
            if (preg_match($regex, $path, $m)) {
                $handler(...array_map('intval', array_slice($m, 1)));
                return;
            }
        }
        Response::error("Rota bulunamadı: $method $path", 404);
    }
}
