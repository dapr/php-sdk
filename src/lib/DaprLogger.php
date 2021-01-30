<?php

namespace Dapr;

use DateTimeZone;
use Monolog\Logger;

class DaprLogger extends Logger
{
    public function __construct(
        string $name,
        array $handlers = [],
        array $processors = [],
        ?DateTimeZone $timezone = null
    ) {
        parent::__construct($name, $handlers, $processors, $timezone);
    }
}
