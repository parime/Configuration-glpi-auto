<?php

/**
 * PHP-CS-Fixer configuration for Configuration GLPI Auto plugin
 *
 * @link https://github.com/PHP-CS-Fixer/PHP-CS-Fixer
 */

return (new PhpCsFixer\Config())
    ->setRules([
        '@PSR12' => true,
        'array_syntax' => ['syntax' => 'short'],
        'list_syntax' => ['syntax' => 'short'],
        'no_unused_imports' => true,
        'ordered_imports' => [
            'sort_algorithm' => 'alpha',
            'imports_order' => ['const', 'class', 'function'],
        ],
        'single_import_per_statement' => true,
        'no_trailing_whitespace' => true,
        'ternary_to_null_coalescing' => true,
        'yoda_style' => false,
    ])
    ->setFinder(
        PhpCsFixer\Finder::create()
            ->in(__DIR__)
            ->exclude(['vendor', 'node_modules', 'tests', 'public', 'misc', '.github'])
            ->ignoreDotFiles(true)
            ->ignoreVCS(true)
    )
    ->setUsingCache(true)
    ->setCacheFile(__DIR__ . '/.php-cs-fixer.cache');
