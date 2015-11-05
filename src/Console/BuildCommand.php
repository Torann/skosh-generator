<?php

namespace Skosh\Console;

use Skosh\Event;
use Skosh\Builder;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class BuildCommand extends Command
{
    /**
     * Skosh builder
     *
     * @var \Skosh\Builder
     */
    protected $builder;

    /**
     * Path to the target directory.
     *
     * @var string
     */
    private $target;

    protected function configure()
    {
        $this
            ->setName('build')
            ->setDescription('Renders the web site')
            ->addOption('env', 'e', InputOption::VALUE_OPTIONAL, 'Which environment to build for.', 'local')
            ->addOption('part', 'p', InputOption::VALUE_OPTIONAL,
                'Which part of the site to build [config, static, pages, or assets]', 'all')
            ->addOption('skip', 's', InputOption::VALUE_OPTIONAL,
                'Which part of the site to skip [config, static, pages, or assets]');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Get application instance
        $app = $this->getApplication();

        // Initialize builder
        $this->builder = new Builder($output, $app);

        // Get arguments
        $env = $app->getEnvironment();
        $part = $input->getOption('part');
        $skip = $input->getOption('skip');

        $isProduction = ($env === 'production');

        // Set system paths
        $this->target = $app->getTarget();

        // For debugging
        $output->writeln("Working directory: " . BASE_PATH);

        // Announce production build
        if ($isProduction) {
            $output->writeln("<info>Building production version...</info>");
        }

        // Remove all built files
        if (!$skip && $part === 'all') {
            $output->writeln("<comment>Cleaning target...</comment>");
            $this->builder->cleanTarget();
        }

        // Create server configuration
        if ($skip !== 'config' && in_array($part, ['all', 'config'])) {
            $output->writeln("<comment>Creating server configuration...</comment>");
            $this->builder->createServerConfig();
        }

        // Copy static files
        if ($skip !== 'static' && in_array($part, ['all', 'static'])) {
            $output->writeln("<comment>Copying statics...</comment>");
            $this->builder->copyStaticFiles();
        }

        // Build assets
        if ($skip !== 'assets' && in_array($part, ['all', 'assets'])) {
            $output->writeln("<comment>Building assets (gulp)...</comment>\n");
            $output->writeln(shell_exec(BASE_PATH . " gulp --target={$this->target} --env={$env}"));

            // Fire event
            Event::fire('assets.built');
        }

        // Build pages
        if ($skip !== 'pages' && in_array($part, ['all', 'pages'])) {
            $output->writeln("<comment>Building pages...</comment>");
            $this->builder->build();
        }

        $output->writeln("<info>Build complete!</info>");
    }
}
