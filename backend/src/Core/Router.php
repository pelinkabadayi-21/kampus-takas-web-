<?php
/** Basit yönlendirici: "GET /api/health" gibi rotaları controller fonksiyonlarına bağlar. */
class Router
{
    private array $routes = [];

    public function get(string $path, callable $handler): void  { $this->routes['GET'][$path]  = $handler; }
    public function post(string $path, callable $handler): void { $this->routes['POST'][$path] = $handler; }

    public function dispatch(string $method, string $uri): void
    {
        $path = rtrim(parse_url($uri, PHP_URL_PATH), '/') ?: '/';
        // /kampus-takas-web-/backend/public/api/health  ->  /api/health
        $pos = strpos($path, '/api');
        if ($pos !== false) {
            $path = substr($path, $pos);
        }
        $handler = $this->routes[$method][$path] ?? null;
        if (!$handler) {
            Response::error("Rota bulunamadı: $method $path", 404);
        }
        $handler();
    }
}
