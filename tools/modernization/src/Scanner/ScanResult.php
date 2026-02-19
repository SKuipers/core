<?php

declare(strict_types=1);

namespace Gibbon\Modernization\Scanner;

/**
 * ScanResult - Encapsulates the result of scanning a directory
 * 
 * Contains all findings from scanning PHP files including implicitly nullable parameters,
 * missing parameter type hints, missing return type hints, and missing property type hints.
 * 
 * Requirements: 1.1, 1.2, 1.3, 1.4, 1.5
 */
class ScanResult
{
    /**
     * @param array<array{file: string, line: int, function: string, parameter: string}> $implicitlyNullableParams
     * @param array<array{file: string, line: int, function?: string, method?: string, class?: string, parameter: string}> $missingParameterTypes
     * @param array<array{file: string, line: int, function?: string, method?: string, class?: string}> $missingReturnTypes
     * @param array<array{file: string, line: int, class: string, property: string}> $missingPropertyTypes
     * @param int $filesScanned Total number of files scanned
     * @param int $issuesFound Total number of issues found
     * @param array<string> $errors List of errors encountered during scanning
     */
    public function __construct(
        public readonly array $implicitlyNullableParams,
        public readonly array $missingParameterTypes,
        public readonly array $missingReturnTypes,
        public readonly array $missingPropertyTypes,
        public readonly int $filesScanned,
        public readonly int $issuesFound,
        public readonly array $errors = []
    ) {}

    /**
     * Group all findings by directory
     * 
     * @return array<string, array{implicitlyNullable: array, missingParameters: array, missingReturns: array, missingProperties: array}>
     */
    public function groupByDirectory(): array
    {
        $grouped = [];

        // Group implicitly nullable parameters
        foreach ($this->implicitlyNullableParams as $issue) {
            $dir = dirname($issue['file']);
            if (!isset($grouped[$dir])) {
                $grouped[$dir] = [
                    'implicitlyNullable' => [],
                    'missingParameters' => [],
                    'missingReturns' => [],
                    'missingProperties' => [],
                ];
            }
            $grouped[$dir]['implicitlyNullable'][] = $issue;
        }

        // Group missing parameter types
        foreach ($this->missingParameterTypes as $issue) {
            $dir = dirname($issue['file']);
            if (!isset($grouped[$dir])) {
                $grouped[$dir] = [
                    'implicitlyNullable' => [],
                    'missingParameters' => [],
                    'missingReturns' => [],
                    'missingProperties' => [],
                ];
            }
            $grouped[$dir]['missingParameters'][] = $issue;
        }

        // Group missing return types
        foreach ($this->missingReturnTypes as $issue) {
            $dir = dirname($issue['file']);
            if (!isset($grouped[$dir])) {
                $grouped[$dir] = [
                    'implicitlyNullable' => [],
                    'missingParameters' => [],
                    'missingReturns' => [],
                    'missingProperties' => [],
                ];
            }
            $grouped[$dir]['missingReturns'][] = $issue;
        }

        // Group missing property types
        foreach ($this->missingPropertyTypes as $issue) {
            $dir = dirname($issue['file']);
            if (!isset($grouped[$dir])) {
                $grouped[$dir] = [
                    'implicitlyNullable' => [],
                    'missingParameters' => [],
                    'missingReturns' => [],
                    'missingProperties' => [],
                ];
            }
            $grouped[$dir]['missingProperties'][] = $issue;
        }

        return $grouped;
    }

    /**
     * Convert scan result to array format
     * 
     * @return array{implicitlyNullableParams: array, missingParameterTypes: array, missingReturnTypes: array, missingPropertyTypes: array, filesScanned: int, issuesFound: int, errors: array}
     */
    public function toArray(): array
    {
        return [
            'implicitlyNullableParams' => $this->implicitlyNullableParams,
            'missingParameterTypes' => $this->missingParameterTypes,
            'missingReturnTypes' => $this->missingReturnTypes,
            'missingPropertyTypes' => $this->missingPropertyTypes,
            'filesScanned' => $this->filesScanned,
            'issuesFound' => $this->issuesFound,
            'errors' => $this->errors,
        ];
    }
}
