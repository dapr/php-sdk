<?php

namespace Dapr\exceptions;

use Exception;

class DaprException extends Exception
{
    public string $dapr_error_code;

    public static function deserialize_from_array(array $array): DaprException
    {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);

        // todo: whitelist some exception types
        switch ($array['errorCode']) {
            default:
                $original_exception                  = new DaprException(
                    $array['message'],
                    E_ERROR,
                    isset($array['inner']) ? self::deserialize_from_array($array['inner']) : null
                );
                $original_exception->dapr_error_code = $array['errorCode'];
                break;
        }

        $original_exception->file = $array['file'] ?? $backtrace[1]['file'];
        $original_exception->line = $array['line'] ?? $backtrace[1]['line'];

        return $original_exception;
    }

    /**
     * @param Exception|null $exception
     *
     * @return array|null
     */
    public static function serialize_to_array(?Exception $exception): ?array
    {
        if ($exception === null || ! ($exception instanceof Exception)) {
            return null;
        }

        return [
            'message'   => $exception->getMessage(),
            'errorCode' => get_class($exception),
            'file'      => $exception->getFile(),
            'line'      => $exception->getLine(),
            'inner'     => self::serialize_to_array($exception->getPrevious()),
        ];
    }

    public function get_dapr_error_code(): string
    {
        return $this->dapr_error_code;
    }
}
