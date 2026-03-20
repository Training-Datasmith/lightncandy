<?php

declare(strict_types=1);

use LightnCandy\LightnCandy;
use LightnCandy\Runtime;
use PHPUnit\Framework\TestCase;

require_once('tests/helpers_for_test.php');

$tmpdir = sys_get_temp_dir();
$errlog_fn = tempnam($tmpdir, 'terr_');

function start_catch_error_log()
{
    global $errlog_fn;
    date_default_timezone_set('GMT');
    if (file_exists($errlog_fn)) {
        unlink($errlog_fn);
    }
    return ini_set('error_log', $errlog_fn);
}

function stop_catch_error_log()
{
    global $errlog_fn;
    ini_restore('error_log');
    if (!file_exists($errlog_fn)) {
        return null;
    }
    return array_map(function ($l) {
        $l = rtrim($l);
        preg_match('/GMT\] (.+)/', $l, $m);
        return isset($m[1]) ? $m[1] : $l;
    }, file($errlog_fn));
}

class errorTest extends TestCase
{
    public function testException()
    {
        try {
            $php = LightnCandy::compile('{{{foo}}', ['flags' => LightnCandy::FLAG_ERROR_EXCEPTION]);
        } catch (\Exception $E) {
            $this->assertEquals('Bad token {{{foo}} ! Do you mean {{foo}} or {{{foo}}}?', $E->getMessage());
        }
    }

    public function testErrorLog()
    {
        start_catch_error_log();
        $php = LightnCandy::compile('{{{foo}}', ['flags' => LightnCandy::FLAG_ERROR_LOG]);
        $e = stop_catch_error_log();
        if ($e) {
            $this->assertEquals(['Bad token {{{foo}} ! Do you mean {{foo}} or {{{foo}}}?'], $e);
        } else {
            $this->markTestIncomplete('skip HHVM');
        }
    }

    public function testLog()
    {
        $php = LightnCandy::compile('{{log foo}}');
        $renderer = LightnCandy::prepare($php);
        start_catch_error_log();
        $renderer(['foo' => 'OK!']);
        $e = stop_catch_error_log();
        if ($e) {
            $this->assertEquals(['array (', "  0 => 'OK!',", ')'], $e);
        } else {
            $this->markTestIncomplete('skip HHVM');
        }
    }

    /**
     * @dataProvider renderErrorProvider
     */
    public function testRenderingException($test)
    {
        $php = LightnCandy::compile($test['template'], $test['options']);
        $renderer = LightnCandy::prepare($php);
        try {
            $input = isset($test['data']) ? $test['data'] : null;
            $renderer($input, ['debug' => Runtime::DEBUG_ERROR_EXCEPTION]);
        } catch (\Exception $E) {
            $this->assertEquals($test['expected'], $E->getMessage());
            return;
        }
        $this->fail("Expected to throw exception: {$test['expected']} . CODE: $php");
    }

    /**
     * @dataProvider renderErrorProvider
     */
    public function testRenderingErrorLog($test)
    {
        start_catch_error_log();
        $php = LightnCandy::compile($test['template'], $test['options']);
        $renderer = LightnCandy::prepare($php);
        try {
            $in = ['dummy' => 'reference'];
            $renderer($in, ['debug' => Runtime::DEBUG_ERROR_LOG]);
        } catch (\Exception $E) {
            $this->fail('Unexpected render exception: ' . $E->getMessage() . ", CODE: $php");
        }
        $e = stop_catch_error_log();
        if ($e) {
            $this->assertEquals([$test['expected']], $e);
        } else {
            $this->markTestIncomplete('skip HHVM');
        }
    }

