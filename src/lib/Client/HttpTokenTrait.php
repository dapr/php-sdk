<?php

namespace Dapr\Client;

/**
 * Trait HttpTokenTrait
 * @package Dapr\Client
 */
trait HttpTokenTrait
{
    static string|false|null $appToken = false;
    static string|false|null $daprToken = false;

    protected function getAppToken(): string|null
    {
        if (self::$appToken === false) {
            self::$appToken = getenv('APP_API_TOKEN') ?: null;
        }
        return self::$appToken;
    }

    protected function getDaprToken(): string|null
    {
        if (self::$daprToken === false) {
            self::$daprToken = getenv('DAPR_API_TOKEN') ?: null;
        }
        return self::$daprToken;
    }
}
