<?php

declare(strict_types=1);

use LightnCandy\LightnCandy;
use PHPUnit\Framework\TestCase;

require_once('tests/helpers_for_test.php');

class usageTest extends TestCase
{
    /**
     * @dataProvider compileProvider
     */
    public function testUsedFeature($test)
    {
        LightnCandy::compile($test['template'], $test['options']);
        $context = LightnCandy::getContext();
        $this->assertEquals($test['expected'], $context['usedFeature']);
    }

    public function compileProvider()
    {
        $default = [
            'rootthis' => 0,
            'enc' => 0,
            'raw' => 0,
            'sec' => 0,
            'isec' => 0,
            'if' => 0,
            'else' => 0,
            'unless' => 0,
            'each' => 0,
            'this' => 0,
            'parent' => 0,
            'with' => 0,
            'comment' => 0,
            'partial' => 0,
            'dynpartial' => 0,
            'inlpartial' => 0,
            'helper' => 0,
            'delimiter' => 0,
            'subexp' => 0,
            'rawblock' => 0,
            'pblock' => 0,
            'lookup' => 0,
            'log' => 0,
        ];

        $compileCases = [
             [
                 'template' => 'abc',
             ],

             [
                 'template' => 'abc{{def',
             ],

             [
                 'template' => 'abc{{def}}',
                 'expected' => [
                     'enc' => 1,
                 ],
             ],

             [
                 'template' => 'abc{{{def}}}',
                 'expected' => [
                     'raw' => 1,
                 ],
             ],

             [
                 'template' => 'abc{{&def}}',
                 'expected' => [
                     'raw' => 1,
                 ],
             ],

             [
                 'template' => 'abc{{this}}',
                 'expected' => [
                     'enc' => 1,
                 ],
             ],

             [
                 'template' => 'abc{{this}}',
                 'options' => ['flags' => LightnCandy::FLAG_THIS],
                 'expected' => [
                     'enc' => 1,
                     'this' => 1,
                     'rootthis' => 1,
                 ],
             ],

             [
                 'template' => '{{#if abc}}OK!{{/if}}',
                 'expected' => [
                     'if' => 1,
                 ],
             ],

             [
                 'template' => '{{#unless abc}}OK!{{/unless}}',
                 'expected' => [
                     'unless' => 1,
                 ],
             ],

             [
                 'template' => '{{#with abc}}OK!{{/with}}',
                 'expected' => [
                     'with' => 1,
                 ],
             ],

             [
                 'template' => '{{#abc}}OK!{{/abc}}',
                 'expected' => [
                     'sec' => 1,
                 ],
             ],

             [
                 'template' => '{{^abc}}OK!{{/abc}}',
                 'expected' => [
                     'isec' => 1,
                 ],
             ],

             [
                 'template' => '{{#each abc}}OK!{{/each}}',
                 'expected' => [
                     'each' => 1,
                 ],
             ],

             [
                 'template' => '{{! test}}OK!{{! done}}',
                 'expected' => [
                     'comment' => 2,
                 ],
             ],

             [
                 'template' => '{{../OK}}',
                 'expected' => [
                     'parent' => 1,
                     'enc' => 1,
                 ],
             ],

             [
                 'template' => '{{&../../OK}}',
                 'expected' => [
                     'parent' => 1,
                     'raw' => 1,
                 ],
             ],

             [
                 'template' => '{{&../../../OK}} {{../OK}}',
                 'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                    'helpers' => [
                        'mytest' => function ($context) {
                            return $context;
                        },
                    ],
                ],
                 'expected' => [
                     'parent' => 2,
                     'enc' => 1,
                     'raw' => 1,
                 ],
             ],

             [
                 'template' => '{{mytest ../../../OK}} {{../OK}}',
                 'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                    'helpers' => [
                        'mytest' => function ($context) {
                            return $context;
                        },
                    ],
                ],
                 'expected' => [
                     'parent' => 2,
                     'enc' => 2,
                     'helper' => 1,
                 ],
             ],

             [
                 'template' => '{{mytest . .}}',
                 'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                    'helpers' => [
                        'mytest' => function ($a, $b) {
                            return '';
                        },
                    ],
                ],
                 'expected' => [
                     'rootthis' => 2,
                     'this' => 2,
                     'enc' => 1,
                     'helper' => 1,
                 ],
             ],

             [
                 'template' => '{{mytest (mytest ..)}}',
                 'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                    'helpers' => [
                        'mytest' => function ($context) {
                            return $context;
                        },
                    ],
                ],
                 'expected' => [
                     'parent' => 1,
                     'enc' => 1,
                     'helper' => 2,
                     'subexp' => 1,
                 ],
             ],

             [
                 'template' => '{{mytest (mytest ..) .}}',
                 'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                    'helpers' => [
                        'mytest' => function ($context) {
                            return $context;
                        },
                    ],
                ],
                 'expected' => [
                     'parent' => 1,
                     'rootthis' => 1,
                     'this' => 1,
                     'enc' => 1,
                     'helper' => 2,
                     'subexp' => 1,
                 ],
             ],

             [
                 'template' => '{{mytest (mytest (mytest ..)) .}}',
                 'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                    'helpers' => [
                        'mytest' => function ($context) {
                            return $context;
                        },
                    ],
                ],
                 'expected' => [
                     'parent' => 1,
                     'rootthis' => 1,
                     'this' => 1,
                     'enc' => 1,
                     'helper' => 3,
                     'subexp' => 2,
                 ],
             ],

             [
                 'id' => '134',
                 'template' => '{{#if 1}}{{keys (keys ../names)}}{{/if}}',
                 'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                    'helpers' => [
                        'keys' => function ($context) {
                            return $context;
                        },
                    ],
                ],
                 'expected' => [
                     'parent' => 1,
                     'enc' => 1,
                     'if' => 1,
                     'helper' => 2,
                     'subexp' => 1,
                 ],
             ],

             [
                 'id' => '196',
                 'template' => '{{log "this is a test"}}',
                 'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                ],
                 'expected' => [
                     'log' => 1,
                     'enc' => 1,
                 ],
             ],
        ];

        return array_map(function ($i) use ($default) {
            if (!isset($i['options'])) {
                $i['options'] = ['flags' => 0];
            }
            if (!isset($i['options']['flags'])) {
                $i['options']['flags'] = 0;
            }
            $i['expected'] = array_merge($default, isset($i['expected']) ? $i['expected'] : []);
            return [$i];
        }, $compileCases);
    }
}
