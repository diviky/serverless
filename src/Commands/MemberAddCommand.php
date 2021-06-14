<?php

namespace Diviky\Serverless\Commands;

use Laravel\VaporCli\Commands\MemberAddCommand as VaporMemberAddCommand;

class MemberAddCommand extends VaporMemberAddCommand
{
    use ExecuteTrait;
}
