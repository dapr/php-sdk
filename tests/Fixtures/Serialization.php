<?php

use Dapr\Deserialization\Attributes\ArrayOf;
use Dapr\Deserialization\Attributes\AsClass;

function get_example_json()
{
    return <<<JSON
{
    "Date": "2019-08-01T00:00:00-07:00",
    "TemperatureCelsius": 25,
    "Summary": "Hot",
    "DatesAvailable": [
        "2019-08-01T00:00:00-07:00",
        "2019-08-02T00:00:00-07:00"
    ],
    "TemperatureRanges": {
        "Cold": {
            "High": 20,
            "Low": -10
        },
        "Hot": {
            "High": 60,
            "Low": 20
        }
    },
    "SummaryWords": [
        "Cool",
        "Windy",
        "Humid"
    ]
}
JSON;
}

function get_example_object()
{
    $other_sdk                                  = new WeatherForecastWithPOPOs();
    $other_sdk->Date                            = new DateTime('2019-08-01T00:00:00-07:00');
    $other_sdk->DatesAvailable                  = [
        new DateTime('2019-08-01T00:00:00-07:00'),
        new DateTime('2019-08-02T00:00:00-07:00'),
    ];
    $other_sdk->Summary                         = 'Hot';
    $other_sdk->TemperatureCelsius              = 25;
    $other_sdk->TemperatureRanges               = [
        'Cold' => new HighLowTemps(),
        'Hot'  => new HighLowTemps(),
    ];
    $other_sdk->TemperatureRanges['Cold']->High = 20;
    $other_sdk->TemperatureRanges['Cold']->Low  = -10;
    $other_sdk->TemperatureRanges['Hot']->High  = 60;
    $other_sdk->TemperatureRanges['Hot']->Low   = 20;
    $other_sdk->SummaryWords                    = [
        "Cool",
        "Windy",
        "Humid",
    ];

    return $other_sdk;
}

class WeatherForecastWithPOPOs
{
    #[AsClass(DateTime::class)]
    public DateTime $Date;
    public int $TemperatureCelsius;
    public string $Summary;
    #[ArrayOf(DateTime::class)]
    public array $DatesAvailable;
    #[ArrayOf(HighLowTemps::class)]
    public array $TemperatureRanges;
    public array $SummaryWords;
}

class HighLowTemps
{
    public int $High;
    public int $Low;
}
