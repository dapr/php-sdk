<?php

namespace Dapr\Serialization;

use Dapr\Serialization\Serializers\DateInterval;
use Dapr\Serialization\Serializers\DateTime;
use Dapr\Serialization\Serializers\ISerialize;

class SerializationConfig
{
    /**
     * SerializationConfig constructor.
     *
     * @param ISerialize[] $serializers
     */
    public function __construct(protected array $serializers = [])
    {
        if (empty($this->serializers[\DateInterval::class])) {
            $this->add(\DateInterval::class, new DateInterval());
        }
        if (empty($this->serializers[\DateTime::class])) {
            $this->add(\DateTime::class, new DateTime());
        }
    }

    public function add(string $type, ISerialize $serializer)
    {
        $this->serializers[$type] = $serializer;
    }
}
