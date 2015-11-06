<?php

namespace Skosh\Content;

use Skosh\Builder;
use Symfony\Component\Finder\SplFileInfo;

class Page extends Content
{
    public function __construct(SplFileInfo $file, Builder $builder)
    {
        parent::__construct($file, $builder);

        // Get date file was last modified
        $this->date = $file->getMTime();
    }

    public function image($name, $default = null)
    {
        if ($this->has('images'))
        {
            $path = rtrim($this->get('images'), DIRECTORY_SEPARATOR);
            $image = join(DIRECTORY_SEPARATOR, [$path, $name]);

            if (file_exists($this->builder->target . $image)) {
                return $this->builder->getUrl($image);
            }
        }

        return $this->builder->getUrl($default);
    }
}
