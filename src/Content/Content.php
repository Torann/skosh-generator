<?php

namespace Skosh\Content;

use Skosh\Builder;
use Skosh\Parsers\Markdown;
use Skosh\Parsers\Textile;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Finder\SplFileInfo;

abstract class Content
{
    const TYPE_MARKDOWN = 'markdown';
    const TYPE_TEXTILE = 'textile';
    const TYPE_TWIG = 'twig';

    protected $meta;

    public $filename;
    public $sourcePath;

    public $target;
    public $type;

    /**
     * Content basic values
     */
    public $id;
    public $parentId;
    public $url;
    public $title;
    public $description;

    /**
     * Content
     *
     * @var string
     */
    public $content;

    /**
     * Builder instance
     *
     * @var Builder
     */
    protected $builder;

    /**
     * Pagination
     */
    public $paginate;
    public $next;
    public $prev;

    /**
     * Create a new content.
     *
     * @param SplFileInfo $file
     * @param Builder     $builder
     */
    public function __construct(SplFileInfo $file, Builder $builder)
    {
        $this->builder = $builder;
        $this->type = self::TYPE_TWIG;

        $this->load($file);
    }

    /**
     * Get the specified metadata value.
     *
     * @param string $key
     * @param mixed  $default
     *
     * @return mixed
     */
    public function get($key, $default = null)
    {
        return $this->has($key) ? $this->meta[$key] : $default;
    }

    /**
     * Determine if the given metadata value exists.
     *
     * @param string $key
     *
     * @return bool
     */
    public function has($key)
    {
        return isset($this->meta[$key]);
    }

    /**
     * Load and parse content from file.
     *
     * @param SplFileInfo $file
     *
     * @return void
     */
    protected function load(SplFileInfo $file)
    {
        // File info
        $this->filename = $file->getBasename();
        $this->sourcePath = $file->getRelativePath();

        // Load the file
        $data = $file->getContents();

        list($content, $meta) = $this->splitContentMeta($data);

        // Parse meta
        $meta = Yaml::parse($meta) ?: [];

        // Parse content
        switch ($file->getExtension()) {
            case 'md':
            case 'markdown':
                $content = Markdown::parse($content);
                $this->type = self::TYPE_MARKDOWN;
                break;
            case 'tx':
            case 'textile':
                $content = Textile::parse($content);
                $this->type = self::TYPE_TEXTILE;
                break;
        }

        // Set content
        $this->content = $content;
        $this->meta = $meta;

        // Ensure local URLs are absolute
        foreach ($this->meta as $key => $value) {
            if (preg_match('/\burl\b|.*_url\b/', $key)) {
                $this->meta[$key] = $this->builder->getUrl($value);
            }
        }

        // Set target
        $this->setTarget($file);

        // Pagination enabled
        $this->paginate = isset($this->meta['paginate']);

        // Get parent page
        if ($root = dirname(dirname($this->target))) {
            if ($root !== DIRECTORY_SEPARATOR) {
                $this->parentId = ltrim($root, '/');
            }
        }

        // Set URL
        $this->url = '/' . trim(str_replace([DIRECTORY_SEPARATOR, '//'], ['/', '/'], $this->target), '/');

        // Remove "index.html" from the end, this provides a cleaner URL
        if (substr($this->url, -10) === 'index.html') {
            $this->url = substr($this->url, 0, -10);
        }

        // Set basic values
        $this->id = trim($this->url, '/') ?: 'root';
        $this->title = $this->get('title');
        $this->url = $this->builder->getUrl($this->url);

        // Set Description
        if ($this->has('description')) {
            $this->description = $this->get('description');
        }
        else {
            $this->description = $this->getDescription();
        }
    }

    /**
     * Parse metadata and content
     *
     * @param string $data
     *
     * @return array
     */
    protected function splitContentMeta($data)
    {
        // Remove Byte Order Mark (BOM)
        $data = preg_replace('/\x{EF}\x{BB}\x{BF}/', '', $data);

        // Pattern for detecting a metadata separator (---)
        // Using ^ and $ in this way requires the PCRE_MULTILINE modifier
        $pattern = '/' // Pattern start
            . '^'       // Beginning of line
            . '---'     // Literal ---
            . '\\s*'    // Zero or more whitespace characters
            . '$'       // End of line
            . '/m';     // Pattern end, PCRE_MULTILINE modifier

        // Separate the meta-data from the content
        $data = trim($data);

        if ((substr($data, 0, 3) === '---') &&
            (preg_match($pattern, $data, $matches, PREG_OFFSET_CAPTURE, 3))
        ) {
            $pos = $matches[0][1];
            $len = strlen($matches[0][0]);

            $meta = trim(substr($data, 3, $pos - 3));
            $content = trim(substr($data, $pos + $len));
        }
        else {
            $content = $data;
            $meta = '';
        }

        return [$content, $meta];
    }

    /**
     * Determines post category based on it's source file path.
     *
     * Posts in /_posts will have no category.
     * Posts in /news/_posts will have the category "news"
     *
     * Others combinations throw an exception.
     */
    protected function getCategory()
    {
        return rtrim($this->getCleanPath($this->sourcePath), DIRECTORY_SEPARATOR);
    }

    /**
     * Determine the target path.
     *
     * @param SplFileInfo $file
     *
     * @return string
     */
    protected function setTarget(SplFileInfo $file)
    {
        // Page extension
        $ext = $file->getExtension();

        // Twig templates are HTML
        if ($this->has('template') === false || $this->get('template') === 'none') {
            $targetExt = $ext;
        }
        else {
            $targetExt = 'html';
        }

        // Get clean source path
        $sourcePath = $this->getCleanPath($file->getRelativePathName());

        // Replace source extension with that of the template
        $this->target = substr($sourcePath, 0, -strlen($ext));
        $this->target .= $targetExt;
    }

    /**
     * Remove child group from path.
     *
     * @return string
     */
    protected function getCleanPath($path)
    {
        return $path;
    }

    /**
     * Get first paragraph from content
     *
     * @return string
     */
    protected function getDescription()
    {
        if (preg_match("/<p[^>]*>.+<\/p>/Us", $this->content, $matches)) {
            return strip_tags($matches[0]);
        }
    }

    /**
     * Magic method to determine if the
     * given metadata value exists.
     *
     * @param string $key
     *
     * @return mixed
     */
    public function __isset($key)
    {
        return $this->has($key);
    }

    /**
     * Magic method to get the specified
     * metadata value.
     *
     * @param string $key
     *
     * @return mixed
     */
    public function __get($key)
    {
        return $this->get($key);
    }
}
