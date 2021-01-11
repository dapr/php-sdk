<?php

namespace Dapr\Mocks;

use Dapr\Actors\Reminder;
use Dapr\Actors\Timer;

trait TestActor
{
    /**
     * @var Reminder[]
     */
    private array $reminders;

    /**
     * @var Timer[]
     */
    private array $timers;

    public function create_reminder(
        Reminder $reminder
    ): bool {
        $this->reminders[$reminder->name] = $reminder;

        return true;
    }

    public function get_reminder(
        string $name
    ): ?Reminder {
        return $this->reminders[$name] ?? null;
    }

    public function delete_reminder(string $name): bool
    {
        unset($this->reminders[$name]);

        return true;
    }

    public function create_timer(
        Timer $timer,
    ): bool {
        $this->timers[$timer->name] = $timer;

        return true;
    }

    public function delete_timer(string $name): bool
    {
        unset($this->timers[$name]);

        return true;
    }

    public function helper_get_timer(string $name): ?Timer
    {
        return $this->timers[$name] ?? null;
    }
}
