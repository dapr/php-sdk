<?php

namespace Dapr\Actors;

use Dapr\DaprClient;
use Dapr\exceptions\DaprException;

/**
 * Trait Actor
 *
 * Implements most of IActor and provides access to timers and reminders.
 *
 * @package Dapr
 */
trait Actor
{
    /**
     * Creates a reminder. These are persisted.
     *
     * @param Reminder $reminder The reminder to create
     *
     * @return bool True if successful
     * @throws DaprException
     */
    public function create_reminder(
        Reminder $reminder
    ): bool {
        // inline function: get name
        if (isset($this->DAPR_TYPE)) {
            $type = $this->DAPR_TYPE;
        } else {
            $type = explode('\\', get_class($this));
            $type = array_pop($type);
        }
        // end function
        $id = $this->get_id();

        $result = DaprClient::post(
            DaprClient::get_api("/actors/$type/$id/reminders/{$reminder->name}", null),
            $reminder->to_array()
        );

        return true;
    }

    /**
     * Get a reminder by name
     *
     * @param string $name The name of the reminder
     *
     * @return Reminder|null The reminder
     * @throws DaprException
     */
    public function get_reminder(
        string $name
    ): ?Reminder {
        // inline function: get name
        if (isset($this->DAPR_TYPE)) {
            $type = $this->DAPR_TYPE;
        } else {
            $type = explode('\\', get_class($this));
            $type = array_pop($type);
        }
        // end function
        $id = $this->get_id();

        $result = DaprClient::get(DaprClient::get_api("/actors/$type/$id/reminders/$name"));
        return Reminder::from_api($name, $result->data);
    }

    /**
     * Delete a reminder
     *
     * @param string $name The reminder to delete
     *
     * @return bool True if successful
     * @throws DaprException
     */
    public function delete_reminder(string $name): bool
    {
        // inline function: get name
        if (isset($this->DAPR_TYPE)) {
            $type = $this->DAPR_TYPE;
        } else {
            $type = explode('\\', get_class($this));
            $type = array_pop($type);
        }
        // end function
        $id = $this->get_id();

        DaprClient::delete(DaprClient::get_api("/actors/$type/$id/reminders/$name"));
        return true;
    }

    /**
     * Create a timer. These are not persisted.
     *
     * @param Timer $timer The timer to create
     *
     * @return bool True if successful
     * @throws DaprException
     */
    public function create_timer(
        Timer $timer,
    ): bool {
        // inline function: get name
        if (isset($this->DAPR_TYPE)) {
            $type = $this->DAPR_TYPE;
        } else {
            $type = explode('\\', get_class($this));
            $type = array_pop($type);
        }
        // end function
        $id = $this->get_id();

        $result = DaprClient::post(
            DaprClient::get_api("/actors/$type/$id/timers/{$timer->name}"),
            $timer->to_array()
        );
        return true;
    }

    /**
     * Delete a timer
     *
     * @param string $name The name of the timer
     *
     * @return bool True if successful
     * @throws DaprException
     */
    public function delete_timer(string $name): bool
    {
        // inline function: get name
        if (isset($this->DAPR_TYPE)) {
            $type = $this->DAPR_TYPE;
        } else {
            $type = explode('\\', get_class($this));
            $type = array_pop($type);
        }
        // end function
        $id = $this->get_id();

        $result = DaprClient::delete(DaprClient::get_api("/actors/$type/$id/timers/$name"));
        return true;
    }
}
