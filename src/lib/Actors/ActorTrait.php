<?php

namespace Dapr\Actors;

use Dapr\Actors\Attributes\DaprType;
use Dapr\DaprClient;
use Dapr\exceptions\DaprException;
use Dapr\Runtime;
use ReflectionClass;

/**
 * Trait Actor
 *
 * Implements most of IActor and provides access to timers and reminders.
 *
 * @package Dapr
 */
trait ActorTrait
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
        global $dapr_container;
        // inline function: get name
        if (isset($this->DAPR_TYPE)) {
            $type = $this->DAPR_TYPE;
        } else {
            $class      = new ReflectionClass($this);
            $attributes = $class->getAttributes(DaprType::class);
            if ( ! empty($attributes)) {
                $type = $attributes[0]->newInstance()->type;
            } else {
                $type = $class->getShortName();
            }
        }
        // end function
        $id = $this->get_id();
        Runtime::$logger?->info('Creating reminder for {type}||{id}', ['type' => $type, 'id' => $id]);

        $client = $dapr_container->get(DaprClient::class);
        $client->post(
            $client->get_api_path("/actors/$type/$id/reminders/{$reminder->name}"),
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
        global $dapr_container;
        // inline function: get name
        if (isset($this->DAPR_TYPE)) {
            $type = $this->DAPR_TYPE;
        } else {
            $class      = new ReflectionClass($this);
            $attributes = $class->getAttributes(DaprType::class);
            if ( ! empty($attributes)) {
                $type = $attributes[0]->newInstance()->type;
            } else {
                $type = $class->getShortName();
            }
        }
        // end function
        $id = $this->get_id();
        Runtime::$logger?->info('Getting reminder for {type}||{id}', ['type' => $type, 'id' => $id]);

        $client = $dapr_container->get(DaprClient::class);
        $result = $client->get($client->get_api_path("/actors/$type/$id/reminders/$name"));

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
        global $dapr_container;
        // inline function: get name
        if (isset($this->DAPR_TYPE)) {
            $type = $this->DAPR_TYPE;
        } else {
            $class      = new ReflectionClass($this);
            $attributes = $class->getAttributes(DaprType::class);
            if ( ! empty($attributes)) {
                $type = $attributes[0]->newInstance()->type;
            } else {
                $type = $class->getShortName();
            }
        }
        // end function
        $id = $this->get_id();
        Runtime::$logger?->info('Deleting reminder for {type}||{id}', ['type' => $type, 'id' => $id]);

        $client = $dapr_container->get(DaprClient::class);
        $client->delete($client->get_api_path("/actors/$type/$id/reminders/$name"));

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
        global $dapr_container;
        // inline function: get name
        if (isset($this->DAPR_TYPE)) {
            $type = $this->DAPR_TYPE;
        } else {
            $class      = new ReflectionClass($this);
            $attributes = $class->getAttributes(DaprType::class);
            if ( ! empty($attributes)) {
                $type = $attributes[0]->newInstance()->type;
            } else {
                $type = $class->getShortName();
            }
        }
        // end function
        $id = $this->get_id();
        Runtime::$logger?->info('Creating timer for {type}||{id}', ['type' => $type, 'id' => $id]);

        $client = $dapr_container->get(DaprClient::class);
        $client->post(
            $client->get_api_path("/actors/$type/$id/timers/{$timer->name}"),
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
        global $dapr_container;
        // inline function: get name
        if (isset($this->DAPR_TYPE)) {
            $type = $this->DAPR_TYPE;
        } else {
            $class      = new ReflectionClass($this);
            $attributes = $class->getAttributes(DaprType::class);
            if ( ! empty($attributes)) {
                $type = $attributes[0]->newInstance()->type;
            } else {
                $type = $class->getShortName();
            }
        }
        // end function
        $id = $this->get_id();
        Runtime::$logger?->info('Deleting timer for {type}||{id}', ['type' => $type, 'id' => $id]);

        $client = $dapr_container->get(DaprClient::class);
        $client->delete($client->get_api_path("/actors/$type/$id/timers/$name"));

        return true;
    }
}
