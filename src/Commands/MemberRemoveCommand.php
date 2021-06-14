<?php

namespace Diviky\Serverless\Commands;

use Laravel\VaporCli\Commands\MemberRemoveCommand as VaporMemberRemoveCommand;

class MemberRemoveCommand extends VaporMemberRemoveCommand
{
    use ExecuteTrait;
}
