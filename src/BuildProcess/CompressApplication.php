<?php

namespace Diviky\Serverless\BuildProcess;

use Laravel\VaporCli\BuildProcess\CompressApplication as BuildProcessCompressApplication;
use Laravel\VaporCli\Helpers;

class CompressApplication extends BuildProcessCompressApplication
{
    /**
     * Ensure the application archive is within supported size limits.
     *
     * @param  float  $bytes
     * @return void
     */
    protected function ensureArchiveIsWithinSizeLimits($bytes)
    {
        $size = ceil($bytes / 1048576);

        if ($size > 250) {
            Helpers::line();
            Helpers::warn('Application is greater than 250MB. Your application is ' . $size . 'MB.');
        }
    }
}
