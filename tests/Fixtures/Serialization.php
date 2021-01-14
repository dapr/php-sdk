<?php

function get_example_json() {
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

class WeatherForecastWithPOPOs
{
    public DateTime $Date;
    public int $TemperatureCelsius;
    public string $Summary;
    #[CastValues(DateTime::class)]
    public array $DatesAvailable;
    #[CastValues(HighLowTemps::class)]
    public array $TemperatureRanges;
    public array $SummaryWords;
}

class HighLowTemps
{
    public int $High;
    public int $Low;
}
