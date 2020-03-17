<?php

namespace Dan\Shopify\Laravel\Console;

use Illuminate\Console\Command;
use More\Laravel\Traits\Console\IdHelper;
use More\Laravel\Traits\Console\LogHelper;
use More\Laravel\Traits\Console\VerbosityHelper;

/**
 * Class AbstractCommand
 */
class AbstractCommand extends Command
{
    use IdHelper, VerbosityHelper, LogHelper;
}
