<?php

namespace Diviky\Serverless\Commands;

use Laravel\VaporCli\Commands\LocalCommand as VaporLocalCommand;

class LocalCommand extends VaporLocalCommand
{
    use ExecuteTrait;
}
