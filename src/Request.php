<?php

namespace ZeroRPC;

/**
 * Request
 */
class Request extends Event
{
    /**
     * @var string|null
     */
    public ?string $name;
    /**
     * @var array|mixed|null
     */
    public ?array $args;
    /**
     * @var array
     */
    public array $header;

    /**
     * @param $name
     * @param $args
     * @param $messageID
     * @param $responseTo
     * @param $envelope
     */
    public function __construct(
        $name,
        $args = null,
        $messageID = null,
        $responseTo = null,
        $envelope = null
    ) {
        $this->name = $name;
        $this->args = $args;
        $this->header = $this->genHeader($messageID, $responseTo);
        $this->envelope = $envelope;
    }

    /**
     * @param $messageID
     * @param $responseTo
     * @return array
     */
    public function genHeader($messageID, $responseTo): array
    {
        $header['v'] = self::VERSION;
        $header['message_id'] = $messageID ?: uniqid('', true);

        if ($responseTo) {
            $header['response_to'] = $responseTo;
        }

        return $header;
    }

    /**
     * @return array
     */
    public function serialize(): array
    {
        $payload = [$this->header, $this->name, $this->args];
        $message = $this->envelope ?: [];
        $message[] = msgpack_pack($payload);

        return $message;
    }

}