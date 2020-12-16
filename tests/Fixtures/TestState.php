<?php

namespace Fixtures;

use Dapr\State\State;

class TestState extends State
{
    public string $with_initial = "initial";
    public ?string $without_initial = null;
    public ?TestObj $complex = null;

    public function set_something()
    {
        $this->without_initial = 'something';
    }
}
