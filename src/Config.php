<?php namespace Skosh;

use Symfony\Component\Yaml\Parser;

class Config
{
    /**
     * Config data loaded from config file.
     *
     * @var array
     */
    public $config = array();

    /**
     * Initializer.
     *
     * @param string $env   Environment
     * @param string $file  Config filename
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
     * @return mixed
     */
    public function get($key, $default = null)
    {
        return isset($this->config[$key]) ? $this->config[$key] : $default;
    }

    /**
     * Save array to file
     *
     * @param  string  $path
     * @return bool
     */
    public function export($path)
    {
        return file_put_contents($path, "<?php\nreturn " . var_export($this->config, true) . ";\n");
    }

    /**
     * Loads and parses the config file
     *
     * @param string $env   Environment
     * @param string $file  Config filename
     * @return void
     * @throws \Exception
     */
    private function load($env = 'local', $file = 'config')
    {
        $path = getcwd() . DIRECTORY_SEPARATOR;

        // File paths
        $configPath    = "{$path}{$file}.yml";
        $envConfigPath = "{$path}{$file}_{$env}.yml";

        if ( !file_exists($configPath)) {
            throw new \Exception("Config file not found at \"{$configPath}\".");
        }

        $data = file_get_contents($configPath);

        if ($data === false) {
            throw new \Exception("Unable to load configuration from: {$configPath}");
        }

        $yaml = new Parser();

        // Load config
        $config = $yaml->parse($data);
        $this->config = $config ? $config : [];

        // Load environment specific config
        if ( file_exists($envConfigPath))
        {
            $data = file_get_contents($envConfigPath);

            if($data)
            {
                $config = $yaml->parse($data);
                $config = $config ? $config : [];

                // Merge config values
                $this->config = array_merge($this->config, $config);
            }
        }
    }
}
