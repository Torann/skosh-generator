<?php namespace Skosh\Parsers;

use Netcarver\Textile\Parser;

class Textile
{
    /**
     * Parse Content
     *
     * @param  string $content
     * @return string
     */
    public static function parse($content)
    {
        $parser = new Parser();

        return $parser->textileThis($content);
    }
}
