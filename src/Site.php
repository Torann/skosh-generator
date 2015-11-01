<?php

namespace Skosh;

use DateTime;
use Skosh\Content\Page;
use Skosh\Content\Content;
use Skosh\Console\Application;

class Site
{
    /**
     * Time of site generation.
     *
     * @var \DateTime
     */
    public $time;

    /**
     * Site title as specified in config.title.
     *
     * @var string
     */
    public $title;

    /**
     * Site URL as specified in config.url.
     *
     * @var string
     */
    public $url;

    /**
     * Holds all pages indexed by ID.
     *
     * @var array
     */
    public $pages = [];

    /**
     * Holds arrays of posts indexed by category.
     *
     * @var array
     */
    public $categories = [];

    /**
     * Constructor
     *
     * @var \Skosh\Console\Application
     */
    public function __construct(Application $app)
    {
        $this->time = new DateTime();
        $this->title = $app->getSetting('title');

        if ($app->getSetting('url')) {
            $this->url = rtrim($app->getSetting('url'), '/');
        }
    }

    public function addContent(Content $content)
    {
        if ($content instanceof Page) {
            $this->addPage($content);
        }
        else {
            if ($content) {
                $this->addChild($content);
            }
            else {
                throw new \Exception("Unknown content type.");
            }
        }
    }

    public function addPage(Content $page)
    {
        $this->pages[$page->id] = $page;
    }

    public function addChild(Content $child)
    {
        $this->pages[$child->id] = $child;

        // Group by category
        if (!isset($this->categories[$child->category])) {
            $this->categories[$child->category] = [];
        }

        // Add post to category
        $this->categories[$child->category][] = $child;
    }
}
