<?php

declare(strict_types = 1);

namespace Nc\CoreApp;

use ReflectionClass;

class App {

    private const array HTTP_STATUS = [
        200 => 'OK',
        404 => 'Not Found',
        500 => 'Internal Server Error',
    ];

    private Router $router;
    private RequestMapper $mapper;

    public function __construct() {
        $this->router = new Router();
        $this->mapper = new RequestMapper();
    }

    public function handle(): void {
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        $routeInfo = $this->router->match($path, $requestMethod);
        if ($routeInfo === null) {
            $this->sendStatus(404);
            echo 'Not Found';

            return;
        }

        $routeClass = $routeInfo['class'];
        if (!class_exists($routeClass)) {
            $this->sendStatus(500);
            echo 'Route class not found';

            return;
        }

        $route = new $routeClass();
        $methodName = $routeInfo['method'];

        if (!method_exists($route, $methodName)) {
            $this->sendStatus(500);
            echo 'Route method not found';

            return;
        }

        try {
            // Get request data
            $requestData = $this->mapper->getRequestData();

            // Map parameters
            $reflection = new ReflectionClass($routeClass);
            $method = $reflection->getMethod($methodName);
            $args = $this->mapper->mapParameters($method, $requestData);

            // Call method with mapped arguments
            $plan = $route->$methodName(...$args);

            $this->sendStatus($plan->getStatus());
            $this->sendContent($plan->getContent());
        } catch (\InvalidArgumentException $e) {
            $this->sendStatus(400);
            echo 'Bad Request: ' . $e->getMessage();
        } catch (\Exception $e) {
            $this->sendStatus(500);
            echo 'Internal Server Error';
        }
    }

    public function getRoutes(): array {
        return $this->router->getRoutes();
    }

    private function sendStatus(int $code): void {
        $status = self::HTTP_STATUS[$code] ?? 'Unknown';
        header("HTTP/1.1 {$code} {$status}");
    }

    private function sendContent(Content $content): void {
        header("Content-Type: {$content->getContentType()}");

        echo $content->serialize();
    }
}
