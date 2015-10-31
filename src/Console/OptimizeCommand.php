<?php namespace Skosh\Console;

use Skosh\Builder;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class OptimizeCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('optimize')
            ->setDescription('Optimize assets.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln("Optimizing");

        $fp = popen("gulp optimize", "r");

        while ( ! feof($fp) ) {
            $result = preg_replace('/\033\[[\d;]+m/','', fgets($fp, 4096) );
            $output->write( $result );
        }

        pclose($fp);

        // Get application instance
        $app = $this->getApplication();

        // Get builder
        $builder = new Builder($output, $app);
        $builder->copyStatics();
    }

}
