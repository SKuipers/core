<?php

declare(strict_types=1);

namespace Gibbon\Modernization\TypeInference;

/**
 * MethodSignature - Represents a method's type signature
 * 
 * Contains information about method parameters and return type
 * for inheritance analysis and type compatibility checking.
 */
class MethodSignature
{
    /**
     * @param string $methodName Method name
     * @param array<array{name: string, type: string|null, isNullable: bool, hasDefault: bool}> $parameters Parameter information
     * @param string|null $returnType Return type or null if not specified
     */
    public function __construct(
        public readonly string $methodName,
        public readonly array $parameters,
        public readonly ?string $returnType
    ) {}

    /**
     * Get parameter type by name
     * 
     * @param string $paramName Parameter name (without $)
     * @return string|null Parameter type or null if not found
     */
    public function getParameterType(string $paramName): ?string
    {
        foreach ($this->parameters as $param) {
            if ($param['name'] === $paramName) {
                return $param['type'];
            }
        }
        return null;
    }

    /**
     * Check if parameter is nullable
     * 
     * @param string $paramName Parameter name (without $)
     * @return bool True if nullable
     */
    public function isParameterNullable(string $paramName): bool
    {
        foreach ($this->parameters as $param) {
            if ($param['name'] === $paramName) {
                return $param['isNullable'];
            }
        }
        return false;
    }

    /**
     * Get number of required parameters
     * 
     * @return int Number of required parameters
     */
    public function getRequiredParameterCount(): int
    {
        $count = 0;
        foreach ($this->parameters as $param) {
            if (!$param['hasDefault']) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Convert to array representation
     * 
     * @return array{methodName: string, parameters: array, returnType: string|null}
     */
    public function toArray(): array
    {
        return [
            'methodName' => $this->methodName,
            'parameters' => $this->parameters,
            'returnType' => $this->returnType,
        ];
    }

    /**
     * Get a human-readable signature string
     * 
     * @return string Signature string
     */
    public function toString(): string
    {
        $params = [];
        foreach ($this->parameters as $param) {
            $paramStr = '';
            if ($param['type'] !== null) {
                $paramStr .= $param['type'] . ' ';
            }
            $paramStr .= '$' . $param['name'];
            if ($param['hasDefault']) {
                $paramStr .= ' = ...';
            }
            $params[] = $paramStr;
        }

        $signature = $this->methodName . '(' . implode(', ', $params) . ')';
        
        if ($this->returnType !== null) {
            $signature .= ': ' . $this->returnType;
        }

        return $signature;
    }
}

