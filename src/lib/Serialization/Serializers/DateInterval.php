<?php

namespace Dapr\Serialization\Serializers;

class DateInterval implements ISerialize
{
    private const REM_DT = ['S0F', 'M0S', 'H0M', 'DT0H', 'M0D', 'P0Y', 'Y0M', 'P0M'];
    private const CLEAN_DT = ['S', 'M', 'H', 'DT', 'M', 'P', 'Y', 'P'];
    private const DEFAULT_DT = 'PT0S';

    public static function serialize(mixed $value): mixed
    {
        return rtrim(
            str_replace(self::REM_DT, self::CLEAN_DT, $value->format('P%yY%mM%dDT%hH%iM%sS%fF')),
            'PT'
        ) ?: self::DEFAULT_DT;
    }
}
