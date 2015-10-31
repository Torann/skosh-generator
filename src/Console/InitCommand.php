<?php namespace Skosh\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class InitCommand extends Command
{
    protected function configure()
    {
        $this->setName('init')
            ->setDescription('Initialize a new web site.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Don't initialize if already done
        if(is_dir(BASE_PATH . DIRECTORY_SEPARATOR . 'node_modules')) {
            $output->writeln("<error>Site already initialized.</error>");
            return;
        }

        $this->initGulp($output);

        $output->writeln("<info>New site initialized.</info>");
    }

    private function initGulp($output)
    {
        $output->writeln(shell_exec("npm install"));
    }
}
