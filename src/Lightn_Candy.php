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
 * the major file of LightnCandy compiler
 *
 * @package    LightnCandy
 * @author     Zordius <zordius@gmail.com>
 */
namespace Lightn_Candy;

/**
 * LightnCandy major static class
 */
class Lightn_Candy extends Flags
{
    protected static $last_context;
    public static $last_parsed;
    /**
     * Compile handlebars template into PHP code.
     *
     * @param string $template handlebars template string
     * @param array<string,array|string|integer> $options LightnCandy compile time and run time options, default is array('flags' => LightnCandy::FLAG_BESTPERFORMANCE)
     *
     * @return string|false Compiled PHP code when successed. If error happened and compile failed, return false.
     */
    public static function compile($template, $options = ['flags' => self::FLAG_BESTPERFORMANCE])
    {
        $context = Context::create($options);
        if (static::handle_error($context)) {
            return false;
        }
        $code = Compiler::compile_template($context, Safe_String::escape_template($template));
        static::$last_parsed = Compiler::$last_parsed;
        // return false when fatal error
        if (static::handle_error($context)) {
            return false;
        }
        // Or, return full PHP render codes as string
        return Compiler::compose_php_render($context, $code);
    }
    /**
     * Compile handlebars partial into PHP function code.
     *
     * @param string $template handlebars template string
     * @param array<string,array|string|integer> $options LightnCandy compile time and run time options, default is array('flags' => LightnCandy::FLAG_BESTPERFORMANCE)
     *
     * @return string|false Compiled PHP code when successed. If error happened and compile failed, return false.
     *
     * @expect false when input '{{"}}', array('flags' => LightnCandy::FLAG_HANDLEBARS)
     */
    public static function compile_partial($template, $options = ['flags' => self::FLAG_BESTPERFORMANCE])
    {
        $context = Context::create($options);
        if (static::handle_error($context)) {
            return false;
        }
        $code = Partial::compile($context, Safe_String::escape_template($template));
        static::$last_parsed = Compiler::$last_parsed;
        // return false when fatal error
        if (static::handle_error($context)) {
            return false;
        }
        return $code;
    }
    /**
     * Handle exists error and return error status.
     *
     * @param array<string,array|string|integer> $context Current context of compiler progress.
     *
     * @throws \Exception
     * @return boolean True when error detected
     *
     * @expect false when input array('error' => array())
     * @expect true when input array('error' => array('some error'), 'flags' => array('errorlog' => 0, 'exception' => 0))
     */
    protected static function handle_error(array &$context): bool
    {
        static::$last_context = $context;
        if (count($context['error'])) {
            if ($context['flags']['errorlog']) {
                error_log(implode("\n", $context['error']));
            }
            if ($context['flags']['exception']) {
                throw new \Exception(implode("\n", $context['error']));
            }
            return true;
        }
        return false;
    }
    /**
     * Get last compiler context.
     *
     * @return array<string,array|string|integer> Context data
     */
    public static function get_context()
    {
        return static::$last_context;
    }
    /**
     * Get a working render function by a string of PHP code. This method may requires php setting allow_url_include=1 and allow_url_fopen=1 , or access right to tmp file system.
     *
     * @param string      $php PHP code
     * @param string|null $tmpDir Optional, change temp directory for php include file saved by prepare() when cannot include PHP code with data:// format.
     * @param boolean     $delete Optional, delete temp php file when set to tru. Default is true, set it to false for debug propose
     *
     * @return Closure|false result of include()
     *
     * @deprecated
     */
    public static function prepare($php, $tmp_dir = null, $delete = true)
    {
        $php = "<?php {$php} ?>";
        if (!ini_get('allow_url_include') || !ini_get('allow_url_fopen')) {
            if (!is_string($tmp_dir) || !is_dir($tmp_dir)) {
                $tmp_dir = sys_get_temp_dir();
            }
        }
        if (is_dir($tmp_dir)) {
            $fn = tempnam($tmp_dir, 'lci_');
            if (!$fn) {
                error_log("Can not generate tmp file under {$tmp_dir}!!\n");
                return false;
            }
            if (!file_put_contents($fn, $php)) {
                error_log("Can not include saved temp php code from {$fn}, you should add {$tmp_dir} into open_basedir!!\n");
                return false;
            }
            $phpfunc = include $fn;
            if ($delete) {
                unlink($fn);
            }
            return $phpfunc;
        }
        return include 'data://text/plain,' . urlencode($php);
    }
}