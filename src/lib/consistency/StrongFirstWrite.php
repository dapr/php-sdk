<?php

namespace Dapr\consistency;

/**
 * Class StrongFirstWrite
 * @package Dapr\consistency
 * @codeCoverageIgnore Trivial
 */
class StrongFirstWrite extends Consistency
{
    public function get_consistency(): string
    {
        return self::STRONG;
    }

    public function get_concurrency(): string
    {
        return self::FIRST_WRITE;
    }
}
