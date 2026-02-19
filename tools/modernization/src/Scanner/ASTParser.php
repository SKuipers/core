<?php

declare(strict_types=1);

namespace Gibbon\Modernization\Scanner;

use PhpParser\Error;
use PhpParser\Node;
use PhpParser\ParserFactory;
use PhpParser\Parser;

/**
 * ASTParser - Wrapper for PHP-Parser library
 * 
 * Provides methods to parse PHP files and return Abstract Syntax Trees (AST).
 * Handles parse errors gracefully with detailed error information.
 * 
 * Requirements: 1.1, 1.2
 */
class ASTParser
{
    private Parser $parser;

    public function __construct()
    {
        $this->parser = (new ParserFactory())->createForNewestSupportedVersion();
    }

    /**
     * Parse a PHP file and return its AST
     * 
     * @param string $filePath Path to the PHP file to parse
     * @return ParseResult Result containing AST nodes or error information
     */
    public function parseFile(string $filePath): ParseResult
    {
        if (!file_exists($filePath)) {
            return ParseResult::error(
                "File not found: {$filePath}",
                'FILE_NOT_FOUND'
            );
        }

        if (!is_readable($filePath)) {
            return ParseResult::error(
                "File not readable: {$filePath}",
                'FILE_NOT_READABLE'
            );
        }

        $code = file_get_contents($filePath);
        if ($code === false) {
            return ParseResult::error(
                "Failed to read file: {$filePath}",
                'FILE_READ_ERROR'
            );
        }

        return $this->parseCode($code, $filePath);
    }

    /**
     * Parse PHP code string and return its AST
     * 
     * @param string $code PHP code to parse
     * @param string|null $filename Optional filename for error reporting
     * @return ParseResult Result containing AST nodes or error information
     */
    public function parseCode(string $code, ?string $filename = null): ParseResult
    {
        try {
            $ast = $this->parser->parse($code);
            
            if ($ast === null) {
                return ParseResult::error(
                    'Parser returned null - possibly empty file',
                    'EMPTY_FILE',
                    $filename
                );
            }

            return ParseResult::success($ast, $filename);
        } catch (Error $error) {
            return ParseResult::error(
                $error->getMessage(),
                'PARSE_ERROR',
                $filename,
                $error->getStartLine()
            );
        }
    }

    /**
     * Get the underlying PHP-Parser instance
     * 
     * @return Parser
     */
    public function getParser(): Parser
    {
        return $this->parser;
    }
}
