<?php

namespace Skosh\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class WatchCommand extends Command
{
    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        $this->setName('watch')
            ->setDescription('Regenerate site as changes are made.');
    }

    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $fp = popen("gulp watch", "r");

        while (! feof($fp)) {
            $result = preg_replace('/\033\[[\d;]+m/', '', fgets($fp, 4096));
            $output->write($result);
        }

        pclose($fp);
    }
}
