<?php

namespace Dapr\consistency;

/**
 * Class StrongFirstWrite
 * @package Dapr\consistency
 * @codeCoverageIgnore Trivial
 */
class StrongFirstWrite extends Consistency
{
    #[\Override]
    public static function instance(): StrongFirstWrite
    {
        static $instance;
        return $instance ??= new StrongFirstWrite();
    }

    #[\Override]
    public function get_consistency(): string
    {
        return self::STRONG;
    }

    #[\Override]
    public function get_concurrency(): string
    {
        return self::FIRST_WRITE;
    }
}
