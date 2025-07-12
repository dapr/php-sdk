<?php

namespace Dapr\consistency;

/**
 * Class StrongLastWrite
 * @package Dapr\consistency
 * @codeCoverageIgnore Trivial
 */
class StrongLastWrite extends Consistency
{
    #[\Override]
    public static function instance(): StrongLastWrite
    {
        static $instance;
        return $instance ??= new StrongLastWrite();
    }

    #[\Override]
    public function get_consistency(): string
    {
        return self::STRONG;
    }

    #[\Override]
    public function get_concurrency(): string
    {
        return self::LAST_WRITE;
    }
}
