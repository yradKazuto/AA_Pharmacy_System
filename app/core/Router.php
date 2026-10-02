<?php

namespace App\Core;

class Router
{
    private $routes = [];
    private $baseUrl;

    public function __construct()
    {
        $scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '';

        // Strip the script's directory (e.g. /aa_pharmacy/public) from the
        // request URI so routes are relative to the project root.
        $this->baseUrl = ($scriptDir !== '' && $scriptDir !== '/') ? str_replace($scriptDir, '', $path) : $path;
    }

    /**
     * Register a GET route.
     *
     * @param string $uri
     * @param string $action Controller@method
     * @return void
     */
    public function get($uri, $action)
    {
        $this->routes['GET'][$uri] = $action;
    }

    /**
     * Register a POST route.
     *
     * @param string $uri
     * @param string $action Controller@method
     * @return void
     */
    public function post($uri, $action)
    {
        $this->routes['POST'][$uri] = $action;
    }

    /**
     * Register a route for both GET and POST.
     *
     * @param string $uri
     * @param string $action Controller@method
     * @return void
     */
    public function match($uri, $action)
    {
        $this->routes['GET'][$uri] = $action;
        $this->routes['POST'][$uri] = $action;
    }

    /**
     * Dispatch the current request.
     *
     * @return void
     */
    public function dispatch()
    {
        $uri = $this->baseUrl;
        $method = $_SERVER['REQUEST_METHOD'];

        // Normalize URI
        if ($uri === '') {
            $uri = '/';
        }

        // Check for exact match first
        if (isset($this->routes[$method][$uri])) {
            $this->callAction($this->routes[$method][$uri]);
            return;
        }

        // Check for parameterized routes
        if (isset($this->routes[$method])) {
            foreach ($this->routes[$method] as $route => $action) {
            $pattern = preg_replace('/\{[a-zA-Z0-9_]+\}/', '([^/]+)', $route);
            $pattern = '/^' . str_replace('/', '\/', $pattern) . '$/';

            if (preg_match($pattern, $uri, $matches)) {
                array_shift($matches); // Remove the full match
                $this->callAction($action, $matches);
                return;
            }
            }
        }

        // 404 Not Found
        header("HTTP/1.0 404 Not Found");
        echo "404 Not Found";
    }

    /**
     * Call the controller action.
     *
     * @param string $action Controller@method
     * @param array $params
     * @return void
     */
    private function callAction($action, $params = [])
    {
        // Handle Closure routes (e.g. dashboard placeholders)
        if ($action instanceof \Closure) {
            call_user_func_array($action, $params);
            return;
        }

        list($controller, $method) = explode('@', $action);

        // Convert to proper class names
        $controllerClass = "App\\Controllers\\{$controller}";
        $controllerInstance = new $controllerClass();

        if (!method_exists($controllerInstance, $method)) {
            header("HTTP/1.0 500 Internal Server Error");
            echo "Method {$method} not found in {$controller}";
            return;
        }

        call_user_func_array([$controllerInstance, $method], $params);
    }

    /**
     * Redirect the client to a URL.
     *
     * @param string $url
     * @return void
     */
    public function redirect($url)
    {
        header("Location: " . $url);
        exit;
    }
}