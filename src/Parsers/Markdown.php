<?php

namespace Skosh\Parsers;

use Skosh\Support\SkoshParsedown;

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
        return SkoshParsedown::instance()->text($content);
    }
}
