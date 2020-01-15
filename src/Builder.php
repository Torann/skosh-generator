<?php

namespace Skosh;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Filesystem\Filesystem;

use Twig_Environment;
use Twig_Loader_Chain;
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
     *
     * @param Application $app
     *
     * @throws \Exception
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->site = new Site($app);

        // Set asset manifest
        $this->manifest = new AssetManifest($this->app->getEnvironment());

        // Set system paths
        $this->target = $this->app->getTarget();
        $this->source = $this->app->getSource();

        // Check paths
        $fs = new Filesystem();
        if ($fs->exists($this->source) === false) {
            throw new \Exception("Source folder not found at \"{$this->source}\".");
        }

        // Ensure we have a target
        if ($fs->exists($this->target) === false) {
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

        // Make the site variable global
        $this->twig->addGlobal('site', $this->site);

        // Add an escaper for XML
        $this->twig->getExtension('core')->setEscaper('xml', function ($env, $content) {
            return htmlentities($content, ENT_COMPAT | ENT_XML1);
        });

        // Add build in extensions
        $this->twig->addExtension(new Twig\Extension($this));
        $this->registerTwigExtensions();

        // Fire booted event
        Event::fire('builder.booted', [$this]);
    }

    /**
     * Register custom twig extensions.
     */
    private function registerTwigExtensions()
    {
        foreach ($this->app->getSetting('twig_extensions', []) as $extension) {
            $this->twig->addExtension(new $extension($this));
        }
    }

    /**
     * Return Twig Environment.
     *
     * @return Twig_Environment
     */
    public function getTwigEnvironment()
    {
        return $this->twig;
    }

    /**
     * Return site.
     *
     * @return Site
     */
    public function getSite()
    {
        return $this->site;
    }

    /**
     * Get the URL for the given page.
     *
     * @param string $url
     *
     * @return string
     */
    public function getUrl($url)
    {
        if (empty($url) || starts_with($url, ['#', '//', 'mailto:', 'tel:', 'http'])) {
            return $url;
        }

        // Get URL root
        $root = $this->app->getSetting('url');

        $url = trim($root, '/') . '/' . trim($url, '/');

        // Force trailing slash
        if ($this->app->getSetting('url_trailing_slash', false)
            && ! strrchr(basename($url), '.')
        ) {
            $url = "{$url}/";
        }

        return $url;
    }

    /**
     * Check asset manifest for a file
     *
     * @param string $path
     *
     * @return string
     */
    public function getAsset($path)
    {
        // Absolute path will not be in manifest
        if ($path[0] === '/') {
            return $this->getUrl($path);
        }

        // Get manifest
        $asset = $this->manifest->get($path);

        return $this->getUrl('/assets/' . trim($asset, '/'));
    }

    /**
     * Renders the site
     *
     * @return void
     * @throws \Exception
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     * @throws \Twig\Error\LoaderError
     */
    public function build()
    {
        $this->app->writeln("\n<comment>Adding root pages</comment>");
        $this->addPages('\\Skosh\\Content\\Page');

        $this->app->writeln("\n<comment>Adding posts</comment>");
        $this->addPages('\\Skosh\\Content\\Post', 'path', '_posts');

        $this->app->writeln("\n<comment>Adding docs</comment>");
        $this->addPages('\\Skosh\\Content\\Doc', 'path', '_doc');

        // Sort pages
        $this->sortPosts();

        // Fire event
        Event::fire('pages.sorted', [$this]);

        $this->app->writeln("\n<comment>Rendering content</comment>");

        foreach ($this->site->pages as $content) {
            $this->renderContent($content);
        }

        // Fire event
        Event::fire('pages.rendered', [$this]);
    }

    /**
     * Renders content
     *
     * @param Content $content
     *
     * @return bool
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     * @throws \Twig\Error\LoaderError
     */
    private function renderContent(Content $content)
    {
        $tpl = $content->has('template') ? " <comment>($content->template)</comment>" : "";
        $this->app->writeln("Rendering: <info>{$content->target}</info>{$tpl}");

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

        return true;
    }

    /**
     * Save page to target file.
     *
     * @param string $html
     * @param string $target
     *
     * @return void
     */
    public function savePage($target, $html)
    {
        $fs = new Filesystem();
        $fs->dumpFile($this->target . DIRECTORY_SEPARATOR . $target, $html);
    }

    /**
     * Get parent content.
     *
     * @param string $parentId
     *
     * @return array
     */
    public function getParent($parentId)
    {
        if ($parentId && isset($this->site->pages[$parentId])) {
            return $this->site->pages[$parentId];
        }

        return [];
    }

    /**
     * Get posts for given content.
     *
     * @param Content $content
     *
     * @return array
     */
    public function getPosts(Content $content)
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
     * @throws \Exception
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
        $exclude = ['js', 'javascripts', 'stylesheets', 'less', 'sass'];

        // Include the excludes from the config
        $exclude = array_merge($exclude, (array) $this->app->getSetting('exclude', []));

        // Create pattern
        $pattern = '/\\.(' . implode("|", $exclude) . ')$/';

        // Get list of files & directories to copy
        $to_copy = (array) $this->app->getSetting('copy', []);

        // Assets folder is hardcoded into copy
        $to_copy = array_merge(['assets'], $to_copy);
        $to_copy = array_unique($to_copy);

        // Initialize file system
        $filesystem = new Filesystem();

        // Fire event
        if ($response = Event::fire('copy.before', [$this, $to_copy])) {
            $to_copy = $response[0];
        }

        // Copy
        foreach ($to_copy as $location) {
            $fileInfo = new \SplFileInfo($this->source . DIRECTORY_SEPARATOR . $location);

            // Copy a complete directory
            if ($fileInfo->isDir()) {
                $finder = new Finder();
                $finder->files()
                    ->exclude($exclude)
                    ->notName($pattern)
                    ->in($this->source . DIRECTORY_SEPARATOR . $location);

                foreach ($finder as $file) {
                    $path = $location . DIRECTORY_SEPARATOR . $file->getRelativePathname();
                    echo "$path\n";
                    $source = $file->getRealPath();
                    $target = $this->target . DIRECTORY_SEPARATOR . $path;

                    $filesystem->copy($source, $target);

                    $this->app->writeln("Copied: <info>$path</info>");
                }
            } // Copy Single File
            else {
                $filesystem->copy($fileInfo->getRealPath(), $this->target . DIRECTORY_SEPARATOR . $location);
                $this->app->writeln("Copied: <info>$location</info>");
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

        // Fire event
        Event::fire('target.cleaned', [$this]);
    }

    /**
     * Add pages for rendering
     *
     * @param string $class
     * @param string $path
     * @param string $filter
     *
     * @return void
     * @throws \Exception
     */
    private function addPages($class, $path = 'notPath', $filter = '_')
    {
        $finder = (new Finder())
            ->files()
            ->in($this->source)
            ->$path($filter)
            ->name('/\\.(md|textile|xml|twig)$/');

        foreach ($finder as $file) {
            $page = new $class($file, $this);

            // Skip drafts in production
            if ($this->app->isProduction() && $page->status === 'draft') {
                $this->app->writeln("Skipping draft: <info>{$page->sourcePath}/{$page->filename}</info>");
                continue;
            }

            $this->app->writeln("Adding: <info>{$page->sourcePath}/{$page->filename}</info>");
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
        $this->app->writeln("\n<comment>Sorting</comment>");

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

        $this->app->writeln("Done!");
    }

    /**
     * Generate pagination
     *
     * @param Content $content
     *
     * @return bool
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     * @throws \Twig\Error\LoaderError
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

            // Set page target filename
            $target = ($pageNumber > 1) ? "{$pageRoot}/page/{$pageNumber}" : $content->target;

            // Previous page is index
            if ($pageNumber === 2) {
                $pagination['prev'] = $this->getUrl($pageRoot);
            } // Set previous page
            elseif ($pageNumber > 1) {
                $pagination['prev'] = $this->getUrl("{$pageRoot}/page/" . ($pageNumber - 1));
            } // No previous page
            else {
                $pagination['prev'] = null;
            }

            // Set next page
            if ($pageNumber + 1 <= $pagination['total_pages']) {
                $pagination['next'] = $this->getUrl("{$pageRoot}/page/" . ($pageNumber + 1));
            } // No next page
            else {
                $pagination['next'] = null;
            }

            // Set current page
            $pagination['page'] = $pageNumber;

            // Set page URL
            $content->url = ($pageNumber > 1) ? $this->getUrl(dirname($target)) : $content->url;

            // Render content
            $html = $this->twig->render($content->id, [
                'page' => $content,
                'posts' => $slice,
                'pagination' => $pagination,
                'parent' => $this->getParent($content->parentId)
            ]);

            // Fire event
            Event::fire('paginate.before', [$this, &$target, &$html]);

            // Save Content
            $this->savePage($target, $html);
        }

        return true;
    }
}
