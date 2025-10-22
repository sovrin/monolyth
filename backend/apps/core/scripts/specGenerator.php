<?php

declare(strict_types = 1);

include __DIR__ . '/../vendor/autoload.php';

use Nc\CoreApp\Attributes\Method;
use Nc\CoreApp\Attributes\Path;
use Nc\CoreApp\Property;
use Nc\CoreApp\Response;
use Nc\CoreApp\Content;
use Nc\CoreApp\Route;
use Nc\CoreApp\Utilities\GenericArray;

class OpenApiGenerator {

    private array $spec = [
        'openapi' => '3.0.0',
        'servers' => [
            ['url' => 'http://localhost:8000/',],
        ],
        'info' => [
            'title' => 'API Documentation',
            'version' => '1.0.0',
        ],
        'paths' => [],
        'components' => [
            'schemas' => [],
            'responses' => [],
        ],
    ];

    public function generateFromResponse(string $responseClass): void {
        if (!class_exists($responseClass) || !is_subclass_of($responseClass, Response::class)) {
            throw new InvalidArgumentException("$responseClass must extend Response");
        }

        $reflection = new ReflectionClass($responseClass);

        // Read from static constants
        $status = $this->getConstant($reflection, 'STATUS') ?? 0;
        $description = $this->getConstant($reflection, 'DESCRIPTION') ?? '';
        $contentClass = $this->getConstant($reflection, 'CONTENT');

        $responseName = $reflection->getShortName();
        $responseSpec = [
            'description' => $description,
        ];

        // If content type is specified, generate schema
        if ($contentClass && class_exists($contentClass) && is_subclass_of($contentClass, Content::class)) {
            $schemaName = (new ReflectionClass($contentClass))->getShortName();

            // Generate schema for content
            $this->generateSchema($contentClass, $schemaName);

            $responseSpec['content'] = [
                'application/json' => [
                    'schema' => [
                        '$ref' => "#/components/schemas/$schemaName",
                    ],
                ],
            ];
        }

        $this->spec['components']['responses'][$responseName] = $responseSpec;
    }

    private function generateSchema(string $contentClass, string $schemaName): void {
        if (isset($this->spec['components']['schemas'][$schemaName])) {
            return; // Already generated
        }

        $reflection = new ReflectionClass($contentClass);
        $properties = [];
        $required = [];

        // Check if class defines schema via constants
        $schemaProperties = $this->getConstant($reflection, 'SCHEMA_PROPERTIES');

        if ($schemaProperties && is_array($schemaProperties)) {
            // Use explicitly defined schema
            foreach ($schemaProperties as $propertyName => $propertySpec) {
                if (is_string($propertySpec)) {
                    // Simple type string like 'string', 'integer', etc.
                    $properties[$propertyName] = ['type' => $propertySpec];
                } elseif (is_array($propertySpec)) {
                    // Full spec with type, format, example, etc.
                    $properties[$propertyName] = $propertySpec;

                    if (isset($propertySpec['required']) && $propertySpec['required']) {
                        $required[] = $propertyName;
                        unset($properties[$propertyName]['required']);
                    }
                }
            }
        } else {
            // Fallback: Analyze public properties
            foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
                $propertyName = $property->getName();
                $propertyType = $property->getType();

                $propertySpec = $this->inferPropertyType($propertyType);

                // Check if property has default value or is nullable
                if ($propertyType && !$propertyType->allowsNull() && !$property->hasDefaultValue()) {
                    $required[] = $propertyName;
                }

                $properties[$propertyName] = $propertySpec;
            }
        }

        $schema = [
            'type' => 'object',
            'properties' => $properties,
        ];

        if (!empty($required)) {
            $schema['required'] = $required;
        }

