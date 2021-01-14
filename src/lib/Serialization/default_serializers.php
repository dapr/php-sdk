<?php

namespace Dapr\Serialization;

Serializer::register(['Dapr\Serialization\Serializers\DateTime', 'serialize'], [\DateTime::class]);
Serializer::register(['Dapr\Serialization\Serializers\DateInterval', 'serialize'], [\DateInterval::class]);
