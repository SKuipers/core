<?php

declare(strict_types=1);

namespace Gibbon\Modernization\Scanner;

/**
 * ReportGenerator - Generates formatted reports from scan results
 * 
 * Supports multiple output formats: console, JSON, and HTML
 * 
 * Requirements: 1.3
 */
class ReportGenerator
{
    /**
     * Generate a console-formatted report
     */
    public function generateConsoleReport(ScanResult $result): string
    {
        $output = [];
        $output[] = "=== PHP 8.4 Modernization Scan Report ===\n";
        $output[] = sprintf("Files Scanned: %d", $result->filesScanned);
        $output[] = sprintf("Total Issues Found: %d\n", $result->issuesFound);

        // Group by directory
        $grouped = $result->groupByDirectory();

        if (empty($grouped)) {
            $output[] = "\nNo issues found! 🎉";
        } else {
            // Sort directories for consistent output
            ksort($grouped);
        }

        foreach ($grouped as $directory => $issues) {
            $dirIssueCount = count($issues['implicitlyNullable']) + 
                           count($issues['missingParameters']) + 
                           count($issues['missingReturns']) + 
                           count($issues['missingProperties']);
            
            if ($dirIssueCount === 0) {
                continue;
            }

            $output[] = "\n" . str_repeat("=", 60);
            $output[] = sprintf("Directory: %s (%d issues)", $directory, $dirIssueCount);
            $output[] = str_repeat("=", 60);

            // Implicitly nullable parameters
            if (!empty($issues['implicitlyNullable'])) {
                $output[] = "\n--- Implicitly Nullable Parameters ---";
                foreach ($issues['implicitlyNullable'] as $issue) {
                    $output[] = sprintf(
                        "  %s:%d - %s(\$%s)",
                        basename($issue['file']),
                        $issue['line'],
                        $issue['function'],
                        $issue['parameter']
                    );
                }
            }

            // Missing parameter types
            if (!empty($issues['missingParameters'])) {
                $output[] = "\n--- Missing Parameter Type Hints ---";
                foreach ($issues['missingParameters'] as $issue) {
                    $location = $this->formatLocation($issue);
                    $output[] = sprintf(
                        "  %s:%d - %s(\$%s)",
                        basename($issue['file']),
                        $issue['line'],
                        $location,
                        $issue['parameter']
                    );
                }
            }

            // Missing return types
            if (!empty($issues['missingReturns'])) {
                $output[] = "\n--- Missing Return Type Hints ---";
                foreach ($issues['missingReturns'] as $issue) {
                    $location = $this->formatLocation($issue);
                    $output[] = sprintf(
                        "  %s:%d - %s",
                        basename($issue['file']),
                        $issue['line'],
                        $location
                    );
                }
            }

            // Missing property types
            if (!empty($issues['missingProperties'])) {
                $output[] = "\n--- Missing Property Type Hints ---";
                foreach ($issues['missingProperties'] as $issue) {
                    $output[] = sprintf(
                        "  %s:%d - %s::\$%s",
                        basename($issue['file']),
                        $issue['line'],
                        $issue['class'],
                        $issue['property']
                    );
                }
            }
        }

        // Errors section
        if (!empty($result->errors)) {
            $output[] = "\n" . str_repeat("=", 60);
            $output[] = sprintf("Errors Encountered: %d", count($result->errors));
            $output[] = str_repeat("=", 60);
            foreach ($result->errors as $error) {
                $output[] = "  - " . $error;
            }
        }

        $output[] = "\n" . str_repeat("=", 60);
        $output[] = "End of Report";
        $output[] = str_repeat("=", 60);

        return implode("\n", $output);
    }