        $this->spec['components']['schemas'][$schemaName] = $schema;
    }

    private function generateProperty(Property | string $typeName, string $shortName) {
        $reflection = new ReflectionClass($typeName);
        $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);

        $spec = [];

        foreach ($properties as $property) {
            $type = $property->getType();

            $spec[$property->getName()] = $this->inferPropertyType($type);
        }

        return [
            'properties' => $spec,
        ];
    }

    public function generateFromRoute(string $routeClass): void {
        if (!class_exists($routeClass) || !is_subclass_of($routeClass, Route::class)) {
            return;
        }

        $reflection = new ReflectionClass($routeClass);

        // Look for methods with Method attribute
        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            // Skip methods without Method attribute
            $methodAttributes = $method->getAttributes(Method::class);
            if (empty($methodAttributes)) {
                continue;
            }

            $methodAttr = $methodAttributes[0]->newInstance();
            $httpMethod = strtolower($methodAttr->method);

            // Use method name as path
            $path = '/' . $method->getName();

            // Get return type (response)
            $returnType = $method->getReturnType();
            if (!$returnType instanceof ReflectionNamedType) {
                continue;
            }

            $responseClass = $returnType->getName();

            if (!class_exists($responseClass) || !is_subclass_of($responseClass, Response::class)) {
                continue;
            }

            // Generate response if not already done
            $this->generateFromResponse($responseClass);

            $responseReflection = new ReflectionClass($responseClass);
            $responseName = $responseReflection->getShortName();
            $status = $this->getConstant($responseReflection, 'STATUS') ?? 200;

            // Build operation spec
            $operation = [
                'summary' => $method->getName(),
                'responses' => [
                    (string) $status => [
                        '$ref' => "#/components/responses/$responseName",
                    ],
                ],
            ];

            // Generate request body from method parameters
            $parameters = $method->getParameters();
            if (!empty($parameters)) {
                $requestBodySpec = $this->generateRequestBodyFromParameters($parameters, $httpMethod);

                if ($requestBodySpec !== null) {
                    if (isset($requestBodySpec['parameters'])) {
                        $operation['parameters'] = $requestBodySpec['parameters'];
                    }

                    if (isset($requestBodySpec['requestBody'])) {
                        $operation['requestBody'] = $requestBodySpec['requestBody'];
                    }
                }
            }

            // Add to paths
            if (!isset($this->spec['paths'][$path])) {
                $this->spec['paths'][$path] = [];
            }

            $this->spec['paths'][$path][$httpMethod] = $operation;
        }
    }

    private function generateRequestBodyFromParameters(array $parameters, string $httpMethod): ?array {
        if (empty($parameters)) {
            return null;
        }

        $result = [];

        // For GET requests, use query parameters
        // For POST/PUT/PATCH/DELETE, use request body
        $useQueryParams = in_array($httpMethod, ['get', 'delete']);

        foreach ($parameters as $parameter) {
            $paramType = $parameter->getType();

            if (!$paramType instanceof ReflectionNamedType) {
                continue;
            }

            $typeName = $paramType->getName();

            // Check if it's a scalar type - add as query parameter
            if (in_array($typeName, ['int', 'float', 'string', 'bool'])) {
                if ($useQueryParams) {
                    $result['parameters'][] = [
                        'name' => $parameter->getName(),
                        'in' => 'query',
                        'required' => !$parameter->isOptional(),
                        'schema' => $this->getScalarTypeSpec($typeName),
                    ];
                }
            } elseif (class_exists($typeName)) {
                // It's an object - generate schema from its properties
                $schemaName = (new ReflectionClass($typeName))->getShortName();
                $this->generateSchemaFromClass($typeName, $schemaName);

                if ($useQueryParams) {
                    // For GET, extract properties as individual query parameters
                    $classReflection = new ReflectionClass($typeName);
                    $properties = $classReflection->getProperties(ReflectionProperty::IS_PUBLIC);

                    foreach ($properties as $property) {
                        $propertyType = $property->getType();
                        $isRequired = $propertyType && !$propertyType->allowsNull() && !$property->hasDefaultValue();

                        $result['parameters'][] = [
                            'name' => $property->getName(),
                            'in' => 'query',
                            'required' => $isRequired,
                            'schema' => $this->inferPropertyType($propertyType),
                        ];
                    }
                } else {
                    // For POST/PUT/PATCH, use request body
                    $result['requestBody'] = [
                        'required' => !$parameter->isOptional(),
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    '$ref' => "#/components/schemas/$schemaName",
                                ],
                            ],
                        ],
                    ];
                }
            }
        }

        return !empty($result) ? $result : null;
    }

    private function generateSchemaFromClass(string $className, string $schemaName): void {
        if (isset($this->spec['components']['schemas'][$schemaName])) {
            return; // Already generated
        }

        $reflection = new ReflectionClass($className);
        $properties = [];
        $required = [];

        // Analyze public properties
        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $propertyName = $property->getName();
            $propertyType = $property->getType();

            $propertySpec = $this->inferPropertyType($propertyType);

            // Check if property is required
            if ($propertyType && !$propertyType->allowsNull() && !$property->hasDefaultValue()) {
                $required[] = $propertyName;
            }

            $properties[$propertyName] = $propertySpec;
        }

        $schema = [
            'type' => 'object',
            'properties' => $properties,
        ];

        if (!empty($required)) {
            $schema['required'] = $required;
        }

        $this->spec['components']['schemas'][$schemaName] = $schema;
    }

    private function getScalarTypeSpec(string $typeName): array {
        return match ($typeName) {
            'int' => ['type' => 'integer'],
            'float' => ['type' => 'number', 'format' => 'float'],
            'bool' => ['type' => 'boolean'],
            'string' => ['type' => 'string'],
            default => ['type' => 'string'],
        };
    }

    private function inferPropertyType(?ReflectionType $type): array {
        if (!$type) {
            return ['type' => 'string'];
        }

        if ($type instanceof ReflectionNamedType) {
            $typeName = $type->getName();

            if ($spec = $this->getScalarTypeSpec($typeName)) {
                if ($type->allowsNull()) {
                    $spec['nullable'] = true;
                }

                return $spec;
            }

            // For custom classes, create a reference
            if (class_exists($typeName)) {
                $reflection = (new ReflectionClass($typeName));
                $shortName = $reflection->getShortName();

                if (is_subclass_of($typeName, Property::class)) {
                    return $this->generateProperty($typeName, $shortName);
                } elseif (is_subclass_of($typeName, GenericArray::class)) {
                    $type = $this->getConstant($reflection, 'TYPE');

                    return ['type' => 'array', 'items' => ['type' => $type]];
                }
            }
        }

        return ['type' => 'string'];
    }

    private function getConstant(ReflectionClass $reflection, string $constantName): mixed {
        if ($reflection->hasConstant($constantName)) {
            return $reflection->getConstant($constantName);
        }

        return null;
    }

    public function getSpec(): array {
        return $this->spec;
    }

    public function saveToFile(string $filename): void {
        file_put_contents($filename, json_encode($this->spec, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    public function scanDirectory(string $directory, string $namespace): void {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $relativePath = str_replace($directory . DIRECTORY_SEPARATOR, '', $file->getPathname());
                $className = $namespace . '\\' . str_replace(['/', '.php'], ['\\', ''], $relativePath);

                if (class_exists($className)) {
                    if (is_subclass_of($className, Response::class)) {
                        $this->generateFromResponse($className);
                    } elseif (is_subclass_of($className, Route::class)) {
                        $this->generateFromRoute($className);
                    }
                }
            }
        }
    }
}

// Usage:
$generator = new OpenApiGenerator();

// Single response
//$generator->generateFromResponse(\Nc\CoreApp\Modules\Login\Responses\SuccessfulResponse::class);

// Or scan entire directory
$generator->scanDirectory(__DIR__ . '/../src/Modules', 'Nc\\CoreApp\\Modules');

// Output
//$json = json_encode($generator->getSpec(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

// Or save to file
$generator->saveToFile('openapi.json');
