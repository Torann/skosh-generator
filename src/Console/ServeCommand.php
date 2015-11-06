<?php

namespace Skosh\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ServeCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('serve')
            ->setDescription('Serve source and regenerate site as changes are made.')
            ->addOption('env', 'e', InputOption::VALUE_OPTIONAL, 'Which environment to build for.', 'local')
            ->addOption('port', 'p', InputOption::VALUE_OPTIONAL, 'Port to listen on.', 8000)
            ->addOption('host', 'H', InputOption::VALUE_OPTIONAL, 'Host to listen on.', 'localhost');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Get application instance
        $app = $this->getApplication();

        // Set CLI output
        $app->setOutput($output);

        // Get target for Gulp
        $target = $app->getTarget();

        // Get arguments
        $port = $input->getOption('port');
        $host = $input->getOption('host');

        // Was the build command ran?
        if (!file_exists($target . DIRECTORY_SEPARATOR . 'index.html'))
        {
            // Create production build command
            $command = $app->find('build');

            $buildInput = new ArrayInput([
                'command' => 'build',
                '--env' => 'local'
            ]);

            // Run production build command
            $command->run($buildInput, $output);
        }

        $fp = popen("gulp serve --target={$target} --port={$port} --host={$host}", "r");

        while (!feof($fp)) {
            $result = preg_replace('/\033\[[\d;]+m/', '', fgets($fp, 4096));
            $output->write($result);
        }

        pclose($fp);
    }

}
