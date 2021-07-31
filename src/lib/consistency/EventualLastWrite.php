<?php

namespace Dapr\consistency;

/**
 * Class EventualLastWrite
 * @package Dapr\consistency
 * @codeCoverageIgnore Trivial
 */
class EventualLastWrite extends Consistency
{
    public static function instance(): EventualLastWrite
    {
        static $instance;
        return $instance ??= new EventualLastWrite();
    }

    public function get_consistency(): string
    {
        return self::EVENTUAL;
    }

    public function get_concurrency(): string
    {
        return self::LAST_WRITE;
    }
}
