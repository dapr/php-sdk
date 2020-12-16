<?php

namespace Dapr\Actors;

use Dapr\DaprClient;
use Dapr\exceptions\ActorNotFound;
use Dapr\exceptions\RequestFailed;

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
     * @throws ActorNotFound
     * @throws RequestFailed
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

        switch ($result->code) {
            case 200:
            case 204:
                return true;
            case 500:
            default:
                throw new RequestFailed("Request Failed");
            case 400:
                throw new ActorNotFound("Actor not found or malformed request");
        }
    }

    /**
     * Get a reminder by name
     *
     * @param string $name The name of the reminder
     *
     * @return Reminder The reminder
     * @throws RequestFailed
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
        switch ($result->code) {
            case 200:
                return Reminder::from_api($name, $result->data);
            case 500:
            default:
                throw new RequestFailed("Request failed");
        }
    }

    /**
     * Delete a reminder
     *
     * @param string $name The reminder to delete
     *
     * @return bool True if successful
     * @throws RequestFailed
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

        $result = DaprClient::delete(DaprClient::get_api("/actors/$type/$id/reminders/$name"));
        switch ($result->code) {
            case 204:
            case 200:
                return true;
            case 500:
            default:
                throw new RequestFailed("Request failed");
        }
    }

    /**
     * Create a timer. These are not persisted.
     *
     * @param Timer $timer The timer to create
     *
     * @return bool True if successful
     * @throws ActorNotFound
     * @throws RequestFailed
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
        switch ($result->code) {
            case 200:
            case 204:
                return true;
            case 400:
                throw new ActorNotFound("Actor not found or malformed request");
            case 500:
            default:
                throw new RequestFailed("Request failed");
        }
    }

    /**
     * Delete a timer
     *
     * @param string $name The name of the timer
     *
     * @return bool True if successful
     * @throws RequestFailed
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
        switch ($result->code) {
            case 204:
            case 200:
                return true;
            case 500:
            default:
                throw new RequestFailed("Request failed");
        }
    }
}
