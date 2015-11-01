<?php

namespace Skosh;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Console\Output\OutputInterface;

use Twig_Environment;
use Twig_Loader_Chain;
use Twig_Extension_Debug;
use Twig_Loader_Filesystem;

use Skosh\Content\Doc;
use Skosh\Content\Content;
use Skosh\Console\Application;

class Builder
{
    /**
     * Path to the source directory.
     *
     * @var string
     */
    private $source;

    /**
     * Path to the target directory.
     *
     * @var string
     */
    public $target;

    /**
     * Application instance.
     *
     * @var \Skosh\Console\Application
     */
    public $app;

    /**
     * Output object for writing to console.
     *
     * @var \Symfony\Component\Console\Output\OutputInterface
     */
    private $output;

    /**
     * Site objects holds the compiled site data.
     *
     * @var \Skosh\Site
     */
    private $site;

    /**
     * Site objects holds the compiled site data.
     *
     * @var \Skosh\AssetManifest
     */
    private $manifest;

    /**
     * Twig template rendering.
     *
     * @var \Twig_Environment
     */
    private $twig;

    /**
     * Initializer.
     */
    public function __construct(OutputInterface $output, Application $app)
    {
        $this->output = $output;
        $this->app = $app;
        $this->site = new Site($app);

        // Set asset manifest
        $this->manifest = new AssetManifest($this->app->getEnvironment());

        // Set system paths
        $this->target = $this->app->getTarget();
        $this->source = $this->app->getSource();

        // Check paths
        $fs = new Filesystem();
        if (!$fs->exists($this->source)) {
            throw new \Exception("Source folder not found at \"{$this->source}\".");
        }

        // Ensure we have a target
        if (!$fs->exists($this->target)) {
            $fs->mkdir($this->target);
        }

        // Setup a twig loader
        $loader = new Twig_Loader_Chain();
        $loader->addLoader(new Twig\Loader($this->site));

        // If a template directory exists, add a filesystem loader to resolve
        // templates residing within it
        $templateDir = $this->source . DIRECTORY_SEPARATOR . "_templates";
        if (is_dir($templateDir)) {
            $loader->addLoader(new Twig_Loader_Filesystem($templateDir));
        }

        $includesDir = $this->source . DIRECTORY_SEPARATOR . "_includes";
        if (is_dir($includesDir)) {
            $loader->addLoader(new Twig_Loader_Filesystem($includesDir));
        }

        // Full template rendering
        $this->twig = new Twig_Environment($loader, ['debug' => true]);
        $this->twig->addExtension(new Twig_Extension_Debug());
        $this->twig->addExtension(new Twig\Extension($this, [
            'site' => $this->site
        ]));
    }

    /**
     * Check asset manifest for a file
     *
     * @return string
     */
    public function getAsset($path)
    {
        if (starts_with($path, ['#', '//', 'mailto:', 'tel:', 'http'])) {
            return $path;
        }

        // Get site URL from config
        $root = $this->app->getSetting('url');

        // Absolute path will not be in manifest
        if ($path[0] === '/') {
            return $root . $path;
        }

        // Check manifest
        $asset = $this->manifest->get($path);

        return $root . '/assets/' . trim($asset, '/');
    }

    /**
     * Renders the site
     *
     * @return void
     */
    public function build()
    {
        $this->writeln("\n<comment>Adding root pages</comment>");
        $this->addPages('\\Skosh\\Content\\Page');

        $this->writeln("\n<comment>Adding posts</comment>");
        $this->addPages('\\Skosh\\Content\\Post', 'path', '_posts');

        $this->writeln("\n<comment>Adding docs</comment>");
        $this->addPages('\\Skosh\\Content\\Doc', 'path', '_doc');

        $this->sortPosts();

        $this->writeln("\n<comment>Rendering content</comment>");

        foreach ($this->site->pages as $page) {
            $this->renderContent($page);
        }
    }

    /**
     * Renders content
     *
     * @return mixed
     */
    private function renderContent(Content $content)
    {
        $tpl = $content->has('template') ? " <comment>($content->template)</comment>" : "";
        $this->writeln("Rendering: <info>{$content->target}</info>{$tpl}");

        // Only template files are run through Twig (template can be "none")
        if ($content->has('template')) {
            if ($content->paginate) {
                return $this->paginate($content);
            }
            else {
                $html = $this->twig->render($content->id, [
                    'page' => $content,
                    'posts' => $this->getPosts($content),
                    'parent' => $this->getParent($content->parentId)
                ]);
            }
        }
        else {

            $template = $this->twig->createTemplate($content->content);
            $html = $template->render([]);
        }

        // Save Content
        $this->savePage($content->target, $html);
    }

    public function savePage($target, $html)
    {
        $fs = new Filesystem();
        $fs->dumpFile($this->target . DIRECTORY_SEPARATOR . $target, $html);
    }

    public function getParent($parentId)
    {
        if ($parentId && isset($this->site->pages[$parentId])) {
            return $this->site->pages[$parentId];
        }

        return [];
    }

    public function getPosts($content)
    {
        if (isset($this->site->categories[$content->id])) {
            return $this->site->categories[$content->id];
        }
        else {
            if (isset($content->category) && isset($this->site->categories[$content->category])) {
                return $this->site->categories[$content->category];
            }
        }

        return [];
    }

    /**
     * Create a server config file
     *
     * @return void
     */
    public function createServerConfig()
    {
        // Load config
        $config = new Config($this->app->getEnvironment(), '.env');

        // Save to protected file
        $config->export($this->target . DIRECTORY_SEPARATOR . '.env.php');
    }

