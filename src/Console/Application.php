<?php namespace Skosh\Console;

use Skosh\Config;

use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Application extends BaseApplication
{
    const VERSION      = '0.1';

    /**
     * Site config.
     *
     * @var \Skosh\Config
     */
    protected $config;

    /**
     * Environment.
     *
     * @var string
     */
    protected $environment = 'local';

    /**
     * Set project name and version.
     */
    public function __construct()
    {
        parent::__construct('Skosk', self::VERSION);
    }

    /**
     * Runs the current application.
     *
     * @param InputInterface  $input  An Input instance
     * @param OutputInterface $output An Output instance
     *
     * @return int 0 if everything went fine, or an error code
     */
    public function doRun(InputInterface $input, OutputInterface $output)
    {
        $name = $this->getCommandName($input);

        // Did the user enter an environment option?
        if (true === $input->hasParameterOption(array('--env', '-e'))) {
            $this->environment = $input->getParameterOption(array('--env', '-e'));
        }

        // Publishing must be in an environment higher than local
        else if ($name === 'publish') {
            $this->environment = 'production';
        }

        // Initializing a new project is a special occasion
        if ($name === 'init') {
            $this->environment = 'initializing';
        }

        // Load config
        $this->config = new Config($this->environment);

        // Set local timezone
        date_default_timezone_set($this->getSetting('timezone', 'America/New_York'));

        return parent::doRun($input, $output);
    }

    /**
     * Get value from site config file.
     *
     * @param  string $key
     * @param  mixed  $default
     * @return mixed
     */
    public function getSetting($key, $default = null)
    {
        return $this->config->get($key, $default);
    }

    /**
     * Return environment state.
     *
     * @return string
     */
    public function getEnvironment()
    {
        return $this->environment;
    }

    /**
     * Return source directory for site.
     *
     * @return string
     */
    public function getSource()
    {
        return realpath(getcwd() . DIRECTORY_SEPARATOR . 'source');
    }

    /**
     * Return target directory for site.
     *
     * @return string
     */
    public function getTarget()
    {
        return getcwd() . $this->getSetting('target', 'public');
    }

    /**
     * Gets the default commands that should always be available.
     *
     * @return array An array of default Command instances
     */
    protected function getDefaultCommands()
    {
        $defaultCommands = parent::getDefaultCommands();

        $defaultCommands[] = new BuildCommand();
        $defaultCommands[] = new InitCommand();
        $defaultCommands[] = new ServeCommand();
        $defaultCommands[] = new OptimizeCommand();
        $defaultCommands[] = new PublishCommand();
        $defaultCommands[] = new WatchCommand();

        return $defaultCommands;
    }
}
