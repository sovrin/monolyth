<?php

declare(strict_types = 1);

namespace Nc\CoreApp;

use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionClass;
use ReflectionProperty;

class RequestMapper {

    /**
     * Maps request data to method parameters
     *
     * @param ReflectionMethod $method
     * @param array<string, mixed> $requestData
     * @return array<int, mixed>
     */
    public function mapParameters(ReflectionMethod $method, array $requestData): array {
        $parameters = $method->getParameters();
        $args = [];

        foreach ($parameters as $parameter) {
            $paramName = $parameter->getName();
            $paramType = $parameter->getType();

            // Map value based on type
            if ($paramType instanceof ReflectionNamedType) {
                $args[] = $this->castValue($requestData, $paramType, $parameter);
            } else {
                // No type hint, try to get by name
                if (array_key_exists($paramName, $requestData)) {
                    $args[] = $requestData[$paramName];
                } elseif ($parameter->isDefaultValueAvailable()) {
                    $args[] = $parameter->getDefaultValue();
                } else {
                    throw new \InvalidArgumentException("Missing required parameter: {$paramName}");
                }
            }
        }

        return $args;
    }

    /**
     * Cast a value to the appropriate type
     */
    private function castValue(array $requestData, ReflectionNamedType $type, ReflectionParameter $parameter): mixed {
        $typeName = $type->getName();
        $paramName = $parameter->getName();

        // Check for scalar types - look for direct parameter match
        if ($this->isScalarType($typeName)) {
            if (!array_key_exists($paramName, $requestData)) {
                if ($parameter->isDefaultValueAvailable()) {
                    return $parameter->getDefaultValue();
                }

                if ($type->allowsNull()) {
                    return null;
                }

                throw new \InvalidArgumentException("Missing required parameter: {$paramName}");
            }

            $value = $requestData[$paramName];

            if ($value === null && $type->allowsNull()) {
                return null;
            }

            return $this->castScalarValue($value, $typeName, $parameter);
        }

        // For object types, map all request data to the object
        return $this->mapToObject($requestData, $typeName, $parameter, $type);
    }

    /**
     * Check if type is scalar
     */
    private function isScalarType(string $typeName): bool {
        return in_array($typeName, ['int', 'float', 'string', 'bool', 'array'], true);
    }

    /**
     * Cast scalar value
     */
    private function castScalarValue(mixed $value, string $typeName, ReflectionParameter $parameter): mixed {
        return match ($typeName) {
            'int' => (int)$value,
            'float' => (float)$value,
            'string' => (string)$value,
            'bool' => $this->castToBool($value),
            'array' => is_array($value) ? $value : [$value],
            default => $value,
        };
    }

    /**
     * Cast value to boolean
     */
    private function castToBool(mixed $value): bool {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return match (strtolower($value)) {
                'true', '1', 'yes', 'on' => true,
                'false', '0', 'no', 'off' => false,
                default => (bool)$value,
            };
        }

        return (bool)$value;
    }

    /**
     * Map data to an object
     */
    private function mapToObject(array $requestData, string $className, ReflectionParameter $parameter, ReflectionNamedType $type): object {
        if (!class_exists($className)) {
            throw new \InvalidArgumentException(
                "Class {$className} does not exist for parameter {$parameter->getName()}"
            );
        }

        // Create new instance
        $object = new $className();
        $reflection = new ReflectionClass($className);

        // Get all public properties
        $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);

        foreach ($properties as $property) {
            $propertyName = $property->getName();

            if (!array_key_exists($propertyName, $requestData)) {
                // Skip if property has default value or is nullable
                if ($property->hasDefaultValue() || ($property->getType()?->allowsNull() ?? true)) {
                    continue;
                }

                throw new \InvalidArgumentException(
                    "Missing required property '{$propertyName}' for parameter {$parameter->getName()}"
                );
            }

            $value = $requestData[$propertyName];
            $propertyType = $property->getType();

            // Cast property value to correct type
            if ($propertyType instanceof ReflectionNamedType) {
                $propertyTypeName = $propertyType->getName();

                if ($value === null && $propertyType->allowsNull()) {
                    $property->setValue($object, null);
                    continue;
                }

                if ($this->isScalarType($propertyTypeName)) {
                    $castedValue = match ($propertyTypeName) {
                        'int' => (int)$value,
                        'float' => (float)$value,
                        'string' => (string)$value,
                        'bool' => $this->castToBool($value),
                        'array' => is_array($value) ? $value : [$value],
                        default => $value,
                    };
                    $property->setValue($object, $castedValue);
                } else {
                    // Nested object
                    if (is_array($value)) {
                        $nestedObject = $this->mapToObject($value, $propertyTypeName, $parameter, $propertyType);
                        $property->setValue($object, $nestedObject);
                    } else {
                        $property->setValue($object, $value);
                    }
                }
            } else {
                $property->setValue($object, $value);
            }
        }

        return $object;
    }

    /**
     * Extract request data from PHP globals
     *
     * @return array<string, mixed>
     */
    public function getRequestData(): array {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $data = [];

        // Merge query parameters
        $data = array_merge($data, $_GET);

        // For POST, PUT, PATCH, get body data
        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

            if (str_contains($contentType, 'application/json')) {
                $json = file_get_contents('php://input');
                $decoded = json_decode($json, true);

                if (is_array($decoded)) {
                    $data = array_merge($data, $decoded);
                }
            } else {
                // Form data
                $data = array_merge($data, $_POST);
            }
        }

        return $data;
    }
}
