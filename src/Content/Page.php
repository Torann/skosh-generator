<?php

namespace Skosh\Content;

use Skosh\Builder;
use Symfony\Component\Finder\SplFileInfo;

class Page extends Content
{
    /**
     * Page constructor.
     *
     * @param SplFileInfo $file
     * @param Builder     $builder
     */
    public function __construct(SplFileInfo $file, Builder $builder)
    {
        parent::__construct($file, $builder);

        // Get date file was last modified
        $this->date = $file->getMTime();
    }

    /**
     * @param string $name
     * @param null   $default
     *
     * @return string
     */
    public function image($name, $default = null)
    {
        if ($this->has('images')) {
            $path = rtrim($this->get('images'), DIRECTORY_SEPARATOR);
            $image = join(DIRECTORY_SEPARATOR, [$path, $name]);

            if (file_exists($this->builder->target . $image)) {
                return $this->builder->getUrl($image);
            }
        }

        return $this->builder->getUrl($default);
    }
}
