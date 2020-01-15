<?php

namespace Skosh\Content;

use Skosh\Builder;
use Symfony\Component\Finder\SplFileInfo;

class Doc extends Content
{
    public $category;
    public $chapter;
    public $date;

    /**
     * Doc constructor.
     *
     * @param SplFileInfo $file
     * @param Builder     $builder
     */
    public function __construct(SplFileInfo $file, Builder $builder)
    {
        parent::__construct($file, $builder);

        // Get Category
        $this->category = $this->getCategory();

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
