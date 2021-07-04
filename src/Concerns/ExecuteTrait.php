<?php

namespace Diviky\Serverless\Concerns;

use DateTime;
use Diviky\Serverless\ConsoleVaporClient;
use Laravel\VaporCli\Helpers;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

trait ExecuteTrait
{
    /**
     * Execute the command.
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->startedAt = new DateTime();

        $_ENV['VAPOR_API_TOKEN'] = \uniqid();

        $this->vapor = Helpers::app(ConsoleVaporClient::class);

        Helpers::app()->instance('input', $this->input = $input);
        Helpers::app()->instance('output', $this->output = $output);
        Helpers::app()->instance('manifest', \getcwd() . '/serverless.yml');

        $this->configureOutputStyles($output);

        return Helpers::app()->call([$this, 'handle']) ?: 0;
    }
}
