<?php

namespace Dapr\State;

use Dapr\consistency\Consistency;
use Dapr\consistency\StrongLastWrite;
use JetBrains\PhpStorm\Pure;

class StateItem
{
    #[Pure] public function __construct(
        public string $key,
        public mixed $value,
        public Consistency|null $consistency = null,
        public string|null $etag = null,
        public array $metadata = [],
    ) {
        if (empty($this->consistency)) {
            $this->consistency = new StrongLastWrite();
        }
    }
}
