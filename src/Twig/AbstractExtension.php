<?php

namespace Skosh\Twig;

use Skosh\Builder;

abstract class AbstractExtension extends \Twig_Extension
{
    /**
     * Application instance.
     *
     * @var \Skosh\Builder
     */
    private $builder;

    /**
     * Create a new instance of AbstractExtension.
     *
     * @param Builder $builder
     */
    public function __construct(Builder $builder)
    {
        $this->builder = $builder;
    }

    /**
     * Return site Builder.
     *
     * @return Builder
     */
    public function getBuilder()
    {
        return $this->builder;
    }

    /**
     * Is given URL the current page?
     *
     * @param $page
     * @param $pattern
     *
     * @return bool
     */
    public function isCurrent($page, $pattern)
    {
        // Remove site URL from string
        $page = str_replace($this->getBuilder()->app->getSetting('url'), '', $page);

        return str_is($pattern, $page);
    }

    /**
     * Returns the name of the extension.
     *
     * @return string
     */
    public function getName()
    {
        return str_slug(get_class($this));
    }
}