    public function renderErrorProvider()
    {
        $errorCases = [
             [
                 'template' => "{{#> testPartial}}\n  {{#> innerPartial}}\n   {{> @partial-block}}\n  {{/innerPartial}}\n{{/testPartial}}",
                 'options' => [
                   'flags' => LightnCandy::FLAG_HANDLEBARS | LightnCandy::FLAG_RUNTIMEPARTIAL | LightnCandy::FLAG_ERROR_SKIPPARTIAL,
                   'partials' => [
                     'testPartial' => 'testPartial => {{> @partial-block}} <=',
                     'innerPartial' => 'innerPartial -> {{> @partial-block}} <-',
                   ],
                 ],
                 'expected' => "Can not find partial named as '@partial-block' !!",
             ],
             [
                 'template' => '{{> abc}}',
                 'options' => [
                   'flags' => LightnCandy::FLAG_HANDLEBARS | LightnCandy::FLAG_RUNTIMEPARTIAL | LightnCandy::FLAG_ERROR_SKIPPARTIAL,
                 ],
                 'expected' => "Can not find partial named as 'abc' !!",
             ],
             [
                 'template' => '{{> @partial-block}}',
                 'options' => [
                   'flags' => LightnCandy::FLAG_HANDLEBARS | LightnCandy::FLAG_RUNTIMEPARTIAL,
                 ],
                 'expected' => "Can not find partial named as '@partial-block' !!",
             ],
             [
                 'template' => '{{{foo}}}',
                 'expected' => 'Runtime: [foo] does not exist',
             ],
             [
                 'template' => '{{foo}}',
                 'options' => [
                     'helpers' => [
                         'foo' => function () {
                             return 1 / 0;
                         },
                     ],
                 ],
                 'expected' => 'Runtime: call custom helper \'foo\' error: Division by zero',
             ],
        ];

        return array_map(function ($i) {
            if (!isset($i['options'])) {
                $i['options'] = ['flags' => LightnCandy::FLAG_RENDER_DEBUG];
            }
            if (!isset($i['options']['flags'])) {
                $i['options']['flags'] = LightnCandy::FLAG_RENDER_DEBUG;
            }
            return [$i];
        }, $errorCases);
    }

    /**
     * @dataProvider errorProvider
     */
    public function testErrors($test)
    {
        global $tmpdir;

        $php = LightnCandy::compile($test['template'], $test['options']);
        $context = LightnCandy::getContext();

        // This case should be compiled without error
        if (!isset($test['expected'])) {
            $this->assertEquals(true, true);
            return;
        }

        $this->assertEquals($test['expected'], $context['error'], "Code: $php");
    }

