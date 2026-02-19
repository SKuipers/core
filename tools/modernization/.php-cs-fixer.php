<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->in(__DIR__ . '/../../src')
    ->name('*.php')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true)
    ->filter(function (\SplFileInfo $file) {
        // Only process files that contain class definitions
        $content = $file->getContents();
        
        // Skip files without class, interface, trait, or enum definitions
        if (!preg_match('/^\s*(abstract\s+)?(final\s+)?(class|interface|trait|enum)\s+\w+/m', $content)) {
            return false;
        }
        
        return true;
    });

return (new Config())
    ->setRules([
        '@PSR12' => true,
        
        // ===== Class Structure =====
        
        // Preserve blank lines between class members
        'class_attributes_separation' => [
            'elements' => [
                'const' => 'one',
                'method' => 'one',
                'property' => 'one',
                'trait_import' => 'none',
            ],
        ],
        
        // ===== Array Formatting =====
        
        'array_syntax' => ['syntax' => 'short'],
        'array_indentation' => true,
        'no_multiline_whitespace_around_double_arrow' => false,
        'trim_array_spaces' => true,
        'no_whitespace_before_comma_in_array' => true,
        'whitespace_after_comma_in_array' => ['ensure_single_space' => true],
        'trailing_comma_in_multiline' => [
            'elements' => ['arrays'],
        ],
        'binary_operator_spaces' => [
            'operators' => [
                '=>' => 'align_single_space_minimal',
            ],
        ],
        
        // ===== Blank Lines =====
        
        'blank_line_after_namespace' => true,
        'blank_line_after_opening_tag' => true,
        'blank_line_before_statement' => [
            'statements' => ['return', 'if', 'for', 'foreach'],
        ],
        'no_extra_blank_lines' => [
            'tokens' => [
                'extra',
                'throw',
                'use',
            ],
        ],
        
        // ===== PHPDoc =====
        
        // Add missing @param annotations
        'phpdoc_add_missing_param_annotation' => [
            'only_untyped' => false,
        ],
        
        // Align PHPDoc tags
        'phpdoc_align' => [
            'align' => 'left',
        ],
        
        // Ensure PHPDoc is indented correctly
        'phpdoc_indent' => true,
        
        // Remove superfluous @param and @return tags
        'no_superfluous_phpdoc_tags' => false,
        
        // Ensure PHPDoc summary ends with period
        'phpdoc_summary' => false,
        
        // Trim PHPDoc whitespace
        'phpdoc_trim' => true,
        'phpdoc_trim_consecutive_blank_line_separation' => true,
        
        // ===== Type Hints =====
        
        'type_declaration_spaces' => true,
        'return_type_declaration' => ['space_before' => 'none'],
        'nullable_type_declaration_for_default_null_value' => true,
        
        // ===== Comments =====
        
        'single_line_comment_style' => false,
        'no_trailing_whitespace_in_comment' => false,
        
        // ===== Method/Function Formatting =====
        
        'method_argument_space' => [
            'on_multiline' => 'ensure_fully_multiline',
            'keep_multiple_spaces_after_comma' => false,
        ],
        
        'function_declaration' => [
            'closure_function_spacing' => 'one',
        ],
        
        // ===== Imports =====
        
        'ordered_imports' => false,
        'no_unused_imports' => true,
        'single_import_per_statement' => true,
        'single_line_after_imports' => true,
        
        // ===== Whitespace =====
        
        'no_trailing_whitespace' => true,
        'no_whitespace_in_blank_line' => true,
        'indentation_type' => true,
        
        // ===== Operators =====
        
        'concat_space' => ['spacing' => 'one'],
        'unary_operator_spaces' => true,
        
        // ===== Control Structures =====
        
        'control_structure_braces' => true,
        'control_structure_continuation_position' => ['position' => 'same_line'],
        'elseif' => true,
        'no_break_comment' => false,
        
        // ===== Casts =====
        
        'cast_spaces' => ['space' => 'single'],
        'lowercase_cast' => true,
        
        // ===== Strings =====
        
        'single_quote' => true,
        'escape_implicit_backslashes' => false,
        
        // ===== Other =====
        
        'visibility_required' => [
            'elements' => ['property', 'method', 'const'],
        ],
        'declare_strict_types' => false,
        'final_class' => false,
        'final_internal_class' => false,
        'strict_comparison' => false,
        'strict_param' => false,
        'yoda_style' => false,
        'modernize_types_casting' => false,
        'no_alias_functions' => false,
        'no_mixed_echo_print' => ['use' => 'echo'],
    ])
    ->setFinder($finder)
    ->setRiskyAllowed(true)
    ->setUsingCache(true)
    ->setCacheFile(__DIR__ . '/.php-cs-fixer.cache');
