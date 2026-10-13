<?php

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__)
    ->exclude([
        '.phpunit.cache',
        'vendor',
        'uploads',
    ])
    ->notName('adminer.php');

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(false)
    ->setRules([
        'full_opening_tag' => true,
        'indentation_type' => true,
        'line_ending' => true,
        'no_trailing_whitespace' => true,
        'no_whitespace_in_blank_line' => true,
        'single_blank_line_at_eof' => true,
        'trailing_comma_in_multiline' => true,
    ])
    ->setFinder($finder);
