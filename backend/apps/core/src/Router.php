<?php

declare(strict_types = 1);

namespace Nc\CoreApp;

use Nc\CoreApp\Attributes\Method;
use ReflectionClass;
use ReflectionMethod;

class Router {

    /**
     * @var array<string, array{class: class-string<Route>, method: string, httpMethod: string}>
     */
    private array $routes = [];

    public function __construct() {
        $this->discoverRoutes();
    }

    private function discoverRoutes(): void {
        $pattern = __DIR__ . '/Modules/*/Routes/*.php';
        $files = glob($pattern);

        foreach ($files as $file) {
            preg_match('#/Modules/([^/]+)/Routes/([^/]+)\.php$#', $file, $matches);
            if (!$matches) {
                continue;
            }

            $moduleName = $matches[1];
            $className = $matches[2];
            $fqcn = "Nc\\CoreApp\\Modules\\{$moduleName}\\Routes\\{$className}";
            if (!class_exists($fqcn)) {
                continue;
            }

            $reflection = new ReflectionClass($fqcn);
            $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);

            foreach ($methods as $method) {
                // skip constructor and other magic methods
                if (str_starts_with($method->getName(), '__')) {
                    continue;
                }

                $methodAttributes = $method->getAttributes(Method::class);
                if (empty($methodAttributes)) {
                    continue;
                }

                $methodAttribute = $methodAttributes[0]->newInstance();
                $httpMethod = strtoupper($methodAttribute->method);

                $methodName = $method->getName();
                $path = '/' . $methodName;

                $this->routes[$path] = [
                    'class' => $fqcn,
                    'method' => $methodName,
                    'httpMethod' => $httpMethod,
                ];
            }
        }
    }

    /**
     * @return array{class: class-string<Route>, method: string, httpMethod: string}|null
     */
    public function match(string $path, string $httpMethod): ?array {
        if (!isset($this->routes[$path])) {
            return null;
        }

        $routeInfo = $this->routes[$path];

        if ($routeInfo['httpMethod'] !== strtoupper($httpMethod)) {
            return null;
        }

        return $routeInfo;
    }

    public function getRoutes(): array {
        return $this->routes;
    }
}
