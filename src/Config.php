<?php

namespace Skosh;

use Symfony\Component\Yaml\Yaml;

class Config
{
    /**
     * Config data loaded from config file.
     *
     * @var array
     */
    public $config = [];

    /**
     * Initializer.
     *
     * @param string $env
     * @param string $file
     *
     * @throws \Exception
     */
    public function __construct($env = 'local', $file = 'config')
    {
        if ($env !== 'initializing') {
            $this->load($env, $file);
        }
    }

    /**
     * Get config value
     *
     * @param      $key
     * @param null $default
     *
     * @return mixed
     */
    public function get($key, $default = null)
    {
        return isset($this->config[$key]) ? $this->config[$key] : $default;
    }

    /**
     * Save array to file
     *
     * @param string $path
     *
     * @return bool
     */
    public function export($path)
    {
        return file_put_contents($path, "<?php\nreturn " . var_export($this->config, true) . ";\n");
    }

    /**
     * Loads and parses the config file
     *
     * @param string $env Environment
     * @param string $file Config filename
     *
     * @return void
     * @throws \Exception
     */
    private function load($env = 'local', $file = 'config')
    {
        $path = BASE_PATH . ($file === '.env' ? '' : '/config');

        // File paths
        $configPath = "{$path}/{$file}.yml";
        $envConfigPath = "{$path}/{$file}_{$env}.yml";

        if (file_exists($configPath) === false) {
            throw new \Exception("Config file not found at \"{$configPath}\".");
        }

        // Load config
        $config = Yaml::parse(file_get_contents($configPath));
        $this->config = $config ? $config : [];

        // Load environment specific config
        if (file_exists($envConfigPath)) {
            $config = Yaml::parse(file_get_contents($envConfigPath));
            $config = $config ? $config : [];

            // Merge config values
            $this->config = array_merge($this->config, $config);
        }
    }
}
