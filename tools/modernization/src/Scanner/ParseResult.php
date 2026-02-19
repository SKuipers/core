<?php

declare(strict_types=1);

namespace Gibbon\Modernization\Scanner;

use PhpParser\Node;

/**
 * ParseResult - Encapsulates the result of parsing a PHP file
 * 
 * Contains either successful AST nodes or error information.
 * Provides a type-safe way to handle parse results.
 */
class ParseResult
{
    /**
     * @param array<Node>|null $ast The parsed AST nodes (null on error)
     * @param string|null $originalCode The original source code
     * @param array|null $tokens The lexer tokens for format preservation
     * @param bool $success Whether parsing was successful
     * @param string|null $errorMessage Error message if parsing failed
     * @param string|null $errorCode Error code for categorizing errors
     * @param string|null $filename The filename being parsed (for error reporting)
     * @param int|null $errorLine Line number where error occurred
     */
    private function __construct(
        private readonly ?array $ast,
        private readonly ?string $originalCode,
        private readonly ?array $tokens,
        private readonly bool $success,
        private readonly ?string $errorMessage = null,
        private readonly ?string $errorCode = null,
        private readonly ?string $filename = null,
        private readonly ?int $errorLine = null
    ) {}

    /**
     * Create a successful parse result
     * 
     * @param array<Node> $ast The parsed AST nodes
     * @param string $originalCode The original source code
     * @param array $tokens The lexer tokens
     * @param string|null $filename Optional filename for context
     * @return self
     */
    public static function success(array $ast, string $originalCode, array $tokens, ?string $filename = null): self
    {
        return new self(
            ast: $ast,
            originalCode: $originalCode,
            tokens: $tokens,
            success: true,
            filename: $filename
        );
    }

    /**
     * Create an error parse result
     * 
     * @param string $errorMessage Description of the error
     * @param string $errorCode Error code for categorization
     * @param string|null $filename Optional filename where error occurred
     * @param int|null $errorLine Optional line number where error occurred
     * @return self
     */
    public static function error(
        string $errorMessage,
        string $errorCode,
        ?string $filename = null,
        ?int $errorLine = null
    ): self {
        return new self(
            ast: null,
            originalCode: null,
            tokens: null,
            success: false,
            errorMessage: $errorMessage,
            errorCode: $errorCode,
            filename: $filename,
            errorLine: $errorLine
        );
    }

    /**
     * Check if parsing was successful
     * 
     * @return bool
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * Check if parsing failed
     * 
     * @return bool
     */
    public function isError(): bool
    {
        return !$this->success;
    }

    /**
     * Get the AST nodes (only available on success)
     * 
     * @return array<Node>
     * @throws \RuntimeException if called on error result
     */
    public function getAst(): array
    {
        if (!$this->success) {
            throw new \RuntimeException('Cannot get AST from error result');
        }
        
        return $this->ast;
    }

    /**
     * Get the original source code (only available on success)
     * 
     * @return string
     * @throws \RuntimeException if called on error result
     */
    public function getOriginalCode(): string
    {
        if (!$this->success) {
            throw new \RuntimeException('Cannot get original code from error result');
        }
        
        return $this->originalCode;
    }

    /**
     * Get the lexer tokens (only available on success)
     * 
     * @return array
     * @throws \RuntimeException if called on error result
     */
    public function getTokens(): array
    {
        if (!$this->success) {
            throw new \RuntimeException('Cannot get tokens from error result');
        }
        
        return $this->tokens;
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
     * Get the filename (available on both success and error)
     * 
     * @return string|null
     */
    public function getFilename(): ?string
    {
        return $this->filename;
    }

    /**
     * Get the error line number (only available on parse errors)
     * 
     * @return int|null
     */
    public function getErrorLine(): ?int
    {
        return $this->errorLine;
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

        $parts = [];
        
        if ($this->filename !== null) {
            $parts[] = $this->filename;
        }
        
        if ($this->errorLine !== null) {
            $parts[] = "line {$this->errorLine}";
        }
        
        $location = !empty($parts) ? implode(':', $parts) . ': ' : '';
        
        return "{$location}{$this->errorMessage} [{$this->errorCode}]";
    }
}
