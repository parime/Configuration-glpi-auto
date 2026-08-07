<?php

/**
 * PHP-CS-Fixer configuration for Configuration GLPI Auto plugin
 * 
 * @link https://github.com/FriendsOfPHP/PHP-CS-Fixer
 */

return (new PhpCsFixer\Config())
    ->setRules([
        // PSR-12 rules
        '@PSR12' => true,
        
        // Additional rules for GLPI compatibility
        '@PSR12:risky' => false,
        
        // Array notation
        'array_syntax' => ['syntax' => 'long'],
        
        // No trailing comma in single-line arrays
        'trailing_comma_in_multiline' => ['elements' => ['arrays', 'arguments', 'parameters']],
        
        // No trailing comma in single-line
        'trailing_comma_in_singleline' => false,
        
        // Braces
        'braces' => [
            'allow_single_line_closure' => true,
            'position_after_anonymous_constructs' => 'same',
            'position_after_control_structures' => 'same',
            'position_after_functions' => 'same',
            'position_after_oop_constructs' => 'same',
        ],
        
        // Cast spacing
        'cast_spaces' => ['spacing' => 'single'],
        
        // Class definition
        'class_definition' => ['single_line' => true],
        
        // Class attributes
        'class_attributes_separation' => ['elements' => ['method']],
        
        // Comment spacing
        'comment_to_phpdoc' => false,
        'phpdoc_to_comment' => false,
        
        // Control structure spacing
        'control_structure_braces' => true,
        'control_structure_continuation_position' => ['position' => 'same_line'],
        
        // Elseif
        'elseif' => false,
        
        // Empty loops
        'empty_loop_body' => ['style' => 'braces'],
        'empty_loop_condition' => ['style' => 'while'],
        
        // Function declaration
        'function_declaration' => ['closure_fn_spacer' => 'one'],
        'function_typehint_space' => true,
        
        // Include/require
        'include' => true,
        
        // Indentation type
        'indentation_type' => true,
        
        // Line between interface methods
        'line_between_interface_methods' => false,
        
        // Line ending
        'line_ending' => true,
        
        // List syntax
        'list_syntax' => ['syntax' => 'long'],
        
        // Method argument space
        'method_argument_space' => ['on_multiline' => 'ensure_fully_multiline', 'keep_multiple_spaces_after_comma' => false],
        
        // Method chaining indentation
        'method_chaining_indentation' => true,
        
        // Native type declaration spacing
        'native_type_declaration_spacing' => true,
        
        // New with braces
        'new_with_braces' => true,
        
        // No extra blank lines
        'no_extra_blank_lines' => [
            'tokens' => [
                'curly_brace_block',
                'extra',
                'parenthesis_brace_block',
                'square_brace_block',
                'throws',
                'use',
            ]
        ],
        
        // No leading import slash
        'no_leading_import_slash' => true,
        
        // No leading namespace whitespace
        'no_leading_namespace_whitespace' => true,
        
        // No multiline whitespace before semicolons
        'no_multiline_whitespace_before_semicolons' => false,
        
        // No singleline whitespace before semicolons
        'no_singleline_whitespace_before_semicolons' => false,
        
        // No space after function name
        'no_spaces_after_function_name' => true,
        
        // No trailing whitespace
        'no_trailing_whitespace' => true,
        
        // No unused imports
        'no_unused_imports' => true,
        
        // Object operator spacing
        'object_operator_without_whitespace' => true,
        
        // Ordered imports
        'ordered_imports' => [
            'sort_algorithm' => 'alpha',
            'imports_order' => ['const', 'class', 'function'],
        ],
        
        // Ordered class elements
        'ordered_class_elements' => [
            'order' => [
                'use_trait',
                'constant',
                'property',
                'method',
            ],
            'sort_algorithm' => 'none',
        ],
        
        // PHP opening tag
        'phpdoc_no_alias_tag' => [
            'replacements' => [
                '@link' => 'see',
                '@todo' => 'todo',
            ]
        ],
        
        // PHP closing tag
        'php_closing_tag' => false,
        
        // Return type declaration
        'return_type_declaration' => ['space_before' => 'none'],
        
        // Self accessor
        'self_accessor' => false,
        
        // Self static accessor
        'static_accessor' => false,
        
        // Single blank line before namespace
        'single_blank_line_before_namespace' => true,
        
        // Single class element per statement
        'single_class_element_per_statement' => ['elements' => ['const', 'property']],
        
        // Single import per statement
        'single_import_per_statement' => true,
        
        // Single line after imports
        'single_line_after_imports' => true,
        
        // Space after semicolon
        'space_after_semicolon' => ['remove_in_empty_for_expressions' => false],
        
        // Standardize increment
        'standardize_increment' => true,
        
        // Standardize not equals
        'standardize_not_equals' => true,
        
        // Switch case semicolon to colon
        'switch_case_semicolon_to_colon' => true,
        
        // Switch case spacing
        'switch_case_space' => true,
        
        // Ternary operator spacing
        'ternary_operator_spaces' => true,
        
        // Ternary to null coalescing
        'ternary_to_null_coalescing' => true,
        
        // Use arrow functions
        'use_arrow_functions' => false,
        
        // Visibility required
        'visibility_required' => ['elements' => ['method', 'property', 'const']],
        
        // Whitespace after comma in array
        'whitespace_after_comma_in_array' => ['ensure_style' => 'with_space'],
        
        // Yoda style
        'yoda_style' => false,
    ])
    
    ->setFinder(
        PhpCsFixer\Finder::create()
            ->in(__DIR__)
            ->exclude(['vendor', 'node_modules', 'tests', 'public', 'misc', 'front', '.github'])
            ->ignoreDotFiles(true)
            ->ignoreVCS(true)
    )
    
    ->setUsingCache(true)
    ->setCacheFile(__DIR__ . '/.php-cs-fixer.cache');
