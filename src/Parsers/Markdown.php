<?php namespace Skosh\Parsers;

use ParsedownExtra;

class Markdown
{
    /**
     * Parse Content
     *
     * @param  string $content
     * @return string
     */
    public static function parse($content)
    {
        return ParsedownExtra::instance()->text($content);
    }
}
