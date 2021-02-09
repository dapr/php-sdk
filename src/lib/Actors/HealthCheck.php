<?php

namespace Dapr\Actors;

/**
 * Class HealthCheck
 * @package Dapr\Actors
 * @codeCoverageIgnore Overridden by user code.
 */
class HealthCheck
{
    /**
     * @return bool Whether the app is healthy or not
     */
    public function do_health_check(): bool
    {
        return true;
    }
}
