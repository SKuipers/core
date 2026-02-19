<?php

declare(strict_types=1);

namespace Gibbon\Modernization\Scanner;

/**
 * FileAnalysis - Encapsulates the result of analyzing a PHP file
 * 
 * Contains identified issues including implicitly nullable parameters,
 * missing parameter type hints, missing return type hints, and missing property type hints.
 */
class FileAnalysis
{
    /**
     * @param string $filePath The file path that was analyzed
     * @param bool $success Whether analysis was successful
     * @param array<array{line: int, function: string, parameter: string}> $implicitlyNullableParams
     * @param array{parameters: array, returns: array, properties: array} $missingTypeHints
     * @param string|null $errorMessage Error message if analysis failed
     * @param string|null $errorCode Error code for categorizing errors
     */
    private function __construct(
        private readonly string $filePath,
        private readonly bool $success,
        private readonly array $implicitlyNullableParams = [],
        private readonly array $missingTypeHints = ['parameters' => [], 'returns' => [], 'properties' => []],
        private readonly ?string $errorMessage = null,
        private readonly ?string $errorCode = null
    ) {}

    /**
     * Create a successful analysis result
     * 
     * @param string $filePath The file path that was analyzed
     * @param array<array{line: int, function: string, parameter: string}> $implicitlyNullableParams
     * @param array{parameters: array, returns: array, properties: array} $missingTypeHints
     * @return self
     */
    public static function success(
        string $filePath,
        array $implicitlyNullableParams,
        array $missingTypeHints
    ): self {
        return new self(
            filePath: $filePath,
            success: true,
            implicitlyNullableParams: $implicitlyNullableParams,
            missingTypeHints: $missingTypeHints
        );
    }

    /**
     * Create an error analysis result
     * 
     * @param string $filePath The file path that was analyzed
     * @param string $errorMessage Description of the error
     * @param string $errorCode Error code for categorization
     * @return self
     */
    public static function error(
        string $filePath,
        string $errorMessage,
        string $errorCode
    ): self {
        return new self(
            filePath: $filePath,
            success: false,
            errorMessage: $errorMessage,
            errorCode: $errorCode
        );
    }

    /**
     * Check if analysis was successful
     * 
     * @return bool
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * Check if analysis failed
     * 
     * @return bool
     */
    public function isError(): bool
    {
        return !$this->success;
    }

    /**
     * Get the file path
     * 
     * @return string
     */
    public function getFilePath(): string
    {
        return $this->filePath;
    }

    /**
     * Get implicitly nullable parameters
     * 
     * @return array<array{line: int, function: string, parameter: string}>
     */
    public function getImplicitlyNullableParams(): array
    {
        return $this->implicitlyNullableParams;
    }

    /**
     * Get missing type hints
     * 
     * @return array{parameters: array, returns: array, properties: array}
     */
    public function getMissingTypeHints(): array
    {
        return $this->missingTypeHints;
    }

    /**
     * Get the error message (only available on error)
     * 
     * @return string
     * @throws \RuntimeException if called on success result
     */
    public function getErrorMessage(): string
    {
        if ($this->success) {
            throw new \RuntimeException('Cannot get error message from success result');
        }
        
        return $this->errorMessage;
    }

    /**
     * Get the error code (only available on error)
     * 
     * @return string
     * @throws \RuntimeException if called on success result
     */
    public function getErrorCode(): string
    {
        if ($this->success) {
            throw new \RuntimeException('Cannot get error code from success result');
        }
        
        return $this->errorCode;
    }

    /**
     * Get total count of all issues found
     * 
     * @return int
     */
    public function getTotalIssues(): int
    {
        if (!$this->success) {
            return 0;
        }
        
        return count($this->implicitlyNullableParams)
            + count($this->missingTypeHints['parameters'])
            + count($this->missingTypeHints['returns'])
            + count($this->missingTypeHints['properties']);
    }

    /**
     * Check if any issues were found
     * 
     * @return bool
     */
    public function hasIssues(): bool
    {
        return $this->getTotalIssues() > 0;
    }

    /**
     * Get a formatted error description
     * 
     * @return string
     */
    public function getFormattedError(): string
    {
        if ($this->success) {
            return 'No error';
        }

        return "{$this->filePath}: {$this->errorMessage} [{$this->errorCode}]";
    }
}
