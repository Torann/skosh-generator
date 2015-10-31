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
        // Leading comments breat markdown
        $content = preg_replace('/<!--/s', '&lt;!--', $content);

        // Parse Markdown
        $content = ParsedownExtra::instance()->text($content);

        // Leading comments breat markdown
        $content = preg_replace('/&lt;!--/s', '<!--', $content);

        return $content;
    }
}
