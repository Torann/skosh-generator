<?php

namespace Skosh\Twig;

use Parsedown;
use Skosh\Twig\Extensions\SocialShare;

class Extension extends AbstractExtension
{
    /**
     * Returns a list of functions to add to the existing list.
     *
     * @return array
     */
    public function getFunctions()
    {
        return [
            'isCurrent' => new \Twig_Function_Method($this, 'isCurrent'),
            'file_exists' => new \Twig_Function_Method($this, 'functionFileExists'),
            'clean_string' => new \Twig_Function_Method($this, 'functionCleanString'),
            'asset' => new \Twig_Function_Method($this, 'functionGetAsset'),
            'url' => new \Twig_Function_Method($this, 'functionGetUrl'),
            'share_link' => new \Twig_Function_Method($this, 'functionShareLink', ['is_safe' => ['twig', 'html']]),
            'editButton' => new \Twig_Function_Method($this, 'functionEditButton', [
                'needs_environment' => true,
                'is_safe' => ['twig', 'html']
            ]),
        ];
    }

    /**
     * Returns a list of filters to add to the existing list.
     *
     * @return array
     */
    public function getFilters()
    {
        return [
            new \Twig_SimpleFilter('markdown', [$this, 'filterMarkdown'], ['is_safe' => ['twig', 'html']]),
            new \Twig_SimpleFilter('truncate', [$this, 'filterTruncate'], ['is_safe' => ['twig', 'html']]),
            new \Twig_SimpleFilter('wordwrap', [$this, 'filterWordwrap'], ['is_safe' => ['twig', 'html']])
        ];
    }

    public function functionFileExists($file)
    {
        return file_exists($this->getBuilder()->target . $file);
    }

    public function functionCleanString($string)
    {
        return clean_string($string);
    }

    public function functionShareLink($network, $page)
    {
        return SocialShare::generate($network, $page);
    }

    public function functionGetUrl($path)
    {
        return $this->getBuilder()->getUrl($path);
    }

    public function functionGetAsset($path)
    {
        return $this->getBuilder()->getAsset($path);
    }

    public function functionEditButton(\Twig_Environment $environment, $page, $parent, $template)
    {
        // Must be markdown and have a parent
        if ($page->type === 'markdown' && $parent) {
            // Get URL
            $url = $parent->get('edit_url');

            if (empty($url)) {
                return;
            }

            // Parse URL
            $edit_url = str_replace([
                '{filename}'
            ], [
                $page->filename
            ], $url);

            // Render button template
            return $environment->render($template, [
                'edit_url' => $edit_url,
                'page' => $page
            ]);
        }

        return '';
    }

    public function filterMarkdown($string)
    {
        return Parsedown::instance()->parse($string);
    }

    public function filterTruncate($string, $width)
    {
        return current(explode("\n", wordwrap($string, $width, "...\n")));
    }

    public function filterWordwrap($string, $prefix, $sufix)
    {
        if ($string) {
            return "{$prefix}{$string}{$sufix}";
        }

        return '';
    }
}
