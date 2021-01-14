<?php

namespace Dapr\Deserialization;

Deserializer::register(['\Dapr\Deserialization\Deserializers\DateInterval', 'deserialize'], \DateInterval::class);
Deserializer::register(['\Dapr\Deserialization\Deserializers\DateTime', 'deserialize'], \DateTime::class);
