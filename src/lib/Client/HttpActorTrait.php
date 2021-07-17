<?php

namespace Dapr\Client;

use Dapr\Actors\IActorReference;
use Dapr\Actors\Reminder;
use Dapr\State\Internal\Transaction;

trait HttpActorTrait {
    public function invokeActorMethod(
        string $httpMethod,
        IActorReference $actor,
        string $method,
        string $as = 'array'
    ): mixed {
        // TODO: Implement invokeActorMethod() method.
    }

    public function saveActorState(IActorReference $actor, Transaction $transaction): bool
    {
        // TODO: Implement saveActorState() method.
    }

    public function getActorState(IActorReference $actor, string $key, string $as = 'array'): mixed
    {
        // TODO: Implement getActorState() method.
    }

    public function createActorReminder(
        IActorReference $actor,
        string $reminderName,
        \DateInterval|\DateTimeImmutable $dueTime,
        \DateInterval|int|null $period
    ): bool {
        // TODO: Implement createActorReminder() method.
    }

    public function getActorReminder(IActorReference $actor, string $name): Reminder
    {
        // TODO: Implement getActorReminder() method.
    }

    public function deleteActorReminder(IActorReference $actor, string $name): bool
    {
        // TODO: Implement deleteActorReminder() method.
    }

    public function createActorTimer(
        IActorReference $actor,
        string $timerName,
        \DateInterval|\DateTimeImmutable $dueTime,
        \DateInterval|int|null $period,
        ?string $callback
    ): bool {
        // TODO: Implement createActorTimer() method.
    }

    public function deleteActorTimer(IActorReference $actor, string $name): bool
    {
        // TODO: Implement deleteActorTimer() method.
    }
}
