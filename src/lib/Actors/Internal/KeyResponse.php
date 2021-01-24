<?php

namespace Dapr\Actors\Internal;

abstract class KeyResponse {
    public const SUCCESS = 200;
    public const KEY_NOT_FOUND = 204;
    public const ACTOR_NOT_FOUND = 400;
}
