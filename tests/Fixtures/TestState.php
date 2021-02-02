<?php

namespace Fixtures;

use Dapr\consistency\EventualLastWrite;
use Dapr\State\Attributes\StateStore;
use Dapr\State\TransactionalState;
use DI\FactoryInterface;
use Psr\Container\ContainerInterface;

#[StateStore('store', EventualLastWrite::class)]
class TestState extends TransactionalState
{
    public string $with_initial = "initial";
    public ?string $without_initial = null;
    public ?TestObj $complex = null;

    public function __construct(ContainerInterface $container, FactoryInterface $factory)
    {
        parent::__construct($container, $factory);
    }

    public function set_something()
    {
        $this->without_initial = 'something';
    }
}
