<?php

declare(strict_types=1);

use LightnCandy\LightnCandy;
use LightnCandy\Runtime;
use PHPUnit\Framework\TestCase;

require_once('tests/helpers_for_test.php');

$tmpdir = sys_get_temp_dir();

class regressionTest extends TestCase
{
    /**
     * @dataProvider issueProvider
     */
    public function testIssues($issue)
    {
        global $tmpdir;

        $php = LightnCandy::compile($issue['template'], isset($issue['options']) ? $issue['options'] : null);
        $context = LightnCandy::getContext();
        $parsed = print_r(LightnCandy::$lastParsed, true);
        if (count($context['error'])) {
            $this->fail('Compile failed due to: ' . print_r($context['error'], true) . "\nPARSED: $parsed");
        }
        $renderer = LightnCandy::prepare($php);

        $this->assertEquals($issue['expected'], $renderer(isset($issue['data']) ? $issue['data'] : null, ['debug' => $issue['debug']]), "PHP CODE:\n$php\n$parsed");
    }

    public function issueProvider()
    {
        $test_helpers = ['ouch' => function () {
            return 'ok';
        }];

        $test_helpers2 = ['ouch' => function () {
            return 'wa!';
        }];

        $test_helpers3 = ['ouch' => function () {
            return 'wa!';
        }, 'god' => function () {
            return 'yo';
        }];

        $issues = [
            [
                'id' => 39,
                'template' => '{{{tt}}}',
                'data' => ['tt' => 'bla bla bla'],
                'expected' => 'bla bla bla',
            ],

            [
                'id' => 44,
                'template' => '<div class="terms-text"> {{render "artists-terms"}} </div>',
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_ERROR_LOG | LightnCandy::FLAG_EXTHELPER,
                    'helpers' => [
                        'url',
                        'render' => function ($view, $data = []) {
                            return 'OK!';
                        },
                    ],
                ],
                'data' => ['tt' => 'bla bla bla'],
                'expected' => '<div class="terms-text"> OK! </div>',
            ],

            [
                'id' => 45,
                'template' => '{{{a.b.c}}}, {{a.b.bar}}, {{a.b.prop}}',
                'options' => [
                    'flags' => LightnCandy::FLAG_ERROR_LOG | LightnCandy::FLAG_INSTANCE | LightnCandy::FLAG_HANDLEBARSJS,
                ],
                'data' => ['a' => ['b' => new foo()]],
                'expected' => ', OK!, Yes!',
            ],

            [
                'id' => 46,
                'template' => '{{{this.id}}}, {{a.id}}',
                'options' => [
                    'flags' => LightnCandy::FLAG_THIS,
                ],
                'data' => ['id' => 'bla bla bla', 'a' => ['id' => 'OK!']],
                'expected' => 'bla bla bla, OK!',
            ],

            [
                'id' => 49,
                'template' => '{{date_format}} 1, {{date_format2}} 2, {{date_format3}} 3, {{date_format4}} 4',
                'options' => [
                    'helpers' => [
                        'date_format' => 'meetup_date_format',
                        'date_format2' => 'meetup_date_format2',
                        'date_format3' => 'meetup_date_format3',
                        'date_format4' => 'meetup_date_format4',
                    ],
                ],
                'expected' => 'OKOK~1 1, OKOK~2 2, OKOK~3 3, OKOK~4 4',
            ],

            [
                'id' => 52,
                'template' => '{{{test_array tmp}}} should be happy!',
                'options' => [
                    'helpers' => [
                        'test_array',
                    ],
                ],
                'data' => ['tmp' => ['A', 'B', 'C']],
                'expected' => 'IS_ARRAY should be happy!',
            ],

            [
                'id' => 62,
                'template' => '{{{test_join @root.foo.bar}}} should be happy!',
                'options' => [
                     'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_ERROR_EXCEPTION,
                     'helpers' => ['test_join'],
                ],
                'data' => ['foo' => ['A', 'B', 'bar' => ['C', 'D']]],
                'expected' => 'C.D should be happy!',
            ],

            [
                'id' => 64,
                'template' => '{{#each foo}} Test! {{this}} {{/each}}{{> test1}} ! >>> {{>recursive}}',
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_RUNTIMEPARTIAL,
                    'partials' => [
                        'test1' => "123\n",
                        'recursive' => "{{#if foo}}{{bar}} -> {{#with foo}}{{>recursive}}{{/with}}{{else}}END!{{/if}}\n",
                    ],
                ],
                'data' => [
                 'bar' => 1,
                 'foo' => [
                  'bar' => 3,
                  'foo' => [
                   'bar' => 5,
                   'foo' => [
                    'bar' => 7,
                    'foo' => [
                     'bar' => 11,
                     'foo' => [
                      'no foo here',
                     ],
                    ],
                   ],
                  ],
                 ],
                ],
                'expected' => " Test! 3  Test! [object Object] 123\n ! >>> 1 -> 3 -> 5 -> 7 -> 11 -> END!\n\n\n\n\n\n",
            ],

            [
                'id' => 66,
                'template' => '{{&foo}} , {{foo}}, {{{foo}}}',
                'options' => [
                     'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                ],
                'data' => ['foo' => 'Test & " \' :)'],
                'expected' => 'Test & " \' :) , Test &amp; &quot; &#x27; :), Test & " \' :)',
            ],

            [
                'id' => 68,
                'template' => '{{#myeach foo}} Test! {{this}} {{/myeach}}',
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                    'helpers' => [
                        'myeach' => function ($context, $options) {
                            $ret = '';
                            foreach ($context as $cx) {
                                $ret .= $options['fn']($cx);
                            }
                            return $ret;
                        },
                    ],
                ],
                'data' => ['foo' => ['A', 'B', 'bar' => ['C', 'D', 'E']]],
                'expected' => ' Test! A  Test! B  Test! C,D,E ',
            ],

