<?php

namespace Dapr\consistency;

/**
 * Class EventualFirstWrite
 *
 * @package Dapr\consistency
 * @codeCoverageIgnore Trivial
 */
class EventualFirstWrite extends Consistency
{
    #[\Override]
    public static function instance(): EventualFirstWrite
    {
        static $instance;
        return $instance ??= new EventualFirstWrite();
    }

    #[\Override]
    public function get_consistency(): string
    {
        return self::EVENTUAL;
    }

    #[\Override]
    public function get_concurrency(): string
    {
        return self::FIRST_WRITE;
    }
}
