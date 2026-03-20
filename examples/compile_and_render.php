<?php

declare(strict_types=1);

/**
 * Example: compile a Handlebars template and render it with data.
 *
 * Run from the lightncandy project root:
 *   php examples/compile_and_render.php
 */

require __DIR__ . '/../vendor/autoload.php';

use LightnCandy\LightnCandy;

// --- Basic variable interpolation ---
$template = 'Hello, {{name}}! You have {{count}} messages.';
$phpCode  = LightnCandy::compile($template, ['flags' => LightnCandy::FLAG_HANDLEBARS]);
$renderer = LightnCandy::prepare($phpCode);

echo $renderer(['name' => 'Alice', 'count' => 5]) . "\n";
// Hello, Alice! You have 5 messages.

// --- Conditional block ---
$template = '{{#if premium}}Welcome, premium member!{{else}}Upgrade to premium.{{/if}}';
$phpCode  = LightnCandy::compile($template, ['flags' => LightnCandy::FLAG_HANDLEBARS]);
$renderer = LightnCandy::prepare($phpCode);

echo $renderer(['premium' => true]) . "\n";  // Welcome, premium member!
echo $renderer(['premium' => false]) . "\n"; // Upgrade to premium.

// --- Each loop ---
$template = '<ul>{{#each items}}<li>{{this}}</li>{{/each}}</ul>';
$phpCode  = LightnCandy::compile($template, ['flags' => LightnCandy::FLAG_HANDLEBARS]);
$renderer = LightnCandy::prepare($phpCode);

echo $renderer(['items' => ['Apples', 'Oranges', 'Bananas']]) . "\n";

// --- Custom helper ---
$phpCode = LightnCandy::compile('{{shout name}}', [
    'flags'   => LightnCandy::FLAG_HANDLEBARS,
    'helpers' => [
        'shout' => function (string $str) {
            return strtoupper($str) . '!';
        },
    ],
]);
$renderer = LightnCandy::prepare($phpCode);
echo $renderer(['name' => 'hello']) . "\n"; // HELLO!
