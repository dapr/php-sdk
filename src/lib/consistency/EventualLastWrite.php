<?php

namespace Dapr\consistency;

/**
 * Class EventualLastWrite
 * @package Dapr\consistency
 */
class EventualLastWrite extends Consistency
{
    public function get_consistency(): string
    {
        return self::EVENTUAL;
    }

    public function get_concurrency(): string
    {
        return self::LAST_WRITE;
    }
}