    /**
     * Copy static files to target
     * Ignoring JS, CSS & LESS - Gulp handles that
     *
     * @return void
     */
    public function copyStaticFiles()
    {
        $exclude = ['less', 'js', 'css'];

        // Include the excludes from the config
        $exclude = array_merge($exclude, (array)$this->app->getSetting('exclude', []));

        // Create pattern
        $pattern = '/\\.(' . implode("|", $exclude) . ')$/';

        // Get list of files & directories to copy
        $to_copy = (array)$this->app->getSetting('copy', []);

        // Assets folder is hardcoded into copy
        $to_copy = array_merge(['assets'], $to_copy);
        $to_copy = array_unique($to_copy);

        $fs = new Filesystem();

        foreach ($to_copy as $location) {
            $fileInfo = new \SplFileInfo($this->source . DIRECTORY_SEPARATOR . $location);

            // Copy a complete directory
            if ($fileInfo->isDir()) {
                $finder = new Finder();
                $finder->files()
                    ->in($this->source . DIRECTORY_SEPARATOR . $location)
                    ->notName($pattern);

                foreach ($finder as $file) {
                    $path = $location . DIRECTORY_SEPARATOR . $file->getRelativePathname();

                    $source = $file->getRealPath();
                    $target = $this->target . DIRECTORY_SEPARATOR . $path;

                    $fs->copy($source, $target);

                    $this->writeln("Copied: <info>$path</info>");
                }
            }

            // Copy Single File
            else {
                $fs->copy($fileInfo->getRealPath(), $this->target . DIRECTORY_SEPARATOR . $location);
                $this->writeln("Copied: <info>$location</info>");
            }
        }
    }

    /**
     * Clean target directory
     *
     * @return void
     */
    public function cleanTarget()
    {
        $filesystem = new Filesystem();

        // Get files and directories to remove
        $files = array_diff(scandir($this->target), ['.', '..']);
        $files = preg_grep('/[^.gitignore]/i', $files);

        // Remove files
        foreach ($files as $file) {
            $filesystem->remove("$this->target/$file");
        }
    }

    /**
     * Add pages for rendering
     *
     * @param  string $class
     * @param  string $path
     * @param  string $filter
     * @return void
     */
    private function addPages($class, $path = 'notPath', $filter = '_')
    {
        $finder = new Finder();
        $finder->files()
            ->in($this->source)
            ->$path($filter)
            ->name('/\\.(md|textile|xml|twig)$/');

        foreach ($finder as $file) {
            $page = new $class($file, $this);
            $this->writeln("Adding: <info>{$page->sourcePath}/{$page->filename}</info>");
            $this->site->addContent($page);
        }
    }

    /**
     * Sorts posts by date (descending) or by
     * chapter (ascending). Assigns post.next and post.prev
     *
     * @return void
     */
    private function sortPosts()
    {
        $this->writeln("\n<comment>Sorting</comment>");

        $cmpFn = function (Content $one, Content $other) {
            // Sort by chapters
            if ($one instanceof Doc && $other instanceof Doc) {
                if ($one->chapter == $other->chapter) {
                    return 0;
                }

                return ($one->chapter < $other->chapter) ? -1 : 1;
            }

            // Sort by date
            if ($one->date == $other->date) {
                return 0;
            }

            return ($one->date > $other->date) ? -1 : 1;
        };

        foreach ($this->site->categories as $cat => &$posts) {
            // Sort posts
            usort($posts, $cmpFn);

            // Assign next and previous post within the category
            foreach ($posts as $key => $post) {
                if (isset($posts[$key - 1])) {
                    $post->next = $posts[$key - 1];
                }
                if (isset($posts[$key + 1])) {
                    $post->prev = $posts[$key + 1];
                }
            }
        }

        $this->writeln("Done!");
    }

    /**
     * Generate pagination
     *
     * @param  Content $content
     * @return void
     */
    private function paginate(Content $content)
    {
        $maxPerPage = $this->app->getSetting('max_per_page', 15);
        $posts = $this->getPosts($content);

        $slices = [];
        $slice = [];
        $totalItems = 0;

        foreach ($posts as $k => $v) {
            if (count($slice) === $maxPerPage) {
                $slices[] = $slice;
                $slice = [];
            }

            $slice[$k] = $v;
            $totalItems++;
        }

        $slices[] = $slice;

        // Base URL
        $pageRoot = '/' . dirname($content->target);

        // Pagination data
        $pagination = [
            'total_posts' => count($posts),
            'total_pages' => count($slices),
            'next' => null,
            'prev' => null
        ];

        $pageNumber = 0;
        foreach ($slices as $slice) {
            $pageNumber++;

            $target = ($pageNumber > 1) ? "{$pageRoot}/page/{$pageNumber}/index.html" : $content->target;

            // Get previous page
            if ($pageNumber > 1) {
                $pagination['prev'] = ($pageNumber === 2) ? $pageRoot : "{$pageRoot}/page/" . ($pageNumber - 1);
            }
            else {
                $pagination['prev'] = null;
            }

            // Get next page
            if ($pageNumber + 1 <= $pagination['total_pages']) {
                $pagination['next'] = "{$pageRoot}/page/" . ($pageNumber + 1);
            }
            else {
                $pagination['next'] = null;
            }

            // Set current page
            $pagination['page'] = $pageNumber;

            // Set page URL
            $content->url = ($pageNumber > 1) ? dirname($target) : $content->url;

            // Render content
            $html = $this->twig->render($content->id, [
                'page' => $content,
                'posts' => $slice,
                'pagination' => $pagination,
                'parent' => $this->getParent($content->parentId)
            ]);

            // Save Content
            $this->savePage($target, $html);
        }
    }

    /**
     * Writes to console
     *
     * @return void
     */
    private function writeln($msg)
    {
        if ($this->output->isVerbose()) {
            $this->output->writeln($msg);
        }
    }
}
