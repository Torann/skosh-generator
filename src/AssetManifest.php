<?php namespace Skosh;

class AssetManifest
{
    /**
     * Environment.
     *
     * @var string
     */
    public $environment = 'local';

    /**
     * Asset manifest.
     *
     * @var array
     */
    public $manifest = array();

    /**
     * Initializer.
     *
     * @param string $env Environment
     */
    public function __construct($env = 'local')
    {
        $this->environment = $env;
        $this->load();

        // Register event
        Event::bind('assets.built', array($this, 'load'));
    }

    /**
     * Load manifest file
     */
    public function load()
    {
        $file = getcwd().DIRECTORY_SEPARATOR.'rev-manifest.json';

        if (file_exists($file)) {
            $this->manifest = json_decode(file_get_contents($file), true);
        }
    }

    /**
     * Get config value
     *
     * @param  string $key
     * @return mixed
     */
    public function get($key)
    {
        if ($this->environment === 'production' && isset($this->manifest[$key])) {
            return $this->manifest[$key];
        }

        return $key;
    }
}
