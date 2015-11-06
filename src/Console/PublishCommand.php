<?php

namespace Skosh\Console;

use Skosh\Config;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PublishCommand extends Command
{
    /**
     * Remote server config.
     *
     * @var \Skosh\Config
     */
    public $config;

    /**
     * Shell commands.
     *
     * @var array
     */
    public $servers = [
        'ssh' => 'rsync',
        'ftp' => 'lftp'
    ];

    protected function configure()
    {
        $this
            ->setName('publish')
            ->setDescription('Publish site to production server.')
            ->addArgument(
                'server',
                InputArgument::REQUIRED,
                'Target host (ftp/ssh).'
            )
            ->addOption('env', 'e', InputOption::VALUE_OPTIONAL, 'Which environment to publish to.', 'production');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Get application instance
        $app = $this->getApplication();

        // Set CLI output
        $app->setOutput($output);

        // Get arguments
        $server = strtolower($input->getArgument('server'));

        // Load remote configurations
        $this->config = new Config('production', '.remote');

        // Validate chosen server before everything
        $this->validateServer($server);

        // Create production build command
        $command = $app->find('build');

        $buildInput = new ArrayInput([
            'command' => 'build',
            '--env' => 'production'
        ]);

        // Run production build command
        $command->run($buildInput, $output);

        $output->writeln("<comment>Publishing to server</comment>\n");

        // Call method
        $method = "publish_$server";
        $this->$method($output, $app->getTarget());

        $output->writeln("<comment>Done!</comment>\n");
    }

    /**
     * Check existence of remote server.
     *
     * @param string $server
     * @return bool
     * @throws \Exception
     */
    protected function validateServer($server)
    {
        if (in_array($server, array_keys($this->servers)))
        {
            // Get server options
            $options = $this->config->get($server);

            if (!$options || $options['host'] === 'yoursite') {
                throw new \Exception("Remote server \"{$server}\" not setup in remote.yml.");
            }

            if (shell_exec("which {$this->servers[$server]}") == '') {
                throw new \Exception("Shell command '' is required to publish using {$server}");
            }

            return true;
        }

        throw new \Exception("Unknown remote server: \"{$server}\"");
    }

    /**
     * Publish using SSH.
     *
     * @param  OutputInterface $output
     * @param  string          $target
     * @return bool
     * @throws \Exception
     */
    protected function publish_ssh(OutputInterface $output, $target)
    {
        // Get FTP config
        $config = $this->config->get('ssh');

        // Ensure there isn't a leading slash
        $remote_dir = ltrim($config['remote_dir'], '/');

        // Shell command
        $output->writeln(shell_exec("rsync -avze 'ssh -p {$config['port']}' {$target} {$config['user']}@{$config['host']}:{$remote_dir} --exclude .DS_Store --exclude downloads.json --exclude cache/"));

        return true;
    }

    /**
     * Publish using FTP.
     *
     * @param  OutputInterface $output
     * @param  string          $target
     * @return bool
     * @throws \Exception
     */
    protected function publish_ftp(OutputInterface $output, $target)
    {
        // Get FTP config
        $config = $this->config->get('ftp');

        // Ensure there isn't a leading slash
        $remote_dir = ltrim($config['remote_dir'], '/');

        // Shell command
        $output->writeln(shell_exec("lftp -c \"set ftp:list-options -a;
open ftp://{$config['user']}:{$config['pass']}@{$config['host']}/{$remote_dir};
lcd {$target};
mirror --reverse --only-newer --use-cache --verbose --allow-chown
--allow-suid --no-umask --parallel=2 --exclude=.DS_Store --exclude=downloads.json --exclude=cache/\""));

        return true;
    }
}
