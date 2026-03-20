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
 * file to keep LightnCandy Exporter
 *
 * @package    LightnCandy
 * @author     Zordius <zordius@gmail.com>
 */
namespace Lightn_Candy;

/**
 * LightnCandy major static class
 */
class Exporter
{
    /**
     * Get PHP code string from a closure of function as string
     *
     * @param array<string,array|string|integer> $context current compile context
     * @param object $closure Closure object
     *
     * @return string
     *
     * @expect 'function($a) {return;}' when input array('flags' => array('standalone' => 0)),  function ($a) {return;}
     * @expect 'function($a) {return;}' when input array('flags' => array('standalone' => 0)),   function ($a) {return;}
     */
    protected static function closure($context, $closure): ?string
    {
        if (is_string($closure) && preg_match('/(.+)::(.+)/', $closure, $matched)) {
            $ref = new \ReflectionMethod($matched[1], $matched[2]);
        } else {
            $ref = new \ReflectionFunction($closure);
        }
        $meta = static::get_meta($ref);
        return preg_replace('/^.*?function(\s+[^\s\(]+?)?\s*\((.+)\}.*?\s*$/s', 'function($2}', static::replace_safe_string($context, $meta['code']));
    }
    /**
     * Export required custom helper functions
     *
     * @param array<string,array|string|integer> $context current compile context
     */
    public static function helpers(array $context): string
    {
        $ret = '';
        foreach ($context['helpers'] as $name => $func) {
            if (!isset($context['usedCount']['helpers'][$name])) {
                continue;
            }
            if (is_object($func) && $func instanceof \Closure || $context['flags']['exhlp'] == 0) {
                $ret .= "            '" . addcslashes($name, "'\\") . "' => " . static::closure($context, $func) . ",\n";
                continue;
            }
            $ret .= "            '" . addcslashes($name, "'\\") . "' => '" . addcslashes((string) $func, "'\\") . "',\n";
        }
        return "array({$ret})";
    }
    /**
     * Replace SafeString class with alias class name
     *
     * @param array<string,array|string|integer> $context current compile context
     * @param string $str the PHP code to be replaced
     *
     * @return string
     */
    protected static function replace_safe_string(array $context, $str)
    {
        return $context['flags']['standalone'] ? str_replace($context['safestring'], $context['safestringalias'], $str) : $str;
    }
    /**
     * Get methods from ReflectionClass
     *
     * @param array<string,array|string|integer> $context current compile context
     * @param \ReflectionClass $class instance of the ReflectionClass
     */
    public static function get_class_methods(array $context, $class): array
    {
        $methods = [];
        foreach ($class->get_methods() as $method) {
            $meta = static::get_meta($method);
            $methods[$meta['name']] = static::scan_dependency($context, preg_replace('/public static function (.+)\(/', "function {$context['funcprefix']}\$1(", $meta['code']), $meta['code']);
        }
        return $methods;
    }
    /**
     * Get statics code from ReflectionClass
     *
     * @param \ReflectionClass $class instance of the ReflectionClass
     */
    public static function get_class_statics($class): string
    {
        $ret = '';
        foreach ($class->get_static_properties() as $name => $value) {
            $ret .= " public static \${$name} = " . var_export($value, true) . ";\n";
        }
        return $ret;
    }
    /**
     * Get metadata from ReflectionObject
     *
     * @param object $refobj instance of the ReflectionObject
     */
    public static function get_meta($refobj): array
    {
        $fname = $refobj->get_file_name();
        $lines = file_get_contents($fname);
        if ($lines === false) {
            throw new \RuntimeException("Cannot read file: {$fname}");
        }
        $file = new \Spl_File_Object($fname);
        $start = $refobj->get_start_line() - 2;
        $end = $refobj->get_end_line() - 1;
        if (version_compare(\PHP_VERSION, '8.0.0') >= 0) {
            $start++;
            $end++;
        }
        $file->seek($start);
        $spos = $file->ftell();
        $file->seek($end);
        $epos = $file->ftell();
        unset($file);
        return ['name' => $refobj->get_name(), 'code' => substr($lines, $spos, $epos - $spos)];
    }
    /**
     * Export SafeString class as string
     *
     * @param array<string,array|string|integer> $context current compile context
     */
    public static function safestring(array $context): string
    {
        $class = new \ReflectionClass($context['safestring']);
        $alias = $context['safestringalias'];
        if (!preg_match('/^[a-zA-Z_]\w*$/', $alias)) {
            throw new \InvalidArgumentException("Invalid safestringalias: {$alias}");
        }
        return array_reduce(static::get_class_methods($context, $class), function (string $in, $cur): string {
            return $in . $cur[2];
        }, 'if (!class_exists("' . addslashes($alias) . "\")) {\nclass {$alias} {\n" . static::get_class_statics($class)) . "}\n}\n";
    }
    /**
     * Export StringObject class as string
     *
     * @param array<string,array|string|integer> $context current compile context
     */
    public static function stringobject(array $context): string
    {
        if ($context['flags']['standalone'] == 0) {
            return 'use \LightnCandy\StringObject as StringObject;';
        }
        $class = new \ReflectionClass(\Lightn_Candy\String_Object::class);
        $meta = static::get_meta($class);
        return "if (!class_exists(\"StringObject\")) {\n{$meta['code']}}\n";
    }
    /**
     * Export required standalone Runtime methods
     *
     * @param array<string,array|string|integer> $context current compile context
     */
    public static function runtime(array $context): string
    {
        $class = new \ReflectionClass($context['runtime']);
        $ret = '';
        $methods = static::get_class_methods($context, $class);
        $exports = array_keys($context['usedCount']['runtime']);
        while (true) {
            if (array_sum(array_map(function ($name) use (&$exports, $methods): int {
                $n = 0;
                foreach ($methods[$name][1] as $child => $count) {
                    if (!in_array($child, $exports)) {
                        $exports[] = $child;
                        $n++;
                    }
                }
                return $n;
            }, $exports)) == 0) {
                break;
            }
        }
        foreach ($exports as $export) {
            $ret .= $methods[$export][0] . "\n";
        }
        return $ret;
    }
    /**
     * Export Runtime constants
     *
     * @param array<string,array|string|integer> $context current compile context
     */
    public static function constants(array $context): string
    {
        if ($context['flags']['standalone'] == 0) {
            return 'array()';
        }
        $class = new \ReflectionClass($context['runtime']);
        $constants = $class->get_constants();
        $ret = " array(\n";
        foreach ($constants as $name => $value) {
            $ret .= "            '{$name}' => " . var_export($value, true) . ",\n";
        }
        return $ret . '        )';
    }
    /**
     * Scan for required standalone functions
     *
     * @param array<string,array|string|integer> $context current compile context
     * @param string $code patched PHP code string of the method
     * @param string $ocode original PHP code string of the method
     *
     * @return array<string|array> list of converted code and children array
     */
    protected static function scan_dependency($context, $code, $ocode): array
    {
        $child = [];
        $code = preg_replace_callback('/static::(\w+?)\s*\(/', function ($matches) use ($context, &$child): string {
            if (!isset($child[$matches[1]])) {
                $child[$matches[1]] = 0;
            }
            $child[$matches[1]]++;
            return "{$context['funcprefix']}{$matches[1]}(";
        }, $code);
        // replace the constants
        $code = preg_replace('/static::([A-Z0-9_]+)/', "\$cx['constants']['\$1']", $code);
        // compress space
        $code = preg_replace('/    /', ' ', $code);
        return [static::replace_safe_string($context, $code), $child, $ocode];
    }
}