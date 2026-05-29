<?php

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__.'/src')
    ->in(__DIR__.'/tests')
    ->exclude('var')
    ->exclude('vendor');

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@Symfony'                         => true,
        '@Symfony:risky'                   => true,
        '@PHP84Migration'                  => true,
        'declare_strict_types'             => true,
        'final_class'                      => true,
        'php_unit_method_casing'           => false,
        'global_namespace_import'          => ['import_classes' => true],
        'ordered_imports'                  => ['sort_algorithm' => 'alpha'],
    ])
    ->setFinder($finder);
