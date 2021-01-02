<?php

namespace Dapr\Actors;

abstract class ProxyModes {
    public const GENERATED = 0;
    public const GENERATED_CACHED = 1;
    public const DYNAMIC = 2;
}