    /**
     * Generate a JSON-formatted report
     */
    public function generateJsonReport(ScanResult $result): string
    {
        $data = [
            'summary' => [
                'filesScanned' => $result->filesScanned,
                'issuesFound' => $result->issuesFound,
                'errorCount' => count($result->errors),
            ],
            'issues' => [
                'implicitlyNullableParams' => $result->implicitlyNullableParams,
                'missingParameterTypes' => $result->missingParameterTypes,
                'missingReturnTypes' => $result->missingReturnTypes,
                'missingPropertyTypes' => $result->missingPropertyTypes,
            ],
            'byDirectory' => $result->groupByDirectory(),
            'errors' => $result->errors,
        ];

        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Generate an HTML-formatted report
     */
    public function generateHtmlReport(ScanResult $result): string
    {
        $html = [];
        $html[] = '<!DOCTYPE html>';
        $html[] = '<html lang="en">';
        $html[] = '<head>';
        $html[] = '    <meta charset="UTF-8">';
        $html[] = '    <meta name="viewport" content="width=device-width, initial-scale=1.0">';
        $html[] = '    <title>PHP 8.4 Modernization Scan Report</title>';
        $html[] = '    <style>';
        $html[] = '        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 20px; background: #f5f5f5; }';
        $html[] = '        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }';
        $html[] = '        h1 { color: #333; border-bottom: 3px solid #4CAF50; padding-bottom: 10px; }';
        $html[] = '        h2 { color: #555; margin-top: 30px; border-bottom: 2px solid #ddd; padding-bottom: 8px; }';
        $html[] = '        h3 { color: #666; margin-top: 20px; font-size: 1.1em; }';
        $html[] = '        .summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0; }';
        $html[] = '        .summary-card { background: #f9f9f9; padding: 20px; border-radius: 6px; border-left: 4px solid #4CAF50; }';
        $html[] = '        .summary-card.warning { border-left-color: #ff9800; }';
        $html[] = '        .summary-card.error { border-left-color: #f44336; }';
        $html[] = '        .summary-label { font-size: 0.9em; color: #666; text-transform: uppercase; }';
        $html[] = '        .summary-value { font-size: 2em; font-weight: bold; color: #333; margin-top: 5px; }';
        $html[] = '        .directory { background: #fafafa; padding: 15px; margin: 15px 0; border-radius: 6px; border: 1px solid #e0e0e0; }';
        $html[] = '        .directory-header { font-weight: bold; color: #333; margin-bottom: 10px; font-size: 1.1em; }';
        $html[] = '        .issue-list { list-style: none; padding: 0; margin: 10px 0; }';
        $html[] = '        .issue-item { padding: 8px 12px; margin: 5px 0; background: white; border-left: 3px solid #2196F3; border-radius: 3px; font-family: "Courier New", monospace; font-size: 0.9em; }';
        $html[] = '        .issue-item.nullable { border-left-color: #ff9800; }';
        $html[] = '        .issue-item.param { border-left-color: #2196F3; }';
        $html[] = '        .issue-item.return { border-left-color: #9C27B0; }';
        $html[] = '        .issue-item.property { border-left-color: #4CAF50; }';
        $html[] = '        .file-info { color: #666; }';
        $html[] = '        .line-number { color: #999; }';
        $html[] = '        .function-name { color: #1976D2; font-weight: 500; }';
        $html[] = '        .parameter-name { color: #C62828; }';
        $html[] = '        .error-section { background: #ffebee; padding: 15px; border-radius: 6px; border-left: 4px solid #f44336; margin: 20px 0; }';
        $html[] = '        .error-list { list-style: none; padding: 0; }';
        $html[] = '        .error-item { padding: 5px 0; color: #c62828; }';
        $html[] = '        .no-issues { text-align: center; padding: 40px; color: #4CAF50; font-size: 1.2em; }';
        $html[] = '    </style>';
        $html[] = '</head>';
        $html[] = '<body>';
        $html[] = '    <div class="container">';
        $html[] = '        <h1>PHP 8.4 Modernization Scan Report</h1>';
        
        // Summary section
        $html[] = '        <div class="summary">';
        $html[] = sprintf('            <div class="summary-card"><div class="summary-label">Files Scanned</div><div class="summary-value">%d</div></div>', $result->filesScanned);
        $html[] = sprintf('            <div class="summary-card warning"><div class="summary-label">Issues Found</div><div class="summary-value">%d</div></div>', $result->issuesFound);
        if (!empty($result->errors)) {
            $html[] = sprintf('            <div class="summary-card error"><div class="summary-label">Errors</div><div class="summary-value">%d</div></div>', count($result->errors));
        }
        $html[] = '        </div>';

        // Group by directory
        $grouped = $result->groupByDirectory();

        if (empty($grouped)) {
            $html[] = '        <div class="no-issues">✓ No issues found! Your code is ready for PHP 8.4!</div>';
        } else {
            $html[] = '        <h2>Issues by Directory</h2>';
            
            // Sort directories
            ksort($grouped);

            foreach ($grouped as $directory => $issues) {
                $dirIssueCount = count($issues['implicitlyNullable']) + 
                               count($issues['missingParameters']) + 
                               count($issues['missingReturns']) + 
                               count($issues['missingProperties']);
                
                if ($dirIssueCount === 0) {
                    continue;
                }

                $html[] = '        <div class="directory">';
                $html[] = sprintf('            <div class="directory-header">%s <span style="color: #999;">(%d issues)</span></div>', htmlspecialchars($directory), $dirIssueCount);

                // Implicitly nullable parameters
                if (!empty($issues['implicitlyNullable'])) {
                    $html[] = '            <h3>Implicitly Nullable Parameters</h3>';
                    $html[] = '            <ul class="issue-list">';
                    foreach ($issues['implicitlyNullable'] as $issue) {
                        $html[] = sprintf(
                            '                <li class="issue-item nullable"><span class="file-info">%s</span>:<span class="line-number">%d</span> - <span class="function-name">%s</span>(<span class="parameter-name">$%s</span>)</li>',
                            htmlspecialchars(basename($issue['file'])),
                            $issue['line'],
                            htmlspecialchars($issue['function']),
                            htmlspecialchars($issue['parameter'])
                        );
                    }
                    $html[] = '            </ul>';
                }

                // Missing parameter types
                if (!empty($issues['missingParameters'])) {
                    $html[] = '            <h3>Missing Parameter Type Hints</h3>';
                    $html[] = '            <ul class="issue-list">';
                    foreach ($issues['missingParameters'] as $issue) {
                        $location = $this->formatLocation($issue);
                        $html[] = sprintf(
                            '                <li class="issue-item param"><span class="file-info">%s</span>:<span class="line-number">%d</span> - <span class="function-name">%s</span>(<span class="parameter-name">$%s</span>)</li>',
                            htmlspecialchars(basename($issue['file'])),
                            $issue['line'],
                            htmlspecialchars($location),
                            htmlspecialchars($issue['parameter'])
                        );
                    }
                    $html[] = '            </ul>';
                }

                // Missing return types
                if (!empty($issues['missingReturns'])) {
                    $html[] = '            <h3>Missing Return Type Hints</h3>';
                    $html[] = '            <ul class="issue-list">';
                    foreach ($issues['missingReturns'] as $issue) {
                        $location = $this->formatLocation($issue);
                        $html[] = sprintf(
                            '                <li class="issue-item return"><span class="file-info">%s</span>:<span class="line-number">%d</span> - <span class="function-name">%s</span></li>',
                            htmlspecialchars(basename($issue['file'])),
                            $issue['line'],
                            htmlspecialchars($location)
                        );
                    }
                    $html[] = '            </ul>';
                }

                // Missing property types
                if (!empty($issues['missingProperties'])) {
                    $html[] = '            <h3>Missing Property Type Hints</h3>';
                    $html[] = '            <ul class="issue-list">';
                    foreach ($issues['missingProperties'] as $issue) {
                        $html[] = sprintf(
                            '                <li class="issue-item property"><span class="file-info">%s</span>:<span class="line-number">%d</span> - <span class="function-name">%s</span>::<span class="parameter-name">$%s</span></li>',
                            htmlspecialchars(basename($issue['file'])),
                            $issue['line'],
                            htmlspecialchars($issue['class']),
                            htmlspecialchars($issue['property'])
                        );
                    }
                    $html[] = '            </ul>';
                }

                $html[] = '        </div>';
            }
        }

        // Errors section
        if (!empty($result->errors)) {
            $html[] = '        <div class="error-section">';
            $html[] = sprintf('            <h2>Errors Encountered (%d)</h2>', count($result->errors));
            $html[] = '            <ul class="error-list">';
            foreach ($result->errors as $error) {
                $html[] = sprintf('                <li class="error-item">%s</li>', htmlspecialchars($error));
            }
            $html[] = '            </ul>';
            $html[] = '        </div>';
        }

        $html[] = '    </div>';
        $html[] = '</body>';
        $html[] = '</html>';

        return implode("\n", $html);
    }

    /**
     * Format location string for an issue (function, method, or class context)
     */
    private function formatLocation(array $issue): string
    {
        if (isset($issue['method']) && isset($issue['class'])) {
            return $issue['class'] . '::' . $issue['method'];
        }
        if (isset($issue['function'])) {
            return $issue['function'];
        }
        if (isset($issue['class'])) {
            return $issue['class'];
        }
        return 'unknown';
    }
}
