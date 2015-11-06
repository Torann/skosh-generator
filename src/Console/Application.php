<?php

namespace Skosh\Console;

use Skosh\Event;
use Skosh\Config;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Application as BaseApplication;

class Application extends BaseApplication
{
    const VERSION = '0.1';

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
     * Output object for writing to console.
     *
     * @var \Symfony\Component\Console\Output\OutputInterface
     */
    private $output;

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
        if (true === $input->hasParameterOption(['--env', '-e'])) {
            $this->environment = $input->getParameterOption(['--env', '-e']);
        }

        // Publishing must be in an environment higher than local
        else {
            if ($name === 'publish') {
                $this->environment = 'production';
            }
        }

        // Load config
        $this->config = new Config($this->environment);

        // Set local timezone
        date_default_timezone_set($this->getSetting('timezone', 'America/New_York'));

        // Register events
        $this->registerEvents();

        return parent::doRun($input, $output);
    }

    /**
     * Register custom events.
     */
    private function registerEvents()
    {
        foreach ($this->getSetting('events', []) as $event => $listeners) {
            foreach ($listeners as $listener) {
                Event::bind($event, $listener);
            }
        }
    }

    /**
     * Set CLI output.
     *
     * @param OutputInterface $output
     */
    public function setOutput(OutputInterface $output)
    {
        $this->output = $output;
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
        return realpath(BASE_PATH.'/source');
    }

    /**
     * Return target directory for site.
     *
     * @return string
     */
    public function getTarget()
    {
        return BASE_PATH . $this->getSetting('target', 'public');
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
        $defaultCommands[] = new ServeCommand();
        $defaultCommands[] = new PublishCommand();
        $defaultCommands[] = new WatchCommand();

        return $defaultCommands;
    }

    /**
     * Writes to console
     *
     * @param  string|array $messages
     * @return void
     */
    public function writeln($messages)
    {
        if ($this->output->isVerbose()) {
            $this->output->writeln($messages);
        }
    }
}
