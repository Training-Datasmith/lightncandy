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
 * file to keep LightnCandy string utilities
 *
 * @package    LightnCandy
 * @author     Zordius <zordius@gmail.com>
 */
namespace Lightn_Candy;

/**
 * LightnCandy SafeString class
 */
class Safe_String extends Encoder
{
    public const EXTENDED_COMMENT_SEARCH = '/{{!--.*?--}}/s';
    public const IS_SUBEXP_SEARCH = '/^\(.+\)$/s';
    public const IS_BLOCKPARAM_SEARCH = '/^ +\|(.+)\|$/s';
    private $string;
    public static $js_context = ['flags' => ['jstrue' => 1, 'jsobj' => 1]];
    /**
     * Constructor
     *
     * @param string $str input string
     * @param bool|string $escape false to not escape, true to escape, 'encq' to escape as handlebars.js
     */
    public function __construct($str, $escape = false)
    {
        $this->string = $escape ? $escape === 'encq' ? static::encq(static::$js_context, $str) : static::enc(static::$js_context, $str) : $str;
    }
    public function __toString(): string
    {
        return $this->string;
    }
    /**
     * Strip extended comments {{!-- .... --}}
     *
     * @param string $template handlebars template string
     *
     * @return string Stripped template
     *
     * @expect 'abc' when input 'abc'
     * @expect 'abc{{!}}cde' when input 'abc{{!}}cde'
     * @expect 'abc{{! }}cde' when input 'abc{{!----}}cde'
     */
    public static function strip_extended_comments($template): ?string
    {
        return preg_replace(static::EXTENDED_COMMENT_SEARCH, '{{! }}', $template);
    }
    /**
     * Escape template
     *
     * @param string $template handlebars template string
     *
     * @return string Escaped template
     *
     * @expect 'abc' when input 'abc'
     * @expect 'a\\\\bc' when input 'a\bc'
     * @expect 'a\\\'bc' when input 'a\'bc'
     */
    public static function escape_template($template): string
    {
        return addcslashes(addcslashes($template, '\\'), "'");
    }
}