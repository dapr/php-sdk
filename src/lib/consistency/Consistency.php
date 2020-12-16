<?php

namespace Dapr\consistency;

/**
 * Class Consistency
 *
 * Abstract base class
 *
 * @package Dapr\consistency
 */
abstract class Consistency
{
    protected const EVENTUAL = "eventual";
    protected const STRONG = "strong";
    protected const FIRST_WRITE = "first-write";
    protected const LAST_WRITE = "last-write";

    /**
     * Get the consistency string setting
     *
     * @return string
     */
    public abstract function get_consistency(): string;

    public abstract function get_concurrency(): string;
}
