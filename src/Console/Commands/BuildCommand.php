<?php

namespace Skosh\Console\Commands;

use Skosh\Event;
use Skosh\Builder;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class BuildCommand extends Command
{
    /**
     * Path to the target directory.
     *
     * @var string
     */
    private $target;

    /**
     * {@inheritDoc}
     */
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

    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Get application instance
        $app = $this->getApplication();

        // Set CLI output
        $app->setOutput($output);

        // Initialize builder
        $builder = new Builder($app);

        // Get arguments
        $env = $app->getEnvironment();
        $part = $input->getOption('part');
        $skip = explode(',', preg_replace('/\s+/', '', $input->getOption('skip')));

        $isProduction = ($env === 'production');

        // Set system paths
        $this->target = $app->getTarget();

        // Announce production build
        if ($isProduction) {
            $output->writeln("<info>Building production version...</info>");
        }

        // Remove all built files
        if (empty($skip) && $part === 'all') {
            $output->writeln("<comment>Cleaning target...</comment>");
            $builder->cleanTarget();
        }

        // Create server configuration
        if (in_array('config', $skip) === false && in_array($part, ['all', 'config'])) {
            $output->writeln("<comment>Creating server configuration...</comment>");
            $builder->createServerConfig();
        }

        // Copy static files
        if (in_array('static', $skip) === false && in_array($part, ['all', 'static'])) {
            $output->writeln("<comment>Copying statics...</comment>");
            $builder->copyStaticFiles();
        }

        // Build assets
        if (in_array('assets', $skip) === false && in_array($part, ['all', 'assets'])) {
            $output->writeln("<comment>Building assets (gulp)...</comment>\n");
            $output->writeln(shell_exec("gulp --target={$this->target} --env={$env}"));

            // Fire event
            Event::fire('assets.built');
        }

        // Build pages
        if (in_array('pages', $skip) === false && in_array($part, ['all', 'pages'])) {
            $output->writeln("<comment>Building pages...</comment>");
            $builder->build();
        }

        $output->writeln("<info>Build complete!</info>");
    }
}
