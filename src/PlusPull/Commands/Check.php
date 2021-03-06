<?php
namespace PlusPull\Commands;

use Symfony\Component\Console\Input\Input;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Output\OutputInterface;

class Check extends AbstractCommand
{

    protected function configure()
    {
        $this->setName('check');
        $this->setDescription('Check pull requests');
        $this->addArgument(
            'config-file',
            InputArgument::OPTIONAL,
            'Path of the yaml configuration file',
            'config.yml'
        );
        $this->addOption(
            'pull',
            'p',
            InputOption::VALUE_NONE,
            'Pull the request if all conditions are met'
        );
        $this->addOption(
            'limit',
            'l',
            InputOption::VALUE_REQUIRED,
            'Maximum numbers of pull',
            1
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $config = $this->getYaml()->parse($input->getArgument('config-file'));

        if (!is_array($config) || empty($config)) {
            throw new \InvalidArgumentException('Empty or missing config file');
        }

        $github = $this->getGitHub();

        if (!empty($config['authorization']['token'])) {
            $github->authenticateWithToken($config['authorization']['token']);
        } else {
            $github->authenticate(
                $config['authorization']['username'],
                $config['authorization']['password']
            );
        }

        $maxPulls = $input->getOption('limit');

        if (!empty($config['repository'])) {
            $repositories = array($config['repository']);
        } else {
            $repositories = $config['repositories'];
        }

        foreach ($repositories as $repositoryConfig) {

            $username = $repositoryConfig['username'];
            $repository = $repositoryConfig['name'];
            $checkStatus = !empty($repositoryConfig['status']);

            $output->writeln("repository: $username/$repository");

            $plusRequired = 3;
            if (!empty($repositoryConfig['required'])) {
                $plusRequired = $repositoryConfig['required'];
            }

            $whitelist = null;
            if (!empty($repositoryConfig['whitelist'])) {
                $whitelist = $repositoryConfig['whitelist'];
            }

            $github->setRepository($username, $repository);

            foreach ($github->getPullRequests() as $pullRequest) {
                $pull = $input->getOption('pull');

                $output->write(
                    $pullRequest->number.' ('.$pullRequest->title.')'
                );

                if ($pullRequest->checkComments($plusRequired, $whitelist)) {
                    $output->write(' +1');
                } else {
                    $output->write(' -1');
                    $pull = false;
                }

                if ($checkStatus) {
                    if ($pullRequest->checkStatuses()) {
                        $output->write(' success');
                    } else {
                        $output->write(' fail');
                        $pull = false;
                    }
                }

                if ($pullRequest->isMergeable()) {
                    $output->write(' mergeable');
                } else {
                    $output->write(' conflicts');
                    $pull = false;
                }

                if ($pull) {
                    $github->merge($pullRequest->number);
                    $output->write(' pulled');
                    $maxPulls--;
                }

                $output->writeln('');

                if ($maxPulls<=0) {
                    break;
                }
            }
        }
    }
}
