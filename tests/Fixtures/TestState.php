<?php

namespace Fixtures;

use Dapr\consistency\EventualLastWrite;
use Dapr\State\Attributes\StateStore;

#[StateStore('store', EventualLastWrite::class)]
class TestState
{
    public string $with_initial = "initial";
    public ?string $without_initial = null;
    public ?TestObj $complex = null;

    public function set_something()
    {
        $this->without_initial = 'something';
    }
}