    public function errorProvider()
    {
        $errorCases = [
            [
                'template' => '{{testerr1}}}',
                'expected' => 'Bad token {{testerr1}}} ! Do you mean {{testerr1}} or {{{testerr1}}}?',
            ],
            [
                'template' => '{{{testerr2}}',
                'expected' => 'Bad token {{{testerr2}} ! Do you mean {{testerr2}} or {{{testerr2}}}?',
            ],
            [
                'template' => '{{{#testerr3}}}',
                'expected' => 'Bad token {{{#testerr3}}} ! Do you mean {{#testerr3}} ?',
            ],
            [
                'template' => '{{{!testerr4}}}',
                'expected' => 'Bad token {{{!testerr4}}} ! Do you mean {{!testerr4}} ?',
            ],
            [
                'template' => '{{{^testerr5}}}',
                'expected' => 'Bad token {{{^testerr5}}} ! Do you mean {{^testerr5}} ?',
            ],
            [
                'template' => '{{{/testerr6}}}',
                'expected' => 'Bad token {{{/testerr6}}} ! Do you mean {{/testerr6}} ?',
            ],
            [
                'template' => '{{win[ner.test1}}',
                'options' => ['flags' => LightnCandy::FLAG_ADVARNAME],
                'expected' => [
                    "Error in 'win[ner.test1': expect ']' but the token ended!!",
                    'Wrong variable naming in {{win[ner.test1}}',
                ],
            ],
            [
                'template' => '{{win]ner.test2}}',
                'options' => ['flags' => LightnCandy::FLAG_ADVARNAME],
                'expected' => 'Wrong variable naming as \'win]ner.test2\' in {{win]ner.test2}} !',
            ],
            [
                'template' => '{{wi[n]ner.test3}}',
                'options' => ['flags' => LightnCandy::FLAG_ADVARNAME],
                'expected' => [
                    'Wrong variable naming as \'wi[n]ner.test3\' in {{wi[n]ner.test3}} !',
                    "Unexpected charactor in 'wi[n]ner.test3' ! (should it be 'wi.[n].ner.test3' ?)",
                ],
            ],
            [
                'template' => '{{winner].[test4]}}',
                'options' => ['flags' => LightnCandy::FLAG_ADVARNAME],
                'expected' => [
                    'Wrong variable naming as \'winner].[test4]\' in {{winner].[test4]}} !',
                    "Unexpected charactor in 'winner].[test4]' ! (should it be 'winner.[test4]' ?)",
                ],
            ],
            [
                'template' => '{{winner[.test5]}}',
                'options' => ['flags' => LightnCandy::FLAG_ADVARNAME],
                'expected' => [
                    'Wrong variable naming as \'winner[.test5]\' in {{winner[.test5]}} !',
                    "Unexpected charactor in 'winner[.test5]' ! (should it be 'winner.[.test5]' ?)",
                ],
            ],
            [
                'template' => '{{winner.[.test6]}}',
                'options' => ['flags' => LightnCandy::FLAG_ADVARNAME],
            ],
            [
                'template' => '{{winner.[#te.st7]}}',
                'options' => ['flags' => LightnCandy::FLAG_ADVARNAME],
            ],
            [
                'template' => '{{test8}}',
                'options' => ['flags' => LightnCandy::FLAG_ADVARNAME],
            ],
            [
                'template' => '{{test9]}}',
                'options' => ['flags' => LightnCandy::FLAG_ADVARNAME],
                'expected' => [
                    'Wrong variable naming as \'test9]\' in {{test9]}} !',
                    "Unexpected charactor in 'test9]' ! (should it be 'test9' ?)",
                ],
            ],
            [
                'template' => '{{testA[}}',
                'options' => ['flags' => LightnCandy::FLAG_ADVARNAME],
                'expected' => [
                    "Error in 'testA[': expect ']' but the token ended!!",
                    'Wrong variable naming in {{testA[}}',
                ],
            ],
            [
                'template' => '{{[testB}}',
                'options' => ['flags' => LightnCandy::FLAG_ADVARNAME],
                'expected' => [
                    "Error in '[testB': expect ']' but the token ended!!",
                    'Wrong variable naming in {{[testB}}',
                ],
            ],
            [
                'template' => '{{]testC}}',
                'options' => ['flags' => LightnCandy::FLAG_ADVARNAME],
                'expected' => [
                    'Wrong variable naming as \']testC\' in {{]testC}} !',
                    "Unexpected charactor in ']testC' ! (should it be 'testC' ?)",
                ],
            ],
            [
                'template' => '{{[testD]}}',
                'options' => ['flags' => LightnCandy::FLAG_ADVARNAME],
            ],
            [
                'template' => '{{te]stE}}',
                'options' => ['flags' => LightnCandy::FLAG_ADVARNAME],
                'expected' => 'Wrong variable naming as \'te]stE\' in {{te]stE}} !',
            ],
            [
                'template' => '{{tee[stF}}',
                'options' => ['flags' => LightnCandy::FLAG_ADVARNAME],
                'expected' => [
                    "Error in 'tee[stF': expect ']' but the token ended!!",
                    'Wrong variable naming in {{tee[stF}}',
                ],
            ],
            [
                'template' => '{{te.e[stG}}',
                'options' => ['flags' => LightnCandy::FLAG_ADVARNAME],
                'expected' => [
                    "Error in 'te.e[stG': expect ']' but the token ended!!",
                    'Wrong variable naming in {{te.e[stG}}',
                ],
            ],
            [
                'template' => '{{te.e]stH}}',
                'options' => ['flags' => LightnCandy::FLAG_ADVARNAME],
                'expected' => 'Wrong variable naming as \'te.e]stH\' in {{te.e]stH}} !',
            ],
            [
                'template' => '{{te.e[st.endI}}',
                'options' => ['flags' => LightnCandy::FLAG_ADVARNAME],
                'expected' => [
                    "Error in 'te.e[st.endI': expect ']' but the token ended!!",
                    'Wrong variable naming in {{te.e[st.endI}}',
                ],
            ],
            [
                'template' => '{{te.e]st.endJ}}',
                'options' => ['flags' => LightnCandy::FLAG_ADVARNAME],
                'expected' => 'Wrong variable naming as \'te.e]st.endJ\' in {{te.e]st.endJ}} !',
            ],
            [
                'template' => '{{te.[est].endK}}',
                'options' => ['flags' => LightnCandy::FLAG_ADVARNAME],
            ],
            [
                'template' => '{{te.t[est].endL}}',
                'options' => ['flags' => LightnCandy::FLAG_ADVARNAME],
                'expected' => [
                    'Wrong variable naming as \'te.t[est].endL\' in {{te.t[est].endL}} !',
                    "Unexpected charactor in 'te.t[est].endL' ! (should it be 'te.t.[est].endL' ?)",
                ],
            ],
            [
                'template' => '{{te.t[est]o.endM}}',
                'options' => ['flags' => LightnCandy::FLAG_ADVARNAME],
                'expected' => [
                    'Wrong variable naming as \'te.t[est]o.endM\' in {{te.t[est]o.endM}} !',
                    "Unexpected charactor in 'te.t[est]o.endM' ! (should it be 'te.t.[est].o.endM' ?)",
                ],
            ],
            [
                'template' => '{{te.[est]o.endN}}',
                'options' => ['flags' => LightnCandy::FLAG_ADVARNAME],
                'expected' => [
                    'Wrong variable naming as \'te.[est]o.endN\' in {{te.[est]o.endN}} !',
                    "Unexpected charactor in 'te.[est]o.endN' ! (should it be 'te.[est].o.endN' ?)",
                ],
            ],
            [
                'template' => '{{te.[e.st].endO}}',
                'options' => ['flags' => LightnCandy::FLAG_ADVARNAME],
            ],
            [
                'template' => '{{te.[e.s[t].endP}}',
                'options' => ['flags' => LightnCandy::FLAG_ADVARNAME],
            ],
            [
                'template' => '{{te.[e[s.t].endQ}}',
                'options' => ['flags' => LightnCandy::FLAG_ADVARNAME],
            ],
            [
                'template' => '{{helper}}',
                'options' => ['helpers' => [
                    'helper' => ['bad input'],
                ]],
                'expected' => 'I found an array in helpers with key as helper, please fix it.',
            ],
            [
                'template' => '<ul>{{#each item}}<li>{{name}}</li>',
                'expected' => 'Unclosed token {{#each item}} !!',
            ],
            [
                'template' => 'issue63: {{test_join}} Test! {{this}} {{/test_join}}',
                'expected' => 'Unexpect token: {{/test_join}} !',
            ],
            [
                'template' => '{{#if a}}TEST{{/with}}',
                'expected' => 'Unexpect token: {{/with}} !',
            ],
            [
                'template' => '{{#foo}}error{{/bar}}',
                'expected' => 'Unexpect token {{/bar}} ! Previous token {{#[foo]}} is not closed',
            ],
            [
                'template' => '{{../foo}}',
                'expected' => 'Do not support {{../var}}, you should do compile with LightnCandy::FLAG_PARENT flag',
            ],
            [
                'template' => '{{..}}',
                'expected' => 'Do not support {{../var}}, you should do compile with LightnCandy::FLAG_PARENT flag',
            ],
            [
                'template' => '{{test_join [a]=b}}',
                'options' => [
                    'flags' => LightnCandy::FLAG_NAMEDARG,
                    'helpers' => ['test_join'],
                ],
                'expected' => "Wrong argument name as '[a]' in {{test_join [a]=b}} ! You should fix your template or compile with LightnCandy::FLAG_ADVARNAME flag.",
            ],
            [
                'template' => '{{a=b}}',
                'options' => ['flags' => LightnCandy::FLAG_NAMEDARG],
                'expected' => 'Do not support name=value in {{a=b}}, you should use it after a custom helper.',
            ],
            [
                'template' => '{{#foo}}1{{^}}2{{/foo}}',
                'expected' => 'Do not support {{^}}, you should do compile with LightnCandy::FLAG_ELSE flag',
            ],
            [
                'template' => '{{#with a}OK!{{/with}}',
                'expected' => 'Unclosed token {{#with a}OK!{{/with}} !!',
            ],
            [
                'template' => '{{#each a}OK!{{/each}}',
                'expected' => 'Unclosed token {{#each a}OK!{{/each}} !!',
            ],
            [
                'template' => '{{#with items}}OK!{{/with}}',
            ],
            [
                'template' => '{{#with}}OK!{{/with}}',
                'expected' => 'No argument after {{#with}} !',
            ],
            [
                'template' => '{{#if}}OK!{{/if}}',
                'expected' => 'No argument after {{#if}} !',
            ],
            [
                'template' => '{{#unless}}OK!{{/unless}}',
                'expected' => 'No argument after {{#unless}} !',
            ],
            [
                'template' => '{{#each}}OK!{{/each}}',
                'expected' => 'No argument after {{#each}} !',
            ],
            [
                'template' => '{{lookup}}',
                'expected' => 'No argument after {{lookup}} !',
            ],
            [
                'template' => '{{lookup foo}}',
                'expected' => '{{lookup}} requires 2 arguments !',
            ],
            [
                'template' => '{{#test foo}}{{/test}}',
                'expected' => 'Custom helper not found: test in {{#test foo}} !',
            ],
            [
                'template' => '{{>not_found}}',
                'expected' => "Can not find partial for 'not_found', you should provide partials or partialresolver in options",
            ],
            [
                'template' => '{{>tests/test1 foo}}',
                'options' => ['partials' => ['tests/test1' => '']],
                'expected' => 'Do not support {{>tests/test1 foo}}, you should do compile with LightnCandy::FLAG_RUNTIMEPARTIAL flag',
            ],
            [
                'template' => '{{#with foo}}ABC{{/with}}',
                'options' => ['flags' => LightnCandy::FLAG_NOHBHELPERS],
                'expected' => 'Do not support {{#with var}} because you compile with LightnCandy::FLAG_NOHBHELPERS flag',
            ],
            [
                'template' => '{{#if foo}}ABC{{/if}}',
                'options' => ['flags' => LightnCandy::FLAG_NOHBHELPERS],
                'expected' => 'Do not support {{#if var}} because you compile with LightnCandy::FLAG_NOHBHELPERS flag',
            ],
            [
                'template' => '{{#unless foo}}ABC{{/unless}}',
                'options' => ['flags' => LightnCandy::FLAG_NOHBHELPERS],
                'expected' => 'Do not support {{#unless var}} because you compile with LightnCandy::FLAG_NOHBHELPERS flag',
            ],
            [
                'template' => '{{#each foo}}ABC{{/each}}',
                'options' => ['flags' => LightnCandy::FLAG_NOHBHELPERS],
                'expected' => 'Do not support {{#each var}} because you compile with LightnCandy::FLAG_NOHBHELPERS flag',
            ],
            [
                'template' => '{{abc}}',
                'options' => ['helpers' => ['abc']],
                'expected' => "You provide a custom helper named as 'abc' in options['helpers'], but the function abc() is not defined!",
            ],
            [
                'template' => '{{=~= =~=}}',
                'expected' => "Can not set delimiter contains '=' , you try to set delimiter as '~=' and '=~'.",
            ],
            [
                'template' => '{{>recursive}}',
                'options' => ['partials' => ['recursive' => '{{>recursive}}']],
                'expected' => [
                    'I found recursive partial includes as the path: recursive -> recursive! You should fix your template or compile with LightnCandy::FLAG_RUNTIMEPARTIAL flag.',
                ],
            ],
            [
                'template' => '{{test_join (foo bar)}}',
                'options' => [
                    'flags' => LightnCandy::FLAG_ADVARNAME,
                    'helpers' => ['test_join'],
                ],
                'expected' => 'Can not find custom helper function defination foo() !',
            ],
            [
                'template' => '{{1 + 2}}',
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                    'helpers' => ['test_join'],
                ],
                'expected' => "Wrong variable naming as '+' in {{1 + 2}} ! You should wrap ! \" # % & ' * + , ; < = > { | } ~ into [ ]",
            ],
            [
                'template' => '{{> (foo) bar}}',
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                ],
                'expected' => [
                    'Can not find custom helper function defination foo() !',
                    "You use dynamic partial name as '(foo)', this only works with option FLAG_RUNTIMEPARTIAL enabled",
                ],
            ],
            [
                'template' => '{{{{#foo}}}',
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                ],
                'expected' => [
                    'Bad token {{{{#foo}}} ! Do you mean {{{{#foo}}}} ?',
                    'Wrong raw block begin with {{{{#foo}}} ! Remove "#" to fix this issue.',
                    'Unclosed token {{{{foo}}}} !!',
                ],
            ],
            [
                'template' => '{{{{foo}}}} {{ {{{{/bar}}}}',
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                ],
                'expected' => [
                    'Unclosed token {{{{foo}}}} !!',
                ],
            ],
            [
                'template' => '{{foo (foo (foo 1 2) 3))}}',
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                     'helpers' => [
                         'foo' => function () {
                             return;
                         },
                     ],
                ],
                'expected' => [
                    'Unexcepted \')\' in expression \'foo (foo (foo 1 2) 3))\' !!',
                ],
            ],
            [
                'template' => '{{{{foo}}}} {{ {{{{#foo}}}}',
                'options' => [
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                ],
                'expected' => [
                    'Unclosed token {{{{foo}}}} !!',
                ],
            ],
            [
                'template' => '{{else}}',
                'options' => [
                    'flags' => LightnCandy::FLAG_ELSE,
                ],
                'expected' => [
                    '{{else}} only valid in if, unless, each, and #section context',
                ],
            ],
            [
                'template' => '{{log}}',
                'expected' => [
                    'No argument after {{log}} !',
                ],
            ],
            [
                'template' => '{{#*inline test}}{{/inline}}',
                'expected' => [
                    'Do not support {{#*inline test}}, you should do compile with LightnCandy::FLAG_RUNTIMEPARTIAL flag',
                ],
            ],
            [
                'template' => '{{#*help me}}{{/help}}',
                'options' => [
                    'flags' => LightnCandy::FLAG_RUNTIMEPARTIAL,
                ],
                'expected' => [
                    'Do not support {{#*help me}}, now we only support {{#*inline "partialName"}}template...{{/inline}}',
                ],
            ],
            [
                'template' => '{{#*inline}}{{/inline}}',
                'options' => [
                    'flags' => LightnCandy::FLAG_RUNTIMEPARTIAL,
                ],
                'expected' => [
                    'Error in {{#*inline}}: inline require 1 argument for partial name!',
                ],
            ],
            [
                'template' => '{{#>foo}}bar',
                'options' => [
                    'flags' => LightnCandy::FLAG_RUNTIMEPARTIAL,
                ],
                'expected' => [
                    'Unclosed token {{#>foo}} !!',
                ],
            ],
            [
                'template' => '{{ #2 }}',
                'options' => [
                    'flags' => LightnCandy::FLAG_BESTPERFORMANCE,
                ],
                'expected' => [
                    'Unclosed token {{#2}} !!',
                ],
            ],
            [
                'template' => '{{foo a=b}}',
                'options' => [
                    'flags' => LightnCandy::FLAG_ADVARNAME,
                ],
                'expected' => [
                    "Wrong variable naming as 'a=b' in {{foo a=b}} ! If you try to use foo=bar param, you should enable LightnCandy::FLAG_NAMEDARG !",
                ],
            ],
        ];

        return array_map(function ($i) {
            if (!isset($i['options'])) {
                $i['options'] = ['flags' => 0];
            }
            if (!isset($i['options']['flags'])) {
                $i['options']['flags'] = 0;
            }
            if (isset($i['expected']) && !is_array($i['expected'])) {
                $i['expected'] = [$i['expected']];
            }
            return [$i];
        }, $errorCases);
    }
}
