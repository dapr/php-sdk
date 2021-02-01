<?php

namespace Dapr;

use DateInterval;
use DateTime;
use Exception;
use LogicException;

abstract class Formats
{
    public const FROM_INTERVAL = '%hh%im%ss%fus';
    private const DAY_IN_HOURS = 24;
    private const NANOSECOND_TO_SECOND = 1.0000E-9;
    private const MICROSECOND_TO_SECOND = 0.000001;
    private const MILLISECOND_TO_SECOND = 0.001;

    /**
     * Dapr cannot accept days, so they need to be converted to hours.
     *
     * @param DateInterval|null $interval The interval to normalize.
     *
     * @return string The normalized interval.
     */
    public static function normalize_interval(?DateInterval $interval): string
    {
        if ($interval === null) {
            return "";
        }

        $diff = self::coalesce($interval);

        if ($diff->m || $diff->y) {
            throw new LogicException('Interval cannot use months or years with Dapr');
        }

        return $diff->format(self::FROM_INTERVAL);
    }

    public static function coalesce(DateInterval $interval): bool|DateInterval
    {
        $from    = new DateTime();
        $to      = clone $from;
        $to      = $to->add($interval);
        $diff    = $to->diff($from, true);
        $diff->h += $diff->d * self::DAY_IN_HOURS;
        $diff->d = 0;

        return $diff;
    }

    /**
     * @param string $dapr_interval
     *
     * @return DateInterval|null
     * @throws Exception
     */
    public static function from_dapr_interval(string $dapr_interval): ?DateInterval
    {
        if (empty($dapr_interval)) {
            return null;
        }

        $parts    = array_combine(
            preg_split('/[0-9]/', $dapr_interval, 0, PREG_SPLIT_NO_EMPTY),
            preg_split('/[a-z]/', $dapr_interval, 0, PREG_SPLIT_NO_EMPTY)
        );
        $interval = ['PT'];
        $seconds  = 0.0;
        foreach ($parts as $time => $value) {
            $seconds    += match ($time) {
                'ns' => ((float)$value) * self::NANOSECOND_TO_SECOND,
                'us', 'µs' => ((float)$value) * self::MICROSECOND_TO_SECOND,
                'ms' => ((float)$value) * self::MILLISECOND_TO_SECOND,
                's' => (float)$value,
                default => 0,
            };
            $interval[] = match ($time) {
                'm' => $value.'M',
                'h' => $value.'H',
                default => null
            };
        }
        if ($seconds > 0) {
            $interval[] = $seconds.'S';
        }

        return new DateInterval(implode('', $interval));
    }
}
