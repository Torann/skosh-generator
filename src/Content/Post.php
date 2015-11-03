<?php

namespace Skosh\Content;

use Skosh\Builder;
use Symfony\Component\Finder\SplFileInfo;

class Post extends Content
{
    public $category;
    public $date;
    public $excerpt;
    public $image;
    public $tags;
    public $tag_class;
    public $author = 'unknown';

    public function __construct(SplFileInfo $file, Builder $builder)
    {
        parent::__construct($file, $builder);

        // Get Category
        $this->category = $this->getCategory();

        // Set tags
        $this->tags = $this->getHumanTags();
        $this->tag_class = $this->getTagClassNames();

        // Set date
        if ($this->has('date')) {
            $this->date = strtotime($this->get('date'));
        }
        else {
            $this->date = $file->getMTime();
        }

        // Set excerpt
        if ($this->has('excerpt')) {
            $this->excerpt = $this->get('excerpt');
        }
        else {
            $this->excerpt = $this->getExcerpt($this->content);
        }

        // Set author
        $this->author = $this->get('author', $this->author);

        // Set Images
        if ($this->has('image')) {
            $fileinfo = pathinfo($this->get('image'));

            $template = "{$fileinfo['dirname']}/{$fileinfo['filename']}-%SIZE%.{$fileinfo['extension']}";

            $this->image = [
                'full' => $this->get('image'),
                'medium' => str_replace('%SIZE%', 'medium', $template),
                'thumb' => str_replace('%SIZE%', 'thumb', $template)
            ];
        }

        // Remove content HTML comments
        $this->content = preg_replace('/<!--(.*)-->/Uis', '', $this->content);
    }

    /**
     * Get excerpt from between excerpt tags or
     * first paragraph.
     *
     * @param  string $content
     * @return string
     */
    protected function getExcerpt($content)
    {
        // Check for excerpt tags
        $pattern = '#(\<\!\-\-\s?excerpt\s?\-\-\>)(.*)(\<\!\-\-\s?endexcerpt\s?\-\-\>)#si';
        if (preg_match($pattern, $content, $matches)) {
            return $matches[2];
        }

        // If all else get the content from the first paragraph
        return $this->description;
    }

    /**
     * Remove child group from path.
     *
     * @return string
     */
    protected function getCleanPath($path)
    {
        return preg_replace("/[\\|\/]?_posts[\\|\/]?/", DIRECTORY_SEPARATOR, $path);
    }

    /**
     * Get human readable tags.
     *
     * @return string
     */
    protected function getHumanTags()
    {
        $tags = explode('|', $this->get('tags'));

        return implode(' & ', $tags);
    }

    /**
     * Get human readable tags.
     *
     * @return string
     */
    protected function getTagClassNames()
    {
        $tags = array_map(function($tag) {
            return slugify($tag);
        }, explode('|', $this->get('tags')));

        return implode(' ', $tags);
    }
}
