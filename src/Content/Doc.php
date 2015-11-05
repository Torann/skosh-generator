<?php

namespace Skosh\Content;

use Skosh\Builder;
use Symfony\Component\Finder\SplFileInfo;

class Doc extends Content
{
    public $category;
    public $chapter;
    public $date;
    public $doc_url;

    public function __construct(SplFileInfo $file, Builder $builder)
    {
        parent::__construct($file, $builder);

        // Get Category
        $this->category = $this->getCategory();

        // Set doc url
        $this->doc_url = $this->builder->getUrl($this->get('doc_url'));

        // Set chapter
        $this->chapter = $this->get('chapter');

        // Set date
        if ($this->has('date')) {
            $this->date = strtotime($this->get('date'));
        }
        else {
            $this->date = $file->getMTime();
        }
    }

    /**
     * Remove children directory from path.
     *
     * Posts in /_posts will have no category.
     * Posts in /news/_posts will have the category "news"
     *
     * Others combinations throw an exception.
     */
    protected function getCleanPath($path)
    {
        return preg_replace_callback("/['|\"]?(_doc?)['|\"]?/s", function () {
            return 'doc';
        }, $path);
    }
}
