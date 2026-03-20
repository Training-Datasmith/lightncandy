<?php

declare (strict_types=1);
/*

MIT License
Copyright 2013-2021 Zordius Chen. All Rights Reserved.
Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:
The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.
THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.

Origin: https://github.com/zordius/lightncandy
*/
/**
 * file of LightnCandy Compiler
 *
 * @package    LightnCandy
 * @author     Zordius <zordius@gmail.com>
 */
namespace Lightn_Candy;

/**
 * LightnCandy Compiler
 */
class Compiler extends Validator
{
    public static $last_parsed;
    /**
     * Compile template into PHP code
     *
     * @param array<string,array|string|integer> $context Current context
     * @param string $template handlebars template
     *
     * @return string|null generated PHP code
     */
    public static function compile_template(array &$context, $template)
    {
        array_unshift($context['parsed'], []);
        Validator::verify($context, $template);
        static::$last_parsed = $context['parsed'];
        if (count($context['error'])) {
            return;
        }
        Parser::set_delimiter($context);
        $context['compile'] = true;
        // Handle dynamic partials
        Partial::handle_dynamic($context);
        // Do PHP code generation.
        $code = '';
        foreach ($context['parsed'][0] as $info) {
            if (is_array($info)) {
                $context['tokens']['current']++;
                $code .= "'" . static::compile_token($context, $info) . "'";
            } else {
                $code .= $info;
            }
        }
        array_shift($context['parsed']);
        return $code;
    }
    /**
     * Compose LightnCandy render codes for include()
     *
     * @param array<string,array|string|integer> $context Current context
     * @param string $code generated PHP code
     *
     * @return string Composed PHP code
     */
    public static function compose_php_render(array $context, $code): string
    {
        $flag_j_strue = Expression::bool_string($context['flags']['jstrue']);
        $flag_js_obj = Expression::bool_string($context['flags']['jsobj']);
        $flag_js_len = Expression::bool_string($context['flags']['jslen']);
        $flag_sp_var = Expression::bool_string($context['flags']['spvar']);
        $flag_prop = Expression::bool_string($context['flags']['prop']);
        $flag_method = Expression::bool_string($context['flags']['method']);
        $flag_lambda = Expression::bool_string($context['flags']['lambda']);
        $flag_mustlok = Expression::bool_string($context['flags']['mustlok']);
        $flag_mustlam = Expression::bool_string($context['flags']['mustlam']);
        $flag_mustsec = Expression::bool_string($context['flags']['mustsec']);
        $flag_echo = Expression::bool_string($context['flags']['echo']);
        $flag_part_nc = Expression::bool_string($context['flags']['partnc']);
        $flag_known_hlp = Expression::bool_string($context['flags']['knohlp']);
        $constants = Exporter::constants($context);
        $helpers = Exporter::helpers($context);
        $partials = implode(",\n", $context['partialCode']);
        $debug = Runtime::DEBUG_ERROR_LOG;
        $use = $context['flags']['standalone'] ? Exporter::runtime($context) : "use {$context['runtime']} as {$context['runtimealias']};";
        $string_object = $context['flags']['method'] || $context['flags']['prop'] ? Exporter::stringobject($context) : '';
        $safe_string = $context['usedFeature']['enc'] > 0 && $context['flags']['standalone'] === 0 ? "use {$context['safestring']} as SafeString;" : '';
        $export_safe_string = $context['usedFeature']['enc'] > 0 && $context['flags']['standalone'] > 0 ? Exporter::safestring($context) : '';
        // Return generated PHP code string.
        return <<<VAREND
        {$string_object}{$safe_string}{$use}{$export_safe_string}return function (\$in = null, \$options = null) {
            \$helpers = {$helpers};
            \$partials = array({$partials});
            \$cx = array(
                'flags' => array(
                    'jstrue' => {$flag_j_strue},
                    'jsobj' => {$flag_js_obj},
                    'jslen' => {$flag_js_len},
                    'spvar' => {$flag_sp_var},
                    'prop' => {$flag_prop},
                    'method' => {$flag_method},
                    'lambda' => {$flag_lambda},
                    'mustlok' => {$flag_mustlok},
                    'mustlam' => {$flag_mustlam},
                    'mustsec' => {$flag_mustsec},
                    'echo' => {$flag_echo},
                    'partnc' => {$flag_part_nc},
                    'knohlp' => {$flag_known_hlp},
                    'debug' => isset(\$options['debug']) ? \$options['debug'] : {$debug},
                ),
                'constants' => {$constants},
                'helpers' => isset(\$options['helpers']) ? array_merge(\$helpers, \$options['helpers']) : \$helpers,
                'partials' => isset(\$options['partials']) ? array_merge(\$partials, \$options['partials']) : \$partials,
                'scopes' => array(),
                'sp_vars' => isset(\$options['data']) ? array_merge(array('root' => \$in), \$options['data']) : array('root' => \$in),
                'blparam' => array(),
                'partialid' => 0,
                'runtime' => '{$context['runtime']}',
            );
            {$context['renderex']}
            {$context['ops']['array_check']}
            {$context['ops']['op_start']}'{$code}'{$context['ops']['op_end']}
        };
        VAREND;
    }
    /**
     * Get function name for standalone or none standalone template.
     *
     * @param array<string,array|string|integer> $context Current context of compiler progress.
     * @param string $name base function name
     * @param string $tag original handlabars tag for debug
     *
     * @return string compiled Function name
     *
     * @expect 'LR::test(' when input array('flags' => array('standalone' => 0, 'debug' => 0), 'runtime' => 'Runtime', 'runtimealias' => 'LR'), 'test', ''
     * @expect 'LL::test2(' when input array('flags' => array('standalone' => 0, 'debug' => 0), 'runtime' => 'Runtime', 'runtimealias' => 'LL'), 'test2', ''
     * @expect "lala_abctest3(" when input array('flags' => array('standalone' => 1, 'debug' => 0), 'runtime' => 'Runtime', 'runtimealias' => 0, 'funcprefix' => 'lala_abc'), 'test3', ''
     * @expect 'RR::debug(\'abc\', \'test\', ' when input array('flags' => array('standalone' => 0, 'debug' => 1), 'runtime' => 'Runtime', 'runtimealias' => 'RR', 'funcprefix' => 'haha456'), 'test', 'abc'
     */
    protected static function get_func_name(array &$context, $name, $tag): string
    {
        static::add_usage_count($context, 'runtime', $name);
        if ($context['flags']['debug'] && $name != 'miss') {
            $dbg = "'" . addcslashes($tag, "'\\") . "', '{$name}', ";
            $name = 'debug';
            static::add_usage_count($context, 'runtime', 'debug');
        } else {
            $dbg = '';
        }
        return $context['flags']['standalone'] ? "{$context['funcprefix']}{$name}({$dbg}" : "{$context['runtimealias']}::{$name}({$dbg}";
    }
    /**
     * Get string presentation of variables
     *
     * @param array<string,array|string|integer> $context current compile context
     * @param array<array> $vn variable name array.
     * @param array<string>|null $blockParams block param list
     *
     * @return array<string|array> variable names
     *
     * @expect array('array(array($in),array())', array('this')) when input array('flags'=>array('spvar'=>true)), array(null)
     * @expect array('array(array($in,$in),array())', array('this', 'this')) when input array('flags'=>array('spvar'=>true)), array(null, null)
     * @expect array('array(array(),array(\'a\'=>$in))', array('this')) when input array('flags'=>array('spvar'=>true)), array('a' => null)
     */
    protected static function get_variable_names(&$context, $vn, $block_params = null): array
    {
        $vars = [[], []];
        $exps = [];
        foreach ($vn as $i => $v) {
            $V = static::get_variable_name_or_sub_expression($context, $v);
            if (is_string($i)) {
                $vars[1][] = "'{$i}'=>{$V[0]}";
            } else {
                $vars[0][] = $V[0];
            }
            $exps[] = $V[1];
        }
        $bp = $block_params ? ',array(' . Expression::list_string($block_params) . ')' : '';
        return ['array(array(' . implode(',', $vars[0]) . '),array(' . implode(',', $vars[1]) . "){$bp})", $exps];
    }
    /**
     * Get string presentation of a sub expression
     *
     * @param array<string,array|string|integer> $context current compile context
     * @param array<boolean|integer|string|array> $vars parsed arguments list
     *
     * @return array<string> code representing passed expression
     */
    public static function compile_sub_expression(array &$context, $vars): array
    {
        $ret = static::custom_helper($context, $vars, true, true, true);
        if ($ret === null && $context['flags']['lambda']) {
            $ret = static::compile_variable($context, $vars, true, true);
        }
        return [$ret ?: '', 'FIXME: $subExpression'];
    }
    /**
     * Get string presentation of a subexpression or a variable
     *
     * @param array<array|string|integer> $context current compile context
     * @param array<array|string|integer> $var variable parsed path
     *
     * @return array<string> variable names
     */
    protected static function get_variable_name_or_sub_expression(&$context, ?array $var)
    {
        return Parser::is_sub_exp($var) ? static::compile_sub_expression($context, $var[1]) : static::get_variable_name($context, $var);
    }
    /**
     * Get string presentation of a variable
     *
     * @param array<array|string|integer> $var variable parsed path
     * @param array<array|string|integer> $context current compile context
     * @param array<string>|null $lookup extra lookup string as valid PHP variable name
     *
     * @return array<string> variable names
     *
     * @expect array('$in', 'this') when input array('flags'=>array('spvar'=>true,'debug'=>0)), array(null)
     * @expect array('(($inary && isset($in[\'true\'])) ? $in[\'true\'] : null)', '[true]') when input array('flags'=>array('spvar'=>true,'debug'=>0,'prop'=>0,'method'=>0,'mustlok'=>0,'mustlam'=>0,'lambda'=>0,'jslen'=>0)), array('true')
     * @expect array('(($inary && isset($in[\'false\'])) ? $in[\'false\'] : null)', '[false]') when input array('flags'=>array('spvar'=>true,'debug'=>0,'prop'=>0,'method'=>0,'mustlok'=>0,'mustlam'=>0,'lambda'=>0,'jslen'=>0)), array('false')
     * @expect array('true', 'true') when input array('flags'=>array('spvar'=>true,'debug'=>0)), array(-1, 'true')
     * @expect array('false', 'false') when input array('flags'=>array('spvar'=>true,'debug'=>0)), array(-1, 'false')
     * @expect array('(($inary && isset($in[\'2\'])) ? $in[\'2\'] : null)', '[2]') when input array('flags'=>array('spvar'=>true,'debug'=>0,'prop'=>0,'method'=>0,'mustlok'=>0,'mustlam'=>0,'lambda'=>0,'jslen'=>0)), array('2')
     * @expect array('2', '2') when input array('flags'=>array('spvar'=>true,'debug'=>0,'prop'=>0,'method'=>0)), array(-1, '2')
     * @expect array('(($inary && isset($in[\'@index\'])) ? $in[\'@index\'] : null)', '[@index]') when input array('flags'=>array('spvar'=>false,'debug'=>0,'prop'=>0,'method'=>0,'mustlok'=>0,'mustlam'=>0,'lambda'=>0,'jslen'=>0)), array('@index')
     * @expect array("(isset(\$cx['sp_vars']['index']) ? \$cx['sp_vars']['index'] : null)", '@[index]') when input array('flags'=>array('spvar'=>true,'debug'=>0,'prop'=>0,'method'=>0,'mustlok'=>0,'mustlam'=>0,'lambda'=>0,'jslen'=>0)), array('@index')
     * @expect array("(isset(\$cx['sp_vars']['key']) ? \$cx['sp_vars']['key'] : null)", '@[key]') when input array('flags'=>array('spvar'=>true,'debug'=>0,'prop'=>0,'method'=>0,'mustlok'=>0,'mustlam'=>0,'lambda'=>0,'jslen'=>0)), array('@key')
     * @expect array("(isset(\$cx['sp_vars']['first']) ? \$cx['sp_vars']['first'] : null)", '@[first]') when input array('flags'=>array('spvar'=>true,'debug'=>0,'prop'=>0,'method'=>0,'mustlok'=>0,'mustlam'=>0,'lambda'=>0,'jslen'=>0)), array('@first')
     * @expect array("(isset(\$cx['sp_vars']['last']) ? \$cx['sp_vars']['last'] : null)", '@[last]') when input array('flags'=>array('spvar'=>true,'debug'=>0,'prop'=>0,'method'=>0,'mustlok'=>0,'mustlam'=>0,'lambda'=>0,'jslen'=>0)), array('@last')
     * @expect array('(($inary && isset($in[\'"a"\'])) ? $in[\'"a"\'] : null)', '["a"]') when input array('flags'=>array('spvar'=>true,'debug'=>0,'prop'=>0,'method'=>0,'mustlok'=>0,'mustlam'=>0,'lambda'=>0,'jslen'=>0)), array('"a"')
     * @expect array('"a"', '"a"') when input array('flags'=>array('spvar'=>true,'debug'=>0)), array(-1, '"a"')
     * @expect array('(($inary && isset($in[\'a\'])) ? $in[\'a\'] : null)', '[a]') when input array('flags'=>array('spvar'=>true,'debug'=>0,'prop'=>0,'method'=>0,'mustlok'=>0,'mustlam'=>0,'lambda'=>0,'jslen'=>0)), array('a')
     * @expect array('((isset($cx[\'scopes\'][count($cx[\'scopes\'])-1]) && is_array($cx[\'scopes\'][count($cx[\'scopes\'])-1]) && isset($cx[\'scopes\'][count($cx[\'scopes\'])-1][\'a\'])) ? $cx[\'scopes\'][count($cx[\'scopes\'])-1][\'a\'] : null)', '../[a]') when input array('flags'=>array('spvar'=>true,'debug'=>0,'prop'=>0,'method'=>0,'mustlok'=>0,'mustlam'=>0,'lambda'=>0,'jslen'=>0)), array(1,'a')
     * @expect array('((isset($cx[\'scopes\'][count($cx[\'scopes\'])-3]) && is_array($cx[\'scopes\'][count($cx[\'scopes\'])-3]) && isset($cx[\'scopes\'][count($cx[\'scopes\'])-3][\'a\'])) ? $cx[\'scopes\'][count($cx[\'scopes\'])-3][\'a\'] : null)', '../../../[a]') when input array('flags'=>array('spvar'=>true,'debug'=>0,'prop'=>0,'method'=>0,'mustlok'=>0,'mustlam'=>0,'lambda'=>0,'jslen'=>0)), array(3,'a')
     * @expect array('(($inary && isset($in[\'id\'])) ? $in[\'id\'] : null)', 'this.[id]') when input array('flags'=>array('spvar'=>true,'debug'=>0,'prop'=>0,'method'=>0,'mustlok'=>0,'mustlam'=>0,'lambda'=>0,'jslen'=>0)), array(null, 'id')
     * @expect array('LR::v($cx, $in, isset($in) ? $in : null, array(\'id\'))', 'this.[id]') when input array('flags'=>array('prop'=>true,'spvar'=>true,'debug'=>0,'method'=>0,'mustlok'=>0,'mustlam'=>0,'lambda'=>0,'jslen'=>0,'standalone'=>0), 'runtime' => 'Runtime', 'runtimealias' => 'LR'), array(null, 'id')
     */
    protected static function get_variable_name(array &$context, $var, $lookup = null, $args = null): array
    {
        if (isset($var[0]) && $var[0] === Parser::LITERAL) {
            if ($var[1] === 'undefined') {
                $var[1] = 'null';
            }
            return [$var[1], preg_replace('/\'(.*)\'/', '$1', $var[1])];
        }
        [$levels, $spvar, $var] = Expression::analyze($context, $var);
        $exp = Expression::to_string($levels, $spvar, $var);
        $base = $spvar ? "\$cx['sp_vars']" : '$in';
        // change base when trace to parent
        if ($levels > 0) {
            if ($spvar) {
                $base .= str_repeat("['_parent']", $levels);
            } else {
                $base = "\$cx['scopes'][count(\$cx['scopes'])-{$levels}]";
            }
        }
        if ((empty($var) || count($var) == 0 || $var[0] === null && count($var) == 1) && $lookup === null) {
            return [$base, $exp];
        }
        if (count($var) > 0 && $var[0] === null) {
            array_shift($var);
        }
        // To support recursive context lookup, instance properties + methods and lambdas
        // the only way is using slower rendering time variable resolver.
        if ($context['flags']['prop'] || $context['flags']['method'] || $context['flags']['mustlok'] || $context['flags']['mustlam'] || $context['flags']['lambda']) {
            $L = Expression::list_string($var);
            $L = $L === '' ? [] : [$L];
            if ($lookup) {
                $L[] = $lookup[0];
            }
            $A = $args ? ",{$args[0]}" : '';
            $E = $args ? ' ' . implode(' ', $args[1]) : '';
            return [static::get_func_name($context, 'v', $exp) . "\$cx, \$in, isset({$base}) ? {$base} : null, array(" . implode(',', $L) . "){$A})", $lookup ? "lookup {$exp} {$lookup[1]}" : "{$exp}{$E}"];
        }
        $n = Expression::array_string($var);
        $k = array_pop($var);
        $L = $lookup ? "[{$lookup[0]}]" : '';
        $p = $lookup ? $n : (count($var) ? Expression::array_string($var) : '');
        $checks = [];
        if ($levels > 0) {
            $checks[] = "isset({$base})";
        }
        if (!$spvar) {
            if ($levels === 0 && $p) {
                $checks[] = "isset({$base}{$p})";
            }
            $checks[] = "{$base}{$p}" == '$in' ? '$inary' : "is_array({$base}{$p})";
        }
        $checks[] = "isset({$base}{$n}{$L})";
        $check = (count($checks) > 1 ? '(' : '') . implode(' && ', $checks) . (count($checks) > 1 ? ')' : '');
        $len_start = '';
        $len_end = '';
        if ($context['flags']['jslen']) {
            if ($lookup === null && $k === 'length') {
                array_pop($checks);
                $len_start = '(' . (count($checks) > 1 ? '(' : '') . implode(' && ', $checks) . (count($checks) > 1 ? ')' : '') . " ? count({$base}" . Expression::array_string($var) . ') : ';
                $len_end = ')';
            }
        }
        return ["({$check} ? {$base}{$n}{$L} : {$len_start}" . ($context['flags']['debug'] ? static::get_func_name($context, 'miss', '') . "\$cx, '{$exp}')" : 'null') . "){$len_end}", $lookup ? "lookup {$exp} {$lookup[1]}" : $exp];
    }
    /**
     * Return compiled PHP code for a handlebars token
     *
     * @param array<string,array|string|integer> $context current compile context
     * @param array<string,array|boolean> $info parsed information
     *
     * @return string Return compiled code segment for the token
     */
    protected static function compile_token(array &$context, $info)
    {
        [$raw, $vars, $token, $indent] = $info;
        $context['tokens']['partialind'] = $indent;
        $context['currentToken'] = $token;
        if ($ret = static::operator($token[Token::POS_OP], $context, $vars)) {
            return $ret;
        }
        if (isset($vars[0][0])) {
            if ($ret = static::custom_helper($context, $vars, $raw, true)) {
                return static::compile_output($context, $ret, 'FIXME: helper', $raw, false);
            }
            if ($context['flags']['else'] && $vars[0][0] === 'else') {
                return static::do_else($context, $vars);
            }
            if ($vars[0][0] === 'lookup') {
                return static::compile_lookup($context, $vars, $raw);
            }
            if ($vars[0][0] === 'log') {
                return static::compile_log($context, $vars, $raw);
            }
        }
        return static::compile_variable($context, $vars, $raw, false);
    }
    /**
     * handle partial
     *
     * @param array<string,array|string|integer> $context current compile context
     * @param array<boolean|integer|string|array> $vars parsed arguments list
     *
     * @return string Return compiled code segment for the partial
     */
    public static function partial(&$context, $vars)
    {
        Parser::get_block_params($vars);
        $pid = Parser::get_partial_block($vars);
        $p = array_shift($vars);
        if ($context['flags']['runpart']) {
            if (!isset($vars[0])) {
                $vars[0] = $context['flags']['partnc'] ? [0, 'null'] : [];
            }
            $v = static::get_variable_names($context, $vars);
            $tag = ">{$p[0]} " . implode(' ', $v[1]);
            if (Parser::is_sub_exp($p)) {
                [$p] = static::compile_sub_expression($context, $p[1]);
            } else {
                $p = "'" . addcslashes($p[0], "'\\") . "'";
            }
            $sp = $context['tokens']['partialind'] ? ", '{$context['tokens']['partialind']}'" : '';
            return $context['ops']['seperator'] . static::get_func_name($context, 'p', $tag) . "\$cx, {$p}, {$v[0]},{$pid}{$sp}){$context['ops']['seperator']}";
        }
        return isset($context['usedPartial'][$p[0]]) ? "{$context['ops']['seperator']}'" . Partial::compile_static($context, $p[0]) . "'{$context['ops']['seperator']}" : $context['ops']['seperator'];
    }
    /**
     * handle inline partial
     *
     * @param array<string,array|string|integer> $context current compile context
     * @param array<boolean|integer|string|array> $vars parsed arguments list
     *
     * @return string Return compiled code segment for the partial
     */
    public static function inline(&$context, $vars): bool|string
    {
        Parser::get_block_params($vars);
        [$code] = array_shift($vars);
        $p = array_shift($vars);
        if (!isset($vars[0])) {
            $vars[0] = $context['flags']['partnc'] ? [0, 'null'] : [];
        }
        $v = static::get_variable_names($context, $vars);
        $tag = ">*inline {$p[0]}" . implode(' ', $v[1]);
        return $context['ops']['seperator'] . static::get_func_name($context, 'in', $tag) . "\$cx, '" . addcslashes($p[0], "'\\") . "', {$code}){$context['ops']['seperator']}";
    }
    /**
     * Return compiled PHP code for a handlebars inverted section begin token
     *
     * @param array<string,array|string|integer> $context current compile context
     * @param array<boolean|integer|string|array> $vars parsed arguments list
     *
     * @return string Return compiled code segment for the token
     */
    protected static function inverted_section(&$context, $vars): string
    {
        $v = static::get_variable_name($context, $vars[0]);
        return "{$context['ops']['cnd_start']}(" . static::get_func_name($context, 'isec', '^' . $v[1]) . "\$cx, {$v[0]})){$context['ops']['cnd_then']}";
    }
    /**
     * Return compiled PHP code for a handlebars block custom helper begin token
     *
     * @param array<string,array|string|integer> $context current compile context
     * @param array<boolean|integer|string|array> $vars parsed arguments list
     * @param boolean $inverted the logic will be inverted
     *
     * @return string Return compiled code segment for the token
     */
    protected static function block_custom_helper(&$context, $vars, $inverted = false): string
    {
        $bp = Parser::get_block_params($vars);
        $ch = array_shift($vars);
        $inverted = $inverted ? 'true' : 'false';
        static::add_usage_count($context, 'helpers', $ch[0]);
        $v = static::get_variable_names($context, $vars, $bp);
        return $context['ops']['seperator'] . static::get_func_name($context, 'hbbch', ($inverted ? '^' : '#') . implode(' ', $v[1])) . "\$cx, '" . addcslashes($ch[0], "'\\") . "', {$v[0]}, \$in, {$inverted}, function(\$cx, \$in) {{$context['ops']['array_check']}{$context['ops']['f_start']}";
    }
    /**
     * Return compiled PHP code for a handlebars block end token
     *
     * @param array<string,array|string|integer> $context current compile context
     * @param array<boolean|integer|string|array> $vars parsed arguments list
     * @param string|null $matchop should also match to this operator
     *
     * @return string Return compiled code segment for the token
     */
    protected static function block_end(&$context, &$vars, $matchop = null)
    {
        $pop = $context['stack'][count($context['stack']) - 1];
        switch (isset($context['helpers'][$context['currentToken'][Token::POS_INNERTAG]]) ? 'skip' : $context['currentToken'][Token::POS_INNERTAG]) {
            case 'if':
            case 'unless':
                if ($pop === ':') {
                    array_pop($context['stack']);
                    return "{$context['ops']['cnd_end']}";
                }
                if (!$context['flags']['nohbh']) {
                    return "{$context['ops']['cnd_else']}''{$context['ops']['cnd_end']}";
                }
                break;
            case 'with':
                if (!$context['flags']['nohbh']) {
                    return "{$context['ops']['f_end']}}){$context['ops']['seperator']}";
                }
        }
        if ($pop === ':') {
            array_pop($context['stack']);
            return "{$context['ops']['f_end']}}){$context['ops']['seperator']}";
        }
        switch ($pop) {
            case '#':
                return "{$context['ops']['f_end']}}){$context['ops']['seperator']}";
            case '^':
                return "{$context['ops']['cnd_else']}''{$context['ops']['cnd_end']}";
        }
    }
    /**
     * Return compiled PHP code for a handlebars block begin token
     *
     * @param array<string,array|string|integer> $context current compile context
     * @param array<boolean|integer|string|array> $vars parsed arguments list
     *
     * @return string Return compiled code segment for the token
     */
    protected static function block_begin(&$context, $vars)
    {
        $v = isset($vars[1]) ? static::get_variable_name_or_sub_expression($context, $vars[1]) : [null, []];
        if (!$context['flags']['nohbh']) {
            switch ($vars[0][0] ?? null) {
                case 'if':
                    $include_zero = isset($vars['includeZero'][1]) && $vars['includeZero'][1] ? 'true' : 'false';
                    return "{$context['ops']['cnd_start']}(" . static::get_func_name($context, 'ifvar', $v[1]) . "\$cx, {$v[0]}, {$include_zero})){$context['ops']['cnd_then']}";
                case 'unless':
                    return "{$context['ops']['cnd_start']}(!" . static::get_func_name($context, 'ifvar', $v[1]) . "\$cx, {$v[0]}, false)){$context['ops']['cnd_then']}";
                case 'each':
                    return static::section($context, $vars, true);
                case 'with':
                    if ($r = static::with($context, $vars)) {
                        return $r;
                    }
            }
        }
        return static::section($context, $vars);
    }
    /**
     * compile {{#foo}} token
     *
     * @param array<string,array|string|integer> $context current compile context
     * @param array<boolean|integer|string|array> $vars parsed arguments list
     * @param boolean $isEach the section is #each
     *
     * @return string|null Return compiled code segment for the token
     */
    protected static function section(&$context, $vars, $is_each = false): bool|string
    {
        $bs = 'null';
        $be = '';
        if ($is_each) {
            $bp = Parser::get_block_params($vars);
            $bs = $bp ? 'array(' . Expression::list_string($bp) . ')' : 'null';
            $be = $bp ? ' as |' . implode(' ', $bp) . '|' : '';
            array_shift($vars);
        }
        if ($context['flags']['lambda'] && !$is_each) {
            $V = array_shift($vars);
            $v = static::get_variable_name($context, $V, null, count($vars) ? static::get_variable_names($context, $vars) : ['0', ['']]);
        } else {
            $v = static::get_variable_name_or_sub_expression($context, $vars[0]);
        }
        $each = $is_each ? 'true' : 'false';
        return $context['ops']['seperator'] . static::get_func_name($context, 'sec', ($is_each ? 'each ' : '') . $v[1] . $be) . "\$cx, {$v[0]}, {$bs}, \$in, {$each}, function(\$cx, \$in) {{$context['ops']['array_check']}{$context['ops']['f_start']}";
    }
    /**
     * compile {{with}} token
     *
     * @param array<string,array|string|integer> $context current compile context
     * @param array<boolean|integer|string|array> $vars parsed arguments list
     *
     * @return string|null Return compiled code segment for the token
     */
    protected static function with(&$context, $vars): bool|string
    {
        $v = isset($vars[1]) ? static::get_variable_name_or_sub_expression($context, $vars[1]) : [null, []];
        $bp = Parser::get_block_params($vars);
        $bs = $bp ? 'array(' . Expression::list_string($bp) . ')' : 'null';
        $be = $bp ? " as |{$bp[0]}|" : '';
        return $context['ops']['seperator'] . static::get_func_name($context, 'wi', 'with ' . $v[1] . $be) . "\$cx, {$v[0]}, {$bs}, \$in, function(\$cx, \$in) {{$context['ops']['array_check']}{$context['ops']['f_start']}";
    }
    /**
     * Return compiled PHP code for a handlebars custom helper token
     *
     * @param array<string,array|string|integer> $context current compile context
     * @param array<boolean|integer|string|array> $vars parsed arguments list
     * @param boolean $raw is this {{{ token or not
     * @param boolean $nosep true to compile without seperator
     * @param boolean $subExp true when compile for subexpression
     *
     * @return string|null Return compiled code segment for the token when the token is custom helper
     */
    protected static function custom_helper(array &$context, array $vars, $raw, $nosep, $sub_exp = false)
    {
        if (count($vars[0]) > 1) {
            return;
        }
        if (!isset($context['helpers'][$vars[0][0]])) {
            if (!$sub_exp) {
                return;
            }
            if ($vars[0][0] == 'lookup') {
                return static::compile_lookup($context, $vars, $raw, true);
            }
            return;
        }
        $fn = $raw ? 'raw' : $context['ops']['enc'];
        $ch = array_shift($vars);
        $v = static::get_variable_names($context, $vars);
        static::add_usage_count($context, 'helpers', $ch[0]);
        $sep = $nosep ? '' : $context['ops']['seperator'];
        return $sep . static::get_func_name($context, 'hbch', "{$ch[0]} " . implode(' ', $v[1])) . "\$cx, '" . addcslashes($ch[0], "'\\") . "', {$v[0]}, '{$fn}', \$in){$sep}";
    }
    /**
     * Return compiled PHP code for a handlebars else token
     *
     * @param array<string,array|string|integer> $context current compile context
     * @param array<boolean|integer|string|array> $vars parsed arguments list
     *
     * @return string Return compiled code segment for the token when the token is else
     */
    protected static function do_else(&$context, $vars): string
    {
        $v = $context['stack'][count($context['stack']) - 2];
        if ($v === '[if]' && !isset($context['helpers']['if']) || $v === '[unless]' && !isset($context['helpers']['unless'])) {
            $context['stack'][] = ':';
            return "{$context['ops']['cnd_else']}";
        }
        return "{$context['ops']['f_end']}}, function(\$cx, \$in) {{$context['ops']['array_check']}{$context['ops']['f_start']}";
    }
    /**
     * Return compiled PHP code for a handlebars log token
     *
     * @param array<string,array|string|integer> $context current compile context
     * @param array<boolean|integer|string|array> $vars parsed arguments list
     * @param boolean $raw is this {{{ token or not
     *
     * @return string Return compiled code segment for the token
     */
    protected static function compile_log(array &$context, &$vars, $raw): string
    {
        array_shift($vars);
        $v = static::get_variable_names($context, $vars);
        return $context['ops']['seperator'] . static::get_func_name($context, 'lo', $v[1]) . "\$cx, {$v[0]}){$context['ops']['seperator']}";
    }
    /**
     * Return compiled PHP code for a handlebars lookup token
     *
     * @param array<string,array|string|integer> $context current compile context
     * @param array<boolean|integer|string|array> $vars parsed arguments list
     * @param boolean $raw is this {{{ token or not
     * @param boolean $nosep true to compile without seperator
     *
     * @return string Return compiled code segment for the token
     */
    protected static function compile_lookup(array &$context, array &$vars, $raw, $nosep = false): string
    {
        $v2 = static::get_variable_name($context, $vars[2]);
        $v = static::get_variable_name($context, $vars[1], $v2);
        $sep = $nosep ? '' : $context['ops']['seperator'];
        $ex = $nosep ? ', 1' : '';
        if ($context['flags']['hbesc'] || $context['flags']['jsobj'] || $context['flags']['jstrue'] || $context['flags']['debug']) {
            return $sep . static::get_func_name($context, $raw ? 'raw' : $context['ops']['enc'], $v[1]) . "\$cx, {$v[0]}{$ex}){$sep}";
        }
        return $raw ? "{$sep}{$v[0]}{$sep}" : "{$sep}htmlspecialchars((string){$v[0]}, ENT_QUOTES, 'UTF-8'){$sep}";
    }
    /**
     * Return compiled PHP code for template output
     *
     * @param array<string,array|string|integer> $context current compile context
     * @param string $variable PHP code for the variable
     * @param string $expression normalized handlebars expression
     * @param boolean $raw is this {{{ token or not
     * @param boolean $nosep true to compile without seperator
     *
     * @return string Return compiled code segment for the token
     */
    protected static function compile_output(array &$context, $variable, $expression, $raw, $nosep): string
    {
        $sep = $nosep ? '' : $context['ops']['seperator'];
        if ($context['flags']['hbesc'] || $context['flags']['jsobj'] || $context['flags']['jstrue'] || $context['flags']['debug'] || $nosep) {
            return $sep . static::get_func_name($context, $raw ? 'raw' : $context['ops']['enc'], $expression) . "\$cx, {$variable}){$sep}";
        }
        return $raw ? "{$sep}{$variable}{$context['ops']['seperator']}" : "{$context['ops']['seperator']}htmlspecialchars((string){$variable}, ENT_QUOTES, 'UTF-8'){$sep}";
    }
    /**
     * Return compiled PHP code for a handlebars variable token
     *
     * @param array<string,array|string|integer> $context current compile context
     * @param array<boolean|integer|string|array> $vars parsed arguments list
     * @param boolean $raw is this {{{ token or not
     * @param boolean $nosep true to compile without seperator
     *
     * @return string Return compiled code segment for the token
     */
    protected static function compile_variable(array &$context, array &$vars, $raw, $nosep)
    {
        if ($context['flags']['lambda']) {
            $V = array_shift($vars);
            $v = static::get_variable_name($context, $V, null, count($vars) ? static::get_variable_names($context, $vars) : ['0', ['']]);
        } else {
            $v = static::get_variable_name($context, $vars[0]);
        }
        return static::compile_output($context, $v[0], $v[1], $raw, $nosep);
    }
    /**
     * Add usage count to context
     *
     * @param array<string,array|string|integer> $context current context
     * @param string $category category name, can be one of: 'var', 'helpers', 'runtime'
     * @param string $name used name
     * @param integer $count increment
     *
     * @expect 1 when input array('usedCount' => array('test' => array())), 'test', 'testname'
     * @expect 3 when input array('usedCount' => array('test' => array('testname' => 2))), 'test', 'testname'
     * @expect 5 when input array('usedCount' => array('test' => array('testname' => 2))), 'test', 'testname', 3
     */
    protected static function add_usage_count(array &$context, $category, $name, $count = 1)
    {
        if (!isset($context['usedCount'][$category][$name])) {
            $context['usedCount'][$category][$name] = 0;
        }
        return $context['usedCount'][$category][$name] += $count;
    }
}