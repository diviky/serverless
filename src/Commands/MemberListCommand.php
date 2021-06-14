<?php

namespace Diviky\Serverless\Commands;

use Laravel\VaporCli\Commands\MemberListCommand as VaporMemberListCommand;

class MemberListCommand extends VaporMemberListCommand
{
    use ExecuteTrait;
}
