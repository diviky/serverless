<?php

namespace Diviky\Serverless\Commands;

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

        $this->vapor = Helpers::app(ConsoleVaporClient::class);

        Helpers::app()->instance('input', $this->input = $input);
        Helpers::app()->instance('output', $this->output = $output);

        $this->configureOutputStyles($output);

        return Helpers::app()->call([$this, 'handle']) ?: 0;
    }
}