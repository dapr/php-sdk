<?php

namespace Dapr\PubSub;

use Dapr\Deserialization\Deserializers\IDeserialize;
use Dapr\Deserialization\IDeserializer;
use DateTime;
use Exception;
use InvalidArgumentException;
use JetBrains\PhpStorm\ArrayShape;
use LogicException;

/**
 * Class CloudEvent
 * @package Dapr\PubSub
 */
class CloudEvent implements IDeserialize
{
    /**
     * Identifies the event. Producers MUST ensure that source + id is unique for each distinct event. If a duplicate
     * event is re-sent (e.g. due to a network error) it MAY have the same id. Consumers MAY assume that Events with
     * identical source and id are duplicates.
     *
     * @var string
     */
    public string $id;

    /**
     * Identifies the context in which an event happened. Often this will include information such as the type of the
     * event source, the organization publishing the event or the process that produced the event. The exact syntax and
     * semantics behind the data encoded in the URI is defined by the event producer.
     *
     * Producers MUST ensure that source + id is unique for each distinct event.
     *
     * An application MAY assign a unique source to each distinct producer, which makes it easy to produce unique IDs
     * since no other producer will have the same source. The application MAY use UUIDs, URNs, DNS authorities or an
     * application-specific scheme to create unique source identifiers.
     *
     * A source MAY include more than one producer. In that case the producers MUST collaborate to ensure that source +
     * id is unique for each distinct event.
     *
     * @var string
     */
    public string $source;

    /**
     * This attribute contains a value describing the type of event related to the originating occurrence. Often this
     * attribute is used for routing, observability, policy enforcement, etc. The format of this is producer defined
     * and might include information such as the version of the type - see Versioning of Attributes in the Primer for
     * more information.
     *
     * @var string
     */
    public string $type;

    /**
     * Content type of data value. This attribute enables data to carry any type of content, whereby format and
     * encoding might differ from that of the chosen event format. For example, an event rendered using the JSON
     * envelope format might carry an XML payload in data, and the consumer is informed by this attribute being set to
     * "application/xml". The rules for how data content is rendered for different datacontenttype values are defined
     * in the event format specifications; for example, the JSON event format defines the relationship in section 3.1.
     *
     * For some binary mode protocol bindings, this field is directly mapped to the respective protocol's content-type
     * metadata property. Normative rules for the binary mode and the content-type metadata mapping can be found in the
     * respective protocol
     *
     * In some event formats the datacontenttype attribute MAY be omitted. For example, if a JSON format event has no
     * datacontenttype attribute, then it is implied that the data is a JSON value conforming to the "application/json"
     * media type. In other words: a JSON-format event with no datacontenttype is exactly equivalent to one with
     * datacontenttype="application/json".
     *
     * @var string|null
     */
    public ?string $data_content_type;

    /**
     * This describes the subject of the event in the context of the event producer (identified by source). In
     * publish-subscribe scenarios, a subscriber will typically subscribe to events emitted by a source, but the
     * source identifier alone might not be sufficient as a qualifier for any specific event if the source context has
     * internal sub-structure.
     *
     * @var string|null
     */
    public ?string $subject;

    /**
     * Timestamp of when the occurrence happened. If the time of the occurrence cannot be determined then this
     * attribute MAY be set to some other time (such as the current time) by the CloudEvents producer, however all
     * producers for the same source MUST be consistent in this respect. In other words, either they all use the
     * actual time of the occurrence or they all use the same algorithm to determine the value used.
     *
     * @var DateTime|null
     */
    public ?DateTime $time;

    /**
     * The version of the CloudEvents specification which the event uses. This enables the interpretation of the
     * context. Compliant event producers MUST use a value of 1.0 when referring to this version of the specification.
     *
     * @var string
     */
    public string $spec_version = '1.0';

    /**
     * @var mixed
     */
    public $data;

    /**
     * @var string|null The trace id
     */
    public ?string $trace_id;

    /**
     * @var string|null The topic
     */
    public ?string $topic;

    /**
     * @var string|null The name of the pubsub
     */
    public ?string $pubsub_name;

    public function __construct()
    {
    }

    /**
     * @param string $json
     *
     * @return CloudEvent
     * @throws Exception
     */
    public static function parse(string $json): CloudEvent
    {
        $raw = json_decode($json, true);

        return self::from_array($raw);
    }

    /**
     * @param array $raw
     *
     * @return CloudEvent
     * @throws Exception
     */
    private static function from_array(array $raw): CloudEvent
    {
        if ($raw['specversion'] !== '1.0') {
            throw new InvalidArgumentException('Cloud Event must be spec version 1.0');
        }
        $event                    = new CloudEvent();
        $event->spec_version      = $raw['specversion'];
        $event->id                = (string)$raw['id'];
        $event->source            = (string)$raw['source'];
        $event->type              = (string)$raw['type'];
        $event->data_content_type = $raw['datacontenttype'] ?? null;
        $event->subject           = $raw['subject'] ?? null;
        $event->pubsub_name       = $raw['pubsubname'] ?? null;
        $event->topic             = $raw['topic'] ?? null;
        $event->trace_id          = $raw['traceid'] ?? null;
        $time                     = $raw['time'] ?? null;
        if ( ! empty($time)) {
            $event->time = new DateTime($time);
        }
        if (isset($raw['data_base64'])) {
            $event->data = base64_decode($raw['data_base64']);
        } else {
            $event->data = $raw['data'] ?? null;
        }

        return $event;
    }

    /**
     * @param mixed $value
     * @param IDeserializer $deserializer
     *
     * @return CloudEvent
     * @throws Exception
     */
    public static function deserialize(mixed $value, IDeserializer $deserializer): CloudEvent
    {
        return self::from_array($value);
    }

    /**
     * @return false|string
     */
    public function to_json(): string|bool
    {
        return json_encode($this->to_array());
    }

    #[ArrayShape([
        'id'              => "string",
        'source'          => "string",
        'specversion'     => "string",
        'type'            => "string",
        'traceid'         => "null|string",
        'data'            => "mixed",
        'time'            => "string",
        'subject'         => "null|string",
        'datacontenttype' => "null|string",
    ])] public function to_array(): array
    {
        if ( ! $this->validate()) {
            throw new LogicException('Cloud event is not valid!');
        }
        $json = [
            'id'          => $this->id,
            'source'      => $this->source,
            'specversion' => $this->spec_version,
            'type'        => $this->type,
        ];

        if (isset($this->data_content_type)) {
            $json['datacontenttype'] = $this->data_content_type;
        }
        if (isset($this->subject)) {
            $json['subject'] = $this->subject;
        }
        if (isset($this->time)) {
            $json['time'] = $this->time->format(DATE_RFC3339).'Z';
        }
        if (isset($this->data)) {
            $json['data'] = $this->data;
        }
        if (isset($this->trace_id)) {
            $json['traceid'] = $this->trace_id;
        }

        return $json;
    }

    public function validate(): bool
    {
        // check version
        if ($this->spec_version !== '1.0') {
            return false;
        }

        // check required
        if (empty($this->id) || empty($this->source) || empty($this->type)) {
            return false;
        }

        // check uri -- see https://github.com/dapr/dapr/issues/2540
        /*if ( ! filter_var($this->source, FILTER_VALIDATE_URL)) {
            return false;
        }*/

        // check set optionals
        if (isset($this->data_content_type) && empty($this->data_content_type)) {
            return false;
        }

        if (isset($this->subject) && empty($this->subject)) {
            return false;
        }

        return true;
    }
}