            [
                'id' => 81,
                'template' => '{{#with ../person}} {{^name}} Unknown {{/name}} {{/with}}?!',
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_ERROR_EXCEPTION,
                ],
                'data' => ['parent?!' => ['A', 'B', 'bar' => ['C', 'D', 'E']]],
                'expected' => '?!',
            ],

            [
                'id' => 83,
                'template' => '{{> tests/test1}}',
                'options' => [
                    'partials' => [
                        'tests/test1' => "123\n",
                    ],
                ],
                'expected' => "123\n",
            ],

            [
                'id' => 85,
                'template' => '{{helper 1 foo bar="q"}}',
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                    'helpers' => [
                        'helper' => function ($arg1, $arg2, $options) {
                            return "ARG1:$arg1, ARG2:$arg2, HASH:{$options['hash']['bar']}";
                        },
                    ],
                ],
                'data' => ['foo' => 'BAR'],
                'expected' => 'ARG1:1, ARG2:BAR, HASH:q',
            ],

            [
                'id' => 88,
                'template' => '{{>test2}}',
                'options' => [
                    'flags' => 0,
                    'partials' => [
                        'test2' => "a{{> test1}}b\n",
                        'test1' => "123\n",
                    ],
                ],
                'expected' => "a123\nb\n",
            ],

            [
                'id' => 89,
                'template' => '{{#with}}SHOW:{{.}} {{/with}}',
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_NOHBHELPERS,
                ],
                'data' => ['with' => [1, 3, 7], 'a' => [2, 4, 9]],
                'expected' => 'SHOW:1 SHOW:3 SHOW:7 ',
            ],

            [
                'id' => 90,
                'template' => '{{#items}}{{#value}}{{.}}{{/value}}{{/items}}',
                'data' => ['items' => [['value' => '123']]],
                'expected' => '123',
            ],

            [
                'id' => 109,
                'template' => '{{#if "OK"}}it\'s great!{{/if}}',
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_NOESCAPE,
                ],
                'expected' => 'it\'s great!',
            ],

            [
                'id' => 110,
                'template' => 'ABC{{#block "YES!"}}DEF{{foo}}GHI{{/block}}JKL',
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_BESTPERFORMANCE,
                    'helpers' => [
                        'block' => function ($name, $options) {
                            return "1-$name-2-" . $options['fn']() . '-3';
                        },
                    ],
                ],
                'data' => ['foo' => 'bar'],
                'expected' => 'ABC1-YES!-2-DEFbarGHI-3JKL',
            ],

            [
                'id' => 109,
                'template' => '{{foo}} {{> test}}',
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_NOESCAPE,
                    'partials' => ['test' => '{{foo}}'],
                ],
                'data' => ['foo' => '<'],
                'expected' => '< <',
            ],

            [
                'id' => 114,
                'template' => '{{^myeach .}}OK:{{.}},{{else}}NOT GOOD{{/myeach}}',
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_BESTPERFORMANCE,
                    'helpers' => [
                        'myeach' => function ($context, $options) {
                            $ret = '';
                            foreach ($context as $cx) {
                                $ret .= $options['fn']($cx);
                            }
                            return $ret;
                        },
                    ],
                ],
                'data' => [1, 'foo', 3, 'bar'],
                'expected' => 'NOT GOODNOT GOODNOT GOODNOT GOOD',
            ],

            [
                'id' => 124,
                'template' => '{{list foo bar abc=(lt 10 3) def=(lt 3 10)}}',
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                    'helpers' => [
                        'lt' => function ($a, $b) {
                            return ($a > $b) ? new SafeString("$a>$b") : '';
                        },
                        'list' => function () {
                            $out = 'List:';
                            $args = func_get_args();
                            $opts = array_pop($args);

                            foreach ($args as $v) {
                                if ($v) {
                                    $out .= ")$v , ";
                                }
                            }

                            foreach ($opts['hash'] as $k => $v) {
                                if ($v) {
                                    $out .= "]$k=$v , ";
                                }
                            }
                            return new SafeString($out);
                        },
                    ],
                ],
                'data' => ['foo' => 'OK!', 'bar' => 'OK2', 'abc' => false, 'def' => 123],
                'expected' => 'List:)OK! , )OK2 , ]abc=10>3 , ',
            ],

            [
                'id' => 124,
                'template' => '{{#if (equal \'OK\' cde)}}YES!{{/if}}',
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                    'helpers' => [
                        'equal' => function ($a, $b) {
                            return $a === $b;
                        },
                    ],
                ],
                'data' => ['cde' => 'OK'],
                'expected' => 'YES!',
            ],

            [
                'id' => 124,
                'template' => '{{#if (equal true (equal \'OK\' cde))}}YES!{{/if}}',
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                    'helpers' => [
                        'equal' => function ($a, $b) {
                            return $a === $b;
                        },
                    ],
                ],
                'data' => ['cde' => 'OK'],
                'expected' => 'YES!',
            ],

            [
                'id' => 125,
                'template' => '{{#if (equal true ( equal \'OK\' cde))}}YES!{{/if}}',
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                    'helpers' => [
                        'equal' => function ($a, $b) {
                            return $a === $b;
                        },
                    ],
                ],
                'data' => ['cde' => 'OK'],
                'expected' => 'YES!',
            ],

            [
                'id' => 125,
                'template' => '{{#if (equal true (equal \' OK\' cde))}}YES!{{/if}}',
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                    'helpers' => [
                        'equal' => function ($a, $b) {
                            return $a === $b;
                        },
                    ],
                ],
                'data' => ['cde' => ' OK'],
                'expected' => 'YES!',
            ],

            [
                'id' => 125,
                'template' => '{{#if (equal true (equal \' ==\' cde))}}YES!{{/if}}',
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                    'helpers' => [
                        'equal' => function ($a, $b) {
                            return $a === $b;
                        },
                    ],
                ],
                'data' => ['cde' => ' =='],
                'expected' => 'YES!',
            ],

            [
                'id' => 125,
                'template' => '{{#if (equal true (equal " ==" cde))}}YES!{{/if}}',
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                    'helpers' => [
                        'equal' => function ($a, $b) {
                            return $a === $b;
                        },
                    ],
                ],
                'data' => ['cde' => ' =='],
                'expected' => 'YES!',
            ],

            [
                'id' => 125,
                'template' => '{{[ abc]}}',
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                    'helpers' => [
                        'equal' => function ($a, $b) {
                            return $a === $b;
                        },
                    ],
                ],
                'data' => [' abc' => 'YES!'],
                'expected' => 'YES!',
            ],

            [
                'id' => 125,
                'template' => '{{list [ abc] " xyz" \' def\' "==" \'==\' "OK"}}',
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                    'helpers' => [
                        'list' => function ($a, $b) {
                            $out = 'List:';
                            $args = func_get_args();
                            $opts = array_pop($args);
                            foreach ($args as $v) {
                                if ($v) {
                                    $out .= ")$v , ";
                                }
                            }
                            return $out;
                        },
                    ],
                ],
                'data' => [' abc' => 'YES!'],
                'expected' => 'List:)YES! , ) xyz , ) def , )&#x3D;&#x3D; , )&#x3D;&#x3D; , )OK , ',
            ],

            [
                'id' => 127,
                'template' => '{{#each array}}#{{#if true}}{{name}}-{{../name}}-{{../../name}}-{{../../../name}}{{/if}}##{{#myif true}}{{name}}={{../name}}={{../../name}}={{../../../name}}{{/myif}}###{{#mywith true}}{{name}}~{{../name}}~{{../../name}}~{{../../../name}}{{/mywith}}{{/each}}',
                'data' => ['name' => 'john', 'array' => [1,2,3]],
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                    'helpers' => ['myif', 'mywith'],
                ],
                // PENDING ISSUE, check for https://github.com/wycats/handlebars.js/issues/1135
                // 'expected' => '#--john-##==john=###~~john~#--john-##==john=###~~john~#--john-##==john=###~~john~',
                'expected' => '#-john--##=john==###~~john~#-john--##=john==###~~john~#-john--##=john==###~~john~',
            ],

            [
                'id' => 128,
                'template' => 'foo: {{foo}} , parent foo: {{../foo}}',
                'data' => ['foo' => 'OK'],
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                ],
                'expected' => 'foo: OK , parent foo: ',
            ],

            [
                'id' => 132,
                'template' => '{{list (keys .)}}',
                'data' => ['foo' => 'bar', 'test' => 'ok'],
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                    'helpers' => [
                        'keys' => function ($arg) {
                            return array_keys($arg);
                        },
                        'list' => function ($arg) {
                            return join(',', $arg);
                        },
                    ],
                ],
                'expected' => 'foo,test',
            ],

            [
                'id' => 133,
                'template' => "{{list (keys\n .\n ) \n}}",
                'data' => ['foo' => 'bar', 'test' => 'ok'],
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                    'helpers' => [
                        'keys' => function ($arg) {
                            return array_keys($arg);
                        },
                        'list' => function ($arg) {
                            return join(',', $arg);
                        },
                    ],
                ],
                'expected' => 'foo,test',
            ],

            [
                'id' => 133,
                'template' => "{{list\n .\n \n \n}}",
                'data' => ['foo', 'bar', 'test'],
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                    'helpers' => [
                        'list' => function ($arg) {
                            return join(',', $arg);
                        },
                    ],
                ],
                'expected' => 'foo,bar,test',
            ],

            [
                'id' => 134,
                'template' => '{{#if 1}}{{list (keys names)}}{{/if}}',
                'data' => ['names' => ['foo' => 'bar', 'test' => 'ok']],
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                    'helpers' => [
                        'keys' => function ($arg) {
                            return array_keys($arg);
                        },
                        'list' => function ($arg) {
                            return join(',', $arg);
                        },
                    ],
                ],
                'expected' => 'foo,test',
            ],

            [
                'id' => 138,
                'template' => '{{#each (keys .)}}={{.}}{{/each}}',
                'data' => ['foo' => 'bar', 'test' => 'ok', 'Haha'],
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                    'helpers' => [
                        'keys' => function ($arg) {
                            return array_keys($arg);
                        },
                    ],
                ],
                'expected' => '=foo=test=0',
            ],

            [
                'id' => 140,
                'template' => '{{[a.good.helper] .}}',
                'data' => ['ha', 'hey', 'ho'],
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                    'helpers' => [
                        'a.good.helper' => function ($arg) {
                            return join(',', $arg);
                        },
                    ],
                ],
                'expected' => 'ha,hey,ho',
            ],

            [
                'id' => 141,
                'template' => '{{#with foo}}{{#getThis bar}}{{/getThis}}{{/with}}',
                'data' => ['foo' => ['bar' => 'Good!']],
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                    'helpers' => [
                        'getThis' => function ($input, $options) {
                            return $input . '-' . $options['_this']['bar'];
                        },
                    ],
                ],
                'expected' => 'Good!-Good!',
            ],

            [
                'id' => 141,
                'template' => '{{#with foo}}{{getThis bar}}{{/with}}',
                'data' => ['foo' => ['bar' => 'Good!']],
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                    'helpers' => [
                        'getThis' => function ($input, $options) {
                            return $input . '-' . $options['_this']['bar'];
                        },
                    ],
                ],
                'expected' => 'Good!-Good!',
            ],

            [
                'id' => 143,
                'template' => '{{testString foo bar=" "}}',
                'data' => ['foo' => 'good!'],
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                    'helpers' => [
                        'testString' => function ($arg, $options) {
                            return $arg . '-' . $options['hash']['bar'];
                        },
                    ],
                ],
                'expected' => 'good!- ',
            ],

            [
                'id' => 143,
                'template' => '{{testString foo bar=""}}',
                'data' => ['foo' => 'good!'],
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                    'helpers' => [
                        'testString' => function ($arg, $options) {
                            return $arg . '-' . $options['hash']['bar'];
                        },
                    ],
                ],
                'expected' => 'good!-',
            ],

            [
                'id' => 143,
                'template' => "{{testString foo bar=' '}}",
                'data' => ['foo' => 'good!'],
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                    'helpers' => [
                        'testString' => function ($arg, $options) {
                            return $arg . '-' . $options['hash']['bar'];
                        },
                    ],
                ],
                'expected' => 'good!- ',
            ],

            [
                'id' => 143,
                'template' => "{{testString foo bar=''}}",
                'data' => ['foo' => 'good!'],
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                    'helpers' => [
                        'testString' => function ($arg, $options) {
                            return $arg . '-' . $options['hash']['bar'];
                        },
                    ],
                ],
                'expected' => 'good!-',
            ],

            [
                'id' => 143,
                'template' => '{{testString foo bar=" "}}',
                'data' => ['foo' => 'good!'],
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                    'helpers' => [
                        'testString' => function ($arg1, $options) {
                            return $arg1 . '-' . $options['hash']['bar'];
                        },
                    ],
                ],
                'expected' => 'good!- ',
            ],

            [
                'id' => 147,
                'template' => '{{> test/test3 foo="bar"}}',
                'data' => ['test' => 'OK!', 'foo' => 'error'],
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_RUNTIMEPARTIAL,
                    'partials' => ['test/test3' => '{{test}}, {{foo}}'],
                ],
                'expected' => 'OK!, bar',
            ],

            [
                'id' => 147,
                'template' => '{{> test/test3 foo="bar"}}',
                'data' => new foo(),
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_RUNTIMEPARTIAL | LightnCandy::FLAG_INSTANCE,
                    'partials' => ['test/test3' => '{{bar}}, {{foo}}'],
                ],
                'expected' => 'OK!, bar',
            ],

            [
                'id' => 150,
                'template' => '{{{.}}}',
                'data' => ['hello' => 'world'],
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                    'runtime' => 'MyLCRunClass',
                ],
                'expected' => "[[DEBUG:raw()=>array (\n  'hello' => 'world',\n)]]",
            ],

            [
                'id' => 153,
                'template' => '{{echo "test[]"}}',
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                    'helpers' => [
                        'echo' => function ($in) {
                            return "-$in-";
                        },
                    ],
                ],
                'expected' => '-test[]-',
            ],

            [
                'id' => 153,
                'template' => '{{echo \'test[]\'}}',
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                    'helpers' => [
                        'echo' => function ($in) {
                            return "-$in-";
                        },
                    ],
                ],
                'expected' => '-test[]-',
            ],

            [
                'id' => 154,
                'template' => 'O{{! this is comment ! ... }}K!',
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                ],
                'expected' => 'OK!',
            ],

            [
                'id' => 157,
                'template' => '{{{du_mp text=(du_mp "123")}}}',
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                    'helpers' => [
                        'du_mp' => function ($a) {
                            return '>' . print_r(isset($a['hash']) ? $a['hash'] : $a, true);
                        },
                    ],
                ],
                'expected' => <<<VAREND
