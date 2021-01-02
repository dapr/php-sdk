<?php

namespace Dapr\Actors;

use Attribute;
use Dapr\consistency\StrongFirstWrite;

#[Attribute(Attribute::TARGET_CLASS)]
class ActorState
{
    public function __construct(
        public string $store,
        public string $type,
        public string $consistency = StrongFirstWrite::class,
        public array $metadata = []
    ) {
    }
}
