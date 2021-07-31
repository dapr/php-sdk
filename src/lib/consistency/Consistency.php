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
    abstract public function get_consistency(): string;

    abstract public function get_concurrency(): string;

    abstract public static function instance(): Consistency;
}
