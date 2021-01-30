<?php

namespace Fixtures;

use Dapr\consistency\EventualLastWrite;
use Dapr\DaprClient;
use Dapr\State\Attributes\StateStore;
use Dapr\State\StateManager;
use Dapr\State\TransactionalState;
use Psr\Log\LoggerInterface;

#[StateStore('store', EventualLastWrite::class)]
class TestState extends TransactionalState
{
    public string $with_initial = "initial";
    public ?string $without_initial = null;
    public ?TestObj $complex = null;

    public function __construct()
    {
        parent::__construct();
    }

    public function set_something()
    {
        $this->without_initial = 'something';
    }
}