>Array
(
    [text] => >123
)

VAREND
            ],

            [
                'id' => 157,
                'template' => '{{>test_js_partial}}',
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_RUNTIMEPARTIAL,
                    'partials' => [
                        'test_js_partial' => <<<VAREND
Test GA....
<script>
(function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){console.log('works!')};})();
</script>
VAREND
                    ],
                ],
                'expected' => <<<VAREND
Test GA....
<script>
(function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){console.log('works!')};})();
</script>
VAREND
            ],

            [
                'id' => 159,
                'template' => '{{#.}}true{{else}}false{{/.}}',
                'data' => new ArrayObject(),
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                ],
                'expected' => 'false',
            ],

            [
                'id' => 169,
                'template' => '{{{{a}}}}true{{else}}false{{{{/a}}}}',
                'data' => ['a' => true],
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                ],
                'expected' => 'true{{else}}false',
            ],

            [
                'id' => 171,
                'template' => '{{#my_private_each .}}{{@index}}:{{.}},{{/my_private_each}}',
                'data' => ['a', 'b', 'c'],
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_ERROR_EXCEPTION,
                    'helpers' => [
                        'my_private_each',
                    ],
                ],
                'expected' => '0:a,1:b,2:c,',
            ],

            [
                'id' => 175,
                'template' => 'a{{!-- {{each}} haha {{/each}} --}}b',
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                ],
                'expected' => 'ab',
            ],

            [
                'id' => 175,
                'template' => 'c{{>test}}d',
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                    'partials' => [
                        'test' => 'a{{!-- {{each}} haha {{/each}} --}}b',
                    ],
                ],
                'expected' => 'cabd',
            ],

            [
                'id' => 177,
                'template' => '{{{{a}}}} {{{{b}}}} {{{{/b}}}} {{{{/a}}}}',
                'data' => ['a' => true],
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                ],
                'expected' => ' {{{{b}}}} {{{{/b}}}} ',
            ],

            [
                'id' => 177,
                'template' => '{{{{a}}}} {{{{b}}}} {{{{/b}}}} {{{{/a}}}}',
                'data' => ['a' => true],
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                    'helpers' => [
                        'a' => function ($options) {
                            return $options['fn']();
                        },
                    ],
                ],
                'expected' => ' {{{{b}}}} {{{{/b}}}} ',
            ],

            [
                'id' => 177,
                'template' => '{{{{a}}}} {{{{b}}}} {{{{/b}}}} {{{{/a}}}}',
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                ],
                'expected' => '',
            ],

            [
                'id' => 191,
                'template' => '<% foo %> is good <%> bar %>',
                'data' => ['foo' => 'world'],
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                    'delimiters' => ['<%', '%>'],
                    'partials' => [
                        'bar' => '<% @root.foo %>{{:D}}!',
                    ],
                ],
                'expected' => 'world is good world{{:D}}!',
            ],

            [
                'id' => 199,
                'template' => '{{#if foo}}1{{else if bar}}2{{else}}3{{/if}}',
                'options' => [
                    'flags' => LightnCandy::FLAG_ELSE,
                ],
                'expected' => '3',
            ],

            [
                'id' => 199,
                'template' => '{{#if foo}}1{{else if bar}}2{{/if}}',
                'options' => [
                    'flags' => LightnCandy::FLAG_ELSE,
                ],
                'data' => ['bar' => true],
                'expected' => '2',
            ],

            [
                'id' => 201,
                'template' => '{{foo "world"}}',
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARS,
                    'helperresolver' => function ($cx, $name) {
                        return function ($name, $option) {
                            return "Hello, $name";
                        };
                    },
                ],
                'expected' => 'Hello, world',
            ],

            [
                'id' => 201,
                'template' => '{{#foo "test"}}World{{/foo}}',
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARS,
                    'helperresolver' => function ($cx, $name) {
                        return function ($name, $option) {
                            return "$name = " . $option['fn']();
                        };
                    },
                ],
                'expected' => 'test = World',
            ],

            [
                'id' => 204,
                'template' => '{{#> test name="A"}}B{{/test}}{{#> test name="C"}}D{{/test}}',
                'data' => ['bar' => true],
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARS | LightnCandy::FLAG_RUNTIMEPARTIAL,
                    'partials' => [
                        'test' => '{{name}}:{{> @partial-block}},',
                    ],
                ],
                'expected' => 'A:B,C:D,',
            ],

            [
                'id' => 206,
                'template' => '{{#with bar}}{{#../foo}}YES!{{/../foo}}{{/with}}',
                'data' => ['foo' => 999, 'bar' => true],
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARS,
                ],
                'expected' => 'YES!',
            ],

            [
                'id' => 213,
                'template' => '{{#if foo}}foo{{else if bar}}{{#moo moo}}moo{{/moo}}{{/if}}',
                'data' => ['foo' => true],
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARS,
                    'helpers' => [
                        'moo' => function ($arg1) {
                            return ($arg1 === null);
                        },
                    ],
                ],
                'expected' => 'foo',
            ],

            [
                'id' => 213,
                'template' => '{{#with .}}bad{{else}}Good!{{/with}}',
                'data' => [],
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARS,
                ],
                'expected' => 'Good!',
            ],

            [
                'id' => 216,
                'template' => '{{foo.length}}',
                'data' => ['foo' => []],
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARS,
                ],
                'expected' => '',
            ],

            [
                'id' => 216,
                'template' => '{{foo.length}}',
                'data' => ['foo' => []],
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                ],
                'expected' => '0',
            ],

            [
                'id' => 221,
                'template' => 'a{{ouch}}b',
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                    'helpers' => $test_helpers,
                ],
                'expected' => 'aokb',
            ],

            [
                'id' => 221,
                'template' => 'a{{ouch}}b',
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                    'helpers' => $test_helpers2,
                ],
                'expected' => 'awa!b',
            ],

            [
                'id' => 221,
                'template' => 'a{{ouch}}b',
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                    'helpers' => $test_helpers3,
                ],
                'expected' => 'awa!b',
            ],

            [
                'id' => 224,
                'template' => '{{#> foo bar}}a,b,{{.}},{{!-- comment --}},d{{/foo}}',
                'data' => ['bar' => 'BA!'],
                'options' => [
                    'flags' => LightnCandy::FLAG_RUNTIMEPARTIAL | LightnCandy::FLAG_THIS | LightnCandy::FLAG_SPVARS | LightnCandy::FLAG_ERROR_LOG | LightnCandy::FLAG_ERROR_EXCEPTION | LightnCandy::FLAG_ERROR_SKIPPARTIAL | LightnCandy::FLAG_PARENT,
                    'partials' => ['foo' => 'hello, {{> @partial-block}}'],
                ],
                'expected' => 'hello, a,b,BA!,,d',
            ],

            [
                'id' => 224,
                'template' => '{{#> foo bar}}{{#if .}}OK! {{.}}{{else}}no bar{{/if}}{{/foo}}',
                'data' => ['bar' => 'BA!'],
                'options' => [
                    'flags' => LightnCandy::FLAG_RUNTIMEPARTIAL | LightnCandy::FLAG_THIS | LightnCandy::FLAG_SPVARS | LightnCandy::FLAG_ERROR_LOG | LightnCandy::FLAG_ERROR_EXCEPTION | LightnCandy::FLAG_ERROR_SKIPPARTIAL | LightnCandy::FLAG_PARENT,
                    'partials' => ['foo' => 'hello, {{> @partial-block}}'],
                ],
                'expected' => 'hello, OK! BA!no bar',
            ],

            [
                'id' => 224,
                'template' => '{{#> foo bar}}{{#if .}}OK! {{.}}{{else}}no bar{{/if}}{{/foo}}',
                'data' => ['bar' => 'BA!'],
                'options' => [
                    'flags' => LightnCandy::FLAG_RUNTIMEPARTIAL | LightnCandy::FLAG_THIS | LightnCandy::FLAG_SPVARS | LightnCandy::FLAG_ERROR_LOG | LightnCandy::FLAG_ERROR_EXCEPTION | LightnCandy::FLAG_ERROR_SKIPPARTIAL | LightnCandy::FLAG_PARENT | LightnCandy::FLAG_ELSE,
                    'partials' => ['foo' => 'hello, {{> @partial-block}}'],
                ],
                'expected' => 'hello, OK! BA!',
            ],

            [
                'id' => 227,
                'template' => '{{#if moo}}A{{else if bar}}B{{else foo}}C{{/if}}',
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARS | LightnCandy::FLAG_ERROR_EXCEPTION,
                    'helpers' => [
                        'foo' => function ($options) {
                            return $options['fn']();
                        },
                    ],
                ],
                'expected' => 'C',
            ],

            [
                'id' => 227,
                'template' => '{{#if moo}}A{{else if bar}}B{{else with foo}}C{{.}}{{/if}}',
                'data' => ['foo' => 'D'],
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARS | LightnCandy::FLAG_ERROR_EXCEPTION,
                ],
                'expected' => 'CD',
            ],

            [
                'id' => 227,
                'template' => '{{#if moo}}A{{else if bar}}B{{else each foo}}C{{.}}{{/if}}',
                'data' => ['foo' => [1, 3, 5]],
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARS | LightnCandy::FLAG_ERROR_EXCEPTION,
                ],
                'expected' => 'C1C3C5',
            ],

            [
                'id' => 229,
                'template' => '{{#if foo.bar.moo}}TRUE{{else}}FALSE{{/if}}',
                'data' => [],
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARS | LightnCandy::FLAG_ERROR_EXCEPTION,
                ],
                'expected' => 'FALSE',
            ],

            [
                'id' => 233,
                'template' => '{{#if foo}}FOO{{else}}BAR{{/if}}',
                'data' => [],
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARS,
                    'helpers' => [
                        'if' => function ($arg, $options) {
                            return $options['fn']();
                        },
                    ],
                ],
                'expected' => 'FOO',
            ],

            [
                'id' => 234,
                'template' => '{{> (lookup foo 2)}}',
                'data' => ['foo' => ['a', 'b', 'c']],
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARS | LightnCandy::FLAG_RUNTIMEPARTIAL,
                    'partials' => [
                        'a' => '1st',
                        'b' => '2nd',
                        'c' => '3rd',
                    ],
                ],
                'expected' => '3rd',
            ],

            [
                'id' => 235,
                'template' => '{{#> "myPartial"}}{{#> myOtherPartial}}{{ @root.foo}}{{/myOtherPartial}}{{/"myPartial"}}',
                'data' => ['foo' => 'hello!'],
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARS | LightnCandy::FLAG_RUNTIMEPARTIAL,
                    'partials' => [
                        'myPartial' => '<div>outer {{> @partial-block}}</div>',
                        'myOtherPartial' => '<div>inner {{> @partial-block}}</div>',
                    ],
                ],
                'expected' => '<div>outer <div>inner hello!</div></div>',
            ],

            [
                'id' => 236,
                'template' => 'A{{#> foo}}B{{#> bar}}C{{>moo}}D{{/bar}}E{{/foo}}F',
                'options' => [
                    'flags' => LightnCandy::FLAG_RUNTIMEPARTIAL | LightnCandy::FLAG_HANDLEBARS,
                    'partials' => [
                        'foo' => 'FOO>{{> @partial-block}}<FOO',
                        'bar' => 'bar>{{> @partial-block}}<bar',
                        'moo' => 'MOO!',
                    ],
                ],
                'expected' => 'AFOO>Bbar>CMOO!D<barE<FOOF',
            ],

            [
                'id' => 241,
                'template' => '{{#>foo}}{{#*inline "bar"}}GOOD!{{#each .}}>{{.}}{{/each}}{{/inline}}{{/foo}}',
                'data' => ['1', '3', '5'],
                'options' => [
                    'flags' => LightnCandy::FLAG_RUNTIMEPARTIAL,
                    'partials' => [
                        'foo' => 'A{{#>bar}}BAD{{/bar}}B',
                        'moo' => 'oh',
                    ],
                ],
                'expected' => 'AGOOD!>1>3>5B',
            ],

            [
                'id' => 243,
                'template' => '{{lookup . 3}}',
                'data' => ['3' => 'OK'],
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARS,
                ],
                'expected' => 'OK',
            ],

            [
                'id' => 243,
                'template' => '{{lookup . "test"}}',
                'data' => ['test' => 'OK'],
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARS,
                ],
                'expected' => 'OK',
            ],

            [
                'id' => 244,
                'template' => '{{#>outer}}content{{/outer}}',
                'data' => ['test' => 'OK'],
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARS | LightnCandy::FLAG_RUNTIMEPARTIAL,
                    'partials' => [
                        'outer' => 'outer+{{#>nested}}~{{>@partial-block}}~{{/nested}}+outer-end',
                        'nested' => 'nested={{>@partial-block}}=nested-end',
                    ],
                ],
                'expected' => 'outer+nested=~content~=nested-end+outer-end',
            ],

            [
                'id' => 245,
                'template' => '{{#each foo}}{{#with .}}{{bar}}-{{../../name}}{{/with}}{{/each}}',
                'data' => ['name' => 'bad', 'foo' => [
                    ['bar' => 1],
                    ['bar' => 2],
                ]],
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARS,
                ],
                'expected' => '1-2-',
            ],

            [
                'id' => 251,
                'template' => '{{>foo}}',
                'data' => ['bar' => 'BAD'],
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARS | LightnCandy::FLAG_RUNTIMEPARTIAL | LightnCandy::FLAG_EXTHELPER,
                    'partials' => ['foo' => '{{bar}}'],
                    'helperresolver' => function ($cx, $name) {
                        return function () {
                            return 'OK!';
                        };
                    },
                ],
                'expected' => 'OK!',
            ],

            [
                'id' => 252,
                'template' => '{{foo (lookup bar 1)}}',
                'data' => ['bar' => [
                    'nil',
                    [3, 5],
                ]],
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARS,
                    'helpers' => [
                        'foo' => function ($arg1) {
                            return is_array($arg1) ? 'OK' : 'bad';
                        },
                    ],
                ],
                'expected' => 'OK',
            ],

            [
                'id' => 253,
                'template' => '{{foo.bar}}',
                'data' => ['foo' => ['bar' => 'OK!']],
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARS,
                    'helpers' => [
                        'foo' => function () {
                            return 'bad';
                        },
                    ],
                ],
                'expected' => 'OK!',
            ],

            [
                'id' => 254,
                'template' => '{{#if a}}a{{else if b}}b{{else}}c{{/if}}{{#if a}}a{{else if b}}b{{/if}}',
                'data' => ['b' => 1],
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARS,
                ],
                'expected' => 'bb',
            ],

            [
                'id' => 255,
                'template' => '{{foo.length}}',
                'data' => ['foo' => [1, 2]],
                'options' => [
                    'flags' => LightnCandy::FLAG_JSLENGTH | LightnCandy::FLAG_METHOD,
                ],
                'expected' => '2',
            ],

            [
                'id' => 256,
                'template' => '{{lookup . "foo"}}',
                'data' => ['foo' => 'ok'],
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARSLAMBDA,
                ],
                'expected' => 'ok',
            ],

            [
                'id' => 257,
                'template' => '{{foo a=(foo a=(foo a="ok"))}}',
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARS,
                    'helpers' => [
                        'foo' => function ($opt) {
                            return $opt['hash']['a'];
                        },
                    ],
                ],
                'expected' => 'ok',
            ],

            [
                'id' => 261,
                'template' => '{{#each foo as |bar|}}?{{bar.0}}{{/each}}',
                'data' => ['foo' => [['a'], ['b']]],
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARS,
                ],
                'expected' => '?a?b',
            ],

            [
                'id' => 267,
                'template' => '{{#each . as |v k|}}#{{k}}>{{v}}|{{.}}{{/each}}',
                'data' => ['a' => 'b', 'c' => 'd'],
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARS | LightnCandy::FLAG_PROPERTY,
                ],
                'expected' => '#a>b|b#c>d|d',
            ],

            [
                'id' => 268,
                'template' => '{{foo}}{{bar}}',
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARS,
                    'helpers' => [
                        'foo' => function ($opt) {
                            $opt['_this']['change'] = true;
                        },
                        'bar' => function ($opt) {
                            return $opt['_this']['change'] ? 'ok' : 'bad';
                        },
                    ],
                ],
                'expected' => 'ok',
            ],

            [
                'id' => 278,
                'template' => '{{#foo}}-{{#bar}}={{moo}}{{/bar}}{{/foo}}',
                'data' => [
                    'foo' => [
                         ['bar' => 0, 'moo' => 'A'],
                         ['bar' => 1, 'moo' => 'B'],
                         ['bar' => false, 'moo' => 'C'],
                         ['bar' => true, 'moo' => 'D'],
                    ],
                ],
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARS,
                ],
                'expected' => '-=-=--=D',
            ],

            [
                'id' => 278,
                'template' => '{{#foo}}-{{#bar}}={{moo}}{{/bar}}{{/foo}}',
                'data' => [
                    'foo' => [
                         ['bar' => 0, 'moo' => 'A'],
                         ['bar' => 1, 'moo' => 'B'],
                         ['bar' => false, 'moo' => 'C'],
                         ['bar' => true, 'moo' => 'D'],
                    ],
                ],
                'options' => [
                    'flags' => LightnCandy::FLAG_MUSTACHE,
                ],
                'expected' => '--=B--=D',
            ],

            [
                'id' => 281,
                'template' => '{{echo (echo "foo bar (moo).")}}',
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARS,
                    'helpers' => [
                        'echo' => function ($arg1) {
                            return "ECHO: $arg1";
                        },
                    ],
                ],
                'expected' => 'ECHO: ECHO: foo bar (moo).',
            ],

            [
                'id' => 284,
                'template' => '{{> foo}}',
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_RUNTIMEPARTIAL,
                    'partials' => ['foo' => "12'34"],
                ],
                'expected' => "12'34",
            ],

            [
                'id' => 284,
                'template' => '{{> (lookup foo 2)}}',
                'data' => ['foo' => ['a', 'b', 'c']],
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARS | LightnCandy::FLAG_RUNTIMEPARTIAL,
                    'partials' => [
                        'a' => '1st',
                        'b' => '2nd',
                        'c' => "3'r'd",
                    ],
                ],
                'expected' => "3'r'd",
            ],

            [
                'id' => 289,
                'template' => "1\n2\n{{~foo~}}\n3",
                'data' => ['foo' => 'OK'],
                'expected' => "1\n2OK3",
            ],

            [
                'id' => 289,
                'template' => "1\n2\n{{#test}}\n3TEST\n{{/test}}\n4",
                'data' => ['test' => 1],
                'options' => [
                    'flags' => LightnCandy::FLAG_RUNTIMEPARTIAL,
                ],
                'expected' => "1\n2\n3TEST\n4",
            ],

            [
                'id' => 289,
                'template' => "1\n2\n{{~#test}}\n3TEST\n{{/test}}\n4",
                'data' => ['test' => 1],
                'options' => [
                    'flags' => LightnCandy::FLAG_RUNTIMEPARTIAL,
                ],
                'expected' => "1\n23TEST\n4",
            ],

            [
                'id' => 289,
                'template' => "1\n2\n{{#>test}}\n3TEST\n{{/test}}\n4",
                'options' => [
                    'flags' => LightnCandy::FLAG_RUNTIMEPARTIAL,
                ],
                'expected' => "1\n2\n3TEST\n4",
            ],

            [
                'id' => 289,
                'template' => "1\n2\n\n{{#>test}}\n3TEST\n{{/test}}\n4",
                'options' => [
                    'flags' => LightnCandy::FLAG_RUNTIMEPARTIAL,
                ],
                'expected' => "1\n2\n\n3TEST\n4",
            ],

            [
                'id' => 289,
                'template' => "1\n2\n\n{{#>test~}}\n\n3TEST\n{{/test}}\n4",
                'options' => [
                    'flags' => LightnCandy::FLAG_RUNTIMEPARTIAL,
                ],
                'expected' => "1\n2\n\n3TEST\n4",
            ],

            [
                'id' => 290,
                'template' => '{{foo}} }} OK',
                'data' => [
                  'foo' => 'YES',
                ],
                'expected' => 'YES }} OK',
            ],

            [
                'id' => 290,
                'template' => '{{foo}}{{#with "}"}}{{.}}{{/with}}OK',
                'data' => [
                  'foo' => 'YES',
                ],
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                ],
                'expected' => 'YES}OK',
            ],

            [
                'id' => 290,
                'template' => '{ {{foo}}',
                'data' => [
                  'foo' => 'YES',
                ],
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                ],
                'expected' => '{ YES',
            ],

            [
                'id' => 290,
                'template' => '{{#with "{{"}}{{.}}{{/with}}{{foo}}{{#with "{{"}}{{.}}{{/with}}',
                'data' => [
                  'foo' => 'YES',
                ],
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                ],
                'expected' => '{{YES{{',
            ],

            [
                'id' => 291,
                'template' => 'a{{> @partial-block}}b',
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                ],
                'expected' => 'ab',
            ],

            [
                'id' => 302,
                'template' => '{{#*inline "t1"}}{{#if imageUrl}}<span />{{else}}<div />{{/if}}{{/inline}}{{#*inline "t2"}}{{#if imageUrl}}<span />{{else}}<div />{{/if}}{{/inline}}{{#*inline "t3"}}{{#if imageUrl}}<span />{{else}}<div />{{/if}}{{/inline}}',
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARS | LightnCandy::FLAG_RUNTIMEPARTIAL,
                ],
                'expected' => '',
            ],

            [
                'id' => 303,
                'template' => '{{#*inline "t1"}} {{#if url}} <a /> {{else if imageUrl}} <img /> {{else}} <span /> {{/if}} {{/inline}}',
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARS | LightnCandy::FLAG_RUNTIMEPARTIAL,
                ],
                'expected' => '',
            ],

            [
                'id' => 315,
                'template' => '{{#each foo}}#{{@key}}({{@index}})={{.}}-{{moo}}-{{@irr}}{{/each}}',
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARS | LightnCandy::FLAG_ERROR_EXCEPTION,
                    'helpers' => [
                        'moo' => function ($opts) {
                            $opts['data']['irr'] = '123';
                            return '321';
                        },
                    ],
                ],
                'data' => [
                    'foo' => [
                        'a' => 'b',
                        'c' => 'd',
                        'e' => 'f',
                    ],
                ],
                'expected' => '#a(0)=b-321-123#c(1)=d-321-123#e(2)=f-321-123',
            ],

            [
                'template' => '{{#each . as |v k|}}#{{k}}{{/each}}',
                'data' => ['a' => [], 'c' => []],
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARS,
                ],
                'expected' => '#a#c',
            ],

            [
                'template' => '{{testNull null undefined 1}}',
                'data' => 'test',
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                    'helpers' => [
                        'testNull' => function ($arg1, $arg2) {
                            return (($arg1 === null) && ($arg2 === null)) ? 'YES!' : 'no';
                        },
                    ],
                ],
                'expected' => 'YES!',
            ],

            [
                'template' => '{{> (pname foo) bar}}',
                'data' => ['bar' => 'OK! SUBEXP+PARTIAL!', 'foo' => 'test/test3'],
                'options' => [
                    'helpers' => [
                        'pname' => function ($arg) {
                            return $arg;
                        },
                    ],
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_RUNTIMEPARTIAL,
                    'partials' => ['test/test3' => '{{.}}'],
                ],
                'expected' => 'OK! SUBEXP+PARTIAL!',
            ],

            [
                'template' => '{{> testpartial newcontext mixed=foo}}',
                'data' => ['foo' => 'OK!', 'newcontext' => ['bar' => 'test']],
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_RUNTIMEPARTIAL,
                    'partials' => ['testpartial' => '{{bar}}-{{mixed}}'],
                ],
                'expected' => 'test-OK!',
            ],

            [
                'template' => '{{[helper]}}',
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                    'helpers' => [
                        'helper' => function () {
                            return 'DEF';
                        },
                    ],
                ],
                'data' => [],
                'expected' => 'DEF',
            ],

            [
                'template' => '{{#[helper3]}}ABC{{/[helper3]}}',
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                    'helpers' => [
                        'helper3' => function () {
                            return 'DEF';
                        },
                    ],
                ],
                'data' => [],
                'expected' => 'DEF',
            ],

            [
                'template' => '{{hash abc=["def=123"]}}',
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_BESTPERFORMANCE,
                    'helpers' => [
                        'hash' => function ($options) {
                            $ret = '';
                            foreach ($options['hash'] as $k => $v) {
                                $ret .= "$k : $v,";
                            }
                            return $ret;
                        },
                    ],
                ],
                'data' => ['"def=123"' => 'La!'],
                'expected' => 'abc : La!,',
            ],

            [
                'template' => '{{hash abc=[\'def=123\']}}',
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_BESTPERFORMANCE,
                    'helpers' => [
                        'hash' => function ($options) {
                            $ret = '';
                            foreach ($options['hash'] as $k => $v) {
                                $ret .= "$k : $v,";
                            }
                            return $ret;
                        },
                    ],
                ],
                'data' => ["'def=123'" => 'La!'],
                'expected' => 'abc : La!,',
            ],

            [
                'template' => 'ABC{{#block "YES!"}}DEF{{foo}}GHI{{else}}NO~{{/block}}JKL',
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_BESTPERFORMANCE,
                    'helpers' => [
                        'block' => function ($name, $options) {
                            return "1-$name-2-" . $options['fn']() . '-3';
                        },
                    ],
                ],
                'data' => ['foo' => 'bar'],
                'expected' => 'ABC1-YES!-2-DEFbarGHI-3JKL',
            ],

            [
                'template' => '-{{getroot}}=',
                'options' => [
                    'flags' => LightnCandy::FLAG_SPVARS,
                    'helpers' => ['getroot'],
                ],
                'data' => 'ROOT!',
                'expected' => '-ROOT!=',
            ],

            [
                'template' => 'A{{#each .}}-{{#each .}}={{.}},{{@key}},{{@index}},{{@../index}}~{{/each}}%{{/each}}B',
                'data' => [['a' => 'b'], ['c' => 'd'], ['e' => 'f']],
                'options' => [
                    'flags' => LightnCandy::FLAG_PARENT | LightnCandy::FLAG_THIS | LightnCandy::FLAG_SPVARS,
                ],
                'expected' => 'A-=b,a,0,0~%-=d,c,0,1~%-=f,e,0,2~%B',
            ],

            [
                'template' => 'ABC{{#block "YES!"}}TRUE{{else}}DEF{{foo}}GHI{{/block}}JKL',
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_BESTPERFORMANCE,
                    'helpers' => [
                        'block' => function ($name, $options) {
                            return "1-$name-2-" . $options['inverse']() . '-3';
                        },
                    ],
                ],
                'data' => ['foo' => 'bar'],
                'expected' => 'ABC1-YES!-2-DEFbarGHI-3JKL',
            ],

            [
                'template' => '{{#each .}}{{..}}>{{/each}}',
                'data' => ['a', 'b', 'c'],
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                ],
                'expected' => 'a,b,c>a,b,c>a,b,c>',
            ],

            [
                'template' => '{{#each .}}->{{>tests/test3}}{{/each}}',
                'data' => ['a', 'b', 'c'],
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                    'partials' => [
                        'tests/test3' => 'New context:{{.}}',
                    ],
                ],
                'expected' => '->New context:a->New context:b->New context:c',
            ],

            [
                'template' => '{{#each .}}->{{>tests/test3 ../foo}}{{/each}}',
                'data' => ['a', 'foo' => ['d', 'e', 'f']],
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_RUNTIMEPARTIAL,
                    'partials' => [
                        'tests/test3' => 'New context:{{.}}',
                    ],
                ],
                'expected' => '->New context:d,e,f->New context:d,e,f',
            ],

            [
                'template' => '{{{"{{"}}}',
                'data' => ['{{' => ':D'],
                'expected' => ':D',
            ],

            [
                'template' => '{{{\'{{\'}}}',
                'data' => ['{{' => ':D'],
                'expected' => ':D',
            ],

            [
                'template' => '{{#with "{{"}}{{.}}{{/with}}',
                'expected' => '{{',
            ],

            [
                'template' => '{{good_helper}}',
                'options' => [
                    'helpers' => ['good_helper' => 'foo::bar'],
                ],
                'expected' => 'OK!',
            ],

            [
                'template' => '-{{.}}-',
                'options' => ['flags' => LightnCandy::FLAG_THIS],
                'data' => 'abc',
                'expected' => '-abc-',
            ],

            [
                'template' => '-{{this}}-',
                'options' => ['flags' => LightnCandy::FLAG_THIS],
                'data' => 123,
                'expected' => '-123-',
            ],

            [
                'template' => '{{#if .}}YES{{else}}NO{{/if}}',
                'options' => ['flags' => LightnCandy::FLAG_ELSE],
                'data' => true,
                'expected' => 'YES',
            ],

            [
                'template' => '{{foo}}',
                'options' => ['flags' => LightnCandy::FLAG_RENDER_DEBUG],
                'data' => ['foo' => 'OK'],
                'expected' => 'OK',
            ],

            [
                'template' => '{{foo}}',
                'options' => ['flags' => LightnCandy::FLAG_RENDER_DEBUG],
                'debug' => Runtime::DEBUG_TAGS_ANSI,
                'data' => ['foo' => 'OK'],
                'expected' => pack('H*', '1b5b303b33326d7b7b5b666f6f5d7d7d1b5b306d'),
            ],

            [
                'template' => '{{foo}}',
                'options' => ['flags' => LightnCandy::FLAG_RENDER_DEBUG],
                'debug' => Runtime::DEBUG_TAGS_HTML,
                'expected' => '<!--MISSED((-->{{[foo]}}<!--))-->',
            ],

            [
                'template' => '{{#foo}}OK{{/foo}}',
                'options' => ['flags' => LightnCandy::FLAG_RENDER_DEBUG],
                'debug' => Runtime::DEBUG_TAGS_HTML,
                'expected' => '<!--MISSED((-->{{#[foo]}}<!--))--><!--SKIPPED--><!--MISSED((-->{{/[foo]}}<!--))-->',
            ],

            [
                'template' => '{{#foo}}OK{{/foo}}',
                'options' => ['flags' => LightnCandy::FLAG_RENDER_DEBUG],
                'debug' => Runtime::DEBUG_TAGS_ANSI,
                'expected' => pack('H*', '1b5b303b33316d7b7b235b666f6f5d7d7d1b5b306d1b5b303b33336d534b49505045441b5b306d1b5b303b33316d7b7b2f5b666f6f5d7d7d1b5b306d'),
            ],

            [
                'template' => '{{#myif foo}}YES{{else}}NO{{/myif}}',
                'options' => [
                    'flags' => LightnCandy::FLAG_ELSE,
                    'helpers' => ['myif'],
                ],
                'expected' => 'NO',
            ],

            [
                'template' => '{{#myif foo}}YES{{else}}NO{{/myif}}',
                'data' => ['foo' => 1],
                'options' => [
                    'flags' => LightnCandy::FLAG_ELSE,
                    'helpers' => ['myif'],
                ],
                'expected' => 'YES',
            ],

            [
                'template' => '{{#mylogic 0 foo bar}}YES:{{.}}{{else}}NO:{{.}}{{/mylogic}}',
                'data' => ['foo' => 'FOO', 'bar' => 'BAR'],
                'options' => [
                    'flags' => LightnCandy::FLAG_ELSE,
                    'helpers' => ['mylogic'],
                ],
                'expected' => 'NO:BAR',
            ],

            [
                'template' => '{{#mylogic 0 foo bar}}YES:{{.}}{{else}}NO:{{.}}{{/mylogic}}',
                'data' => ['foo' => 'FOO', 'bar' => 'BAR'],
                'options' => [
                    'helpers' => ['mylogic'],
                ],
                'expected' => '',
            ],

            [
                'template' => '{{#mylogic true foo bar}}YES:{{.}}{{else}}NO:{{.}}{{/mylogic}}',
                'data' => ['foo' => 'FOO', 'bar' => 'BAR'],
                'options' => [
                    'flags' => LightnCandy::FLAG_ELSE,
                    'helpers' => ['mylogic'],
                ],
                'expected' => 'YES:FOO',
            ],

            [
                'template' => '{{#mywith foo}}YA: {{name}}{{/mywith}}',
                'data' => ['name' => 'OK?', 'foo' => ['name' => 'OK!']],
                'options' => [
                    'helpers' => ['mywith'],
                ],
                'expected' => 'YA: OK!',
            ],

            [
                'template' => '{{mydash \'abc\' "dev"}}',
                'data' => ['a' => 'a', 'b' => 'b', 'c' => ['c' => 'c'], 'd' => 'd', 'e' => 'e'],
                'options' => [
                    'helpers' => ['mydash'],
                ],
                'expected' => 'abc-dev',
            ],

            [
                'template' => '{{mydash \'a b c\' "d e f"}}',
                'data' => ['a' => 'a', 'b' => 'b', 'c' => ['c' => 'c'], 'd' => 'd', 'e' => 'e'],
                'options' => [
                    'flags' => LightnCandy::FLAG_ADVARNAME,
                    'helpers' => ['mydash'],
                ],
                'expected' => 'a b c-d e f',
            ],

            [
                'template' => '{{mydash "abc" (test_array 1)}}',
                'data' => ['a' => 'a', 'b' => 'b', 'c' => ['c' => 'c'], 'd' => 'd', 'e' => 'e'],
                'options' => [
                    'flags' => LightnCandy::FLAG_ADVARNAME,
                    'helpers' => ['mydash', 'test_array'],
                ],
                'expected' => 'abc-NOT_ARRAY',
            ],

            [
                'template' => '{{mydash "abc" (myjoin a b)}}',
                'data' => ['a' => 'a', 'b' => 'b', 'c' => ['c' => 'c'], 'd' => 'd', 'e' => 'e'],
                'options' => [
                    'flags' => LightnCandy::FLAG_ADVARNAME,
                    'helpers' => ['mydash', 'myjoin'],
                ],
                'expected' => 'abc-ab',
            ],

            [
                'template' => '{{#with people}}Yes , {{name}}{{else}}No, {{name}}{{/with}}',
                'data' => ['people' => ['name' => 'Peter'], 'name' => 'NoOne'],
                'options' => [
                    'flags' => LightnCandy::FLAG_ELSE,
                ],
                'expected' => 'Yes , Peter',
            ],

            [
                'template' => '{{#with people}}Yes , {{name}}{{else}}No, {{name}}{{/with}}',
                'data' => ['name' => 'NoOne'],
                'options' => [
                    'flags' => LightnCandy::FLAG_ELSE,
                ],
                'expected' => 'No, NoOne',
            ],

            [
                'template' => <<<VAREND
<ul>
 <li>1. {{helper1 name}}</li>
 <li>2. {{helper1 value}}</li>
 <li>3. {{myClass::helper2 name}}</li>
 <li>4. {{myClass::helper2 value}}</li>
 <li>5. {{he name}}</li>
 <li>6. {{he value}}</li>
 <li>7. {{h2 name}}</li>
 <li>8. {{h2 value}}</li>
 <li>9. {{link name}}</li>
 <li>10. {{link value}}</li>
 <li>11. {{alink url text}}</li>
 <li>12. {{{alink url text}}}</li>
</ul>
VAREND
                ,
                'data' => ['name' => 'John', 'value' => 10000, 'url' => 'http://yahoo.com', 'text' => 'You&Me!'],
                'options' => [
                    'flags' => LightnCandy::FLAG_ERROR_LOG | LightnCandy::FLAG_HANDLEBARSJS,
                    'helpers' => [
                        'helper1',
                        'myClass::helper2',
                        'he' => 'helper1',
                        'h2' => 'myClass::helper2',
                        'link' => function ($arg) {
                            if (is_array($arg)) {
                                $arg = 'Array';
                            }
                            return "<a href=\"{$arg}\">click here</a>";
                        },
                        'alink',
                    ],
                ],
                'expected' => <<<VAREND
<ul>
 <li>1. -John-</li>
 <li>2. -10000-</li>
 <li>3. &#x3D;John&#x3D;</li>
 <li>4. &#x3D;10000&#x3D;</li>
 <li>5. -John-</li>
 <li>6. -10000-</li>
 <li>7. &#x3D;John&#x3D;</li>
 <li>8. &#x3D;10000&#x3D;</li>
 <li>9. &lt;a href&#x3D;&quot;John&quot;&gt;click here&lt;/a&gt;</li>
 <li>10. &lt;a href&#x3D;&quot;10000&quot;&gt;click here&lt;/a&gt;</li>
 <li>11. &lt;a href&#x3D;&quot;http://yahoo.com&quot;&gt;You&amp;Me!&lt;/a&gt;</li>
 <li>12. <a href="http://yahoo.com">You&Me!</a></li>
</ul>
VAREND
            ],

            [
                'template' => '{{test.test}} == {{test.test3}}',
                'data' => ['test' => new myClass()],
                'options' => ['flags' => LightnCandy::FLAG_INSTANCE],
                'expected' => "testMethod OK! == -- test3:Array\n(\n)\n",
            ],

            [
                'template' => '{{test.test}} == {{test.bar}}',
                'data' => ['test' => new foo()],
                'options' => ['flags' => LightnCandy::FLAG_INSTANCE],
                'expected' => ' == OK!',
            ],

            [
                'template' => '{{#each foo}}{{@key}}: {{.}},{{/each}}',
                'data' => ['foo' => [1,'a' => 'b',5]],
                'expected' => ': 1,: b,: 5,',
            ],

            [
                'template' => '{{#each foo}}{{@key}}: {{.}},{{/each}}',
                'data' => ['foo' => [1,'a' => 'b',5]],
                'options' => ['flags' => LightnCandy::FLAG_SPVARS],
                'expected' => '0: 1,a: b,1: 5,',
            ],

            [
                'template' => '{{#each foo}}{{@key}}: {{.}},{{/each}}',
                'data' => ['foo' => new twoDimensionIterator(2, 3)],
                'options' => ['flags' => LightnCandy::FLAG_SPVARS],
                'expected' => '0x0: 0,1x0: 0,0x1: 0,1x1: 1,0x2: 0,1x2: 2,',
            ],

            [
                'template' => "   {{#foo}}\n {{name}}\n{{/foo}}\n  ",
                'data' => ['foo' => [['name' => 'A'],['name' => 'd'],['name' => 'E']]],
                'options' => ['flags' => LightnCandy::FLAG_MUSTACHE],
                'expected' => " A\n d\n E\n  ",
            ],

            [
                'template' => "{{bar}}\n   {{#foo}}\n {{name}}\n{{/foo}}\n  ",
                'data' => ['bar' => 'OK', 'foo' => [['name' => 'A'],['name' => 'd'],['name' => 'E']]],
                'options' => ['flags' => LightnCandy::FLAG_MUSTACHE],
                'expected' => "OK\n A\n d\n E\n  ",
            ],

            [
                'template' => "   {{#if foo}}\nYES\n{{else}}\nNO\n{{/if}}\n",
                'options' => ['flags' => LightnCandy::FLAG_HANDLEBARS],
                'expected' => "NO\n",
            ],

            [
                'template' => "  {{#each foo}}\n{{@key}}: {{.}}\n{{/each}}\nDONE",
                'data' => ['foo' => ['a' => 'A', 'b' => 'BOY!']],
                'options' => ['flags' => LightnCandy::FLAG_HANDLEBARS],
                'expected' => "a: A\nb: BOY!\nDONE",
            ],

            [
                'template' => "{{>test1}}\n  {{>test1}}\nDONE\n",
                'options' => [
                    'flags' => LightnCandy::FLAG_MUSTACHE,
                    'partials' => ['test1' => "1:A\n 2:B\n  3:C\n 4:D\n5:E\n"],
                ],
                'expected' => "1:A\n 2:B\n  3:C\n 4:D\n5:E\n  1:A\n   2:B\n    3:C\n   4:D\n  5:E\nDONE\n",
            ],

            [
                'template' => "{{>test1}}\n  {{>test1}}\nDONE\n",
                'options' => [
                    'flags' => LightnCandy::FLAG_MUSTACHE | LightnCandy::FLAG_PREVENTINDENT,
                    'partials' => ['test1' => "1:A\n 2:B\n  3:C\n 4:D\n5:E\n"],
                ],
                'expected' => "1:A\n 2:B\n  3:C\n 4:D\n5:E\n  1:A\n 2:B\n  3:C\n 4:D\n5:E\nDONE\n",
            ],

            [
                'template' => "{{foo}}\n  {{bar}}\n",
                'data' => ['foo' => 'ha', 'bar' => 'hey'],
                'options' => [
                    'flags' => LightnCandy::FLAG_MUSTACHE | LightnCandy::FLAG_PREVENTINDENT,
                ],
                'expected' => "ha\n  hey\n",
            ],

            [
                'template' => "{{>test}}\n",
                'data' => ['foo' => 'ha', 'bar' => 'hey'],
                'options' => [
                    'flags' => LightnCandy::FLAG_MUSTACHE | LightnCandy::FLAG_PREVENTINDENT,
                    'partials' => ['test' => "{{foo}}\n  {{bar}}\n"],
                ],
                'expected' => "ha\n  hey\n",
            ],

            [
                'template' => " {{>test}}\n",
                'data' => ['foo' => 'ha', 'bar' => 'hey'],
                'options' => [
                    'flags' => LightnCandy::FLAG_MUSTACHE | LightnCandy::FLAG_PREVENTINDENT,
                    'partials' => ['test' => "{{foo}}\n  {{bar}}\n"],
                ],
                'expected' => " ha\n  hey\n",
            ],

            [
                'template' => "\n {{>test}}\n",
                'data' => ['foo' => 'ha', 'bar' => 'hey'],
                'options' => [
                    'flags' => LightnCandy::FLAG_MUSTACHE | LightnCandy::FLAG_PREVENTINDENT,
                    'partials' => ['test' => "{{foo}}\n  {{bar}}\n"],
                ],
                'expected' => "\n ha\n  hey\n",
            ],

            [
                'template' => "\n{{#each foo~}}\n  <li>{{.}}</li>\n{{~/each}}\n\nOK",
                'data' => ['foo' => ['ha', 'hu']],
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                ],
                'expected' => "\n<li>ha</li><li>hu</li>\nOK",
            ],

            [
                'template' => "ST:\n{{#foo}}\n {{>test1}}\n{{/foo}}\nOK\n",
                'data' => ['foo' => [1, 2]],
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                    'partials' => ['test1' => "1:A\n 2:B({{@index}})\n"],
                ],
                'expected' => "ST:\n 1:A\n  2:B(0)\n 1:A\n  2:B(1)\nOK\n",
            ],

            [
                'template' => '>{{helper1 "==="}}<',
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                    'helpers' => [
                        'helper1',
                    ],
                ],
                'expected' => '>-&#x3D;&#x3D;&#x3D;-<',
            ],

            [
                'template' => '{{foo}}',
                'data' => ['foo' => 'A&B " \''],
                'options' => ['flags' => LightnCandy::FLAG_NOESCAPE],
                'expected' => "A&B \" '",
            ],

            [
                'template' => '{{foo}}',
                'data' => ['foo' => 'A&B " \' ='],
                'expected' => 'A&amp;B &quot; &#039; =',
            ],

            [
                'template' => '{{foo}}',
                'data' => ['foo' => '<a href="#">\'</a>'],
                'options' => [
                    'flags' => LightnCandy::FLAG_HBESCAPE,
                ],
                'expected' => '&lt;a href&#x3D;&quot;#&quot;&gt;&#x27;&lt;/a&gt;',
            ],

            [
                'template' => '{{#if}}SHOW:{{.}} {{/if}}',
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_NOHBHELPERS,
                ],
                'data' => ['if' => [1, 3, 7], 'a' => [2, 4, 9]],
                'expected' => 'SHOW:1 SHOW:3 SHOW:7 ',
            ],

            [
                'template' => '{{#unless}}SHOW:{{.}} {{/unless}}',
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_NOHBHELPERS,
                ],
                'data' => ['unless' => [1, 3, 7], 'a' => [2, 4, 9]],
                'expected' => 'SHOW:1 SHOW:3 SHOW:7 ',
            ],

            [
                'template' => '{{#each}}SHOW:{{.}} {{/each}}',
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_NOHBHELPERS,
                ],
                'data' => ['each' => [1, 3, 7], 'a' => [2, 4, 9]],
                'expected' => 'SHOW:1 SHOW:3 SHOW:7 ',
            ],

            [
                'template' => '{{#>foo}}inline\'partial{{/foo}}',
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS_FULL,
                ],
                'expected' => 'inline\'partial',
            ],

            [
                'template' => '{{>foo}} and {{>bar}}',
                'options' => [
                    'partialresolver' => function ($context, $name) {
                        return "PARTIAL: $name";
                    },
                ],
                'expected' => 'PARTIAL: foo and PARTIAL: bar',
            ],

            [
                'template' => "{{#> testPartial}}\n ERROR: testPartial is not found!\n  {{#> innerPartial}}\n   ERROR: innerPartial is not found!\n   ERROR: innerPartial is not found!\n  {{/innerPartial}}\n ERROR: testPartial is not found!\n {{/testPartial}}",
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARS | LightnCandy::FLAG_RUNTIMEPARTIAL,
                ],
                'expected' => " ERROR: testPartial is not found!\n   ERROR: innerPartial is not found!\n   ERROR: innerPartial is not found!\n ERROR: testPartial is not found!\n",
            ],

        ];

        return array_map(function ($i) {
            if (!isset($i['debug'])) {
                $i['debug'] = 0;
            }
            return [$i];
        }, $issues);
    }
}
