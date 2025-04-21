<?php

namespace ZeroRPC;

/**
 * Response
 */
class Response extends Event
{
    /**
     * @var string|null
     */
    public ?string $status  = null;
    /**
     * @var string|null|array
     */
    public null|string|array $content = null;
    /**
     *
     */
    const string STATUS_OK = 'OK';
    /**
     *
     */
    const string STATUS_ERROR = 'ERR';

    /**
     * @param array $header
     * @param string|null $status
     * @param string|null $content
     * @param array|string|null $envelope
     */
    public function __construct(array $header, ?string $status, ?string $content, null|array|string $envelope)
    {
        $this->envelope = $envelope;
        $this->header = $header;
        $this->status = $status;
        $this->content = $content;
    }

    /**
     * @throws EventException
     */
    public static function deserialize(?array $recv = null)
    {
        $envelope = array_slice($recv, 0, count($recv) - 2);
        $payload = $recv[count($recv) - 1];

        $event = msgpack_unpack($payload);
        if (!is_array($event) || count($event) !== 3) {
            throw new EventException('Expected array of size 3');
        }

        if (!is_array($event[0]) || !array_key_exists('message_id', $event[0])) {
            throw new EventException('Bad header');
        }

        if (!is_string($event[1])) {
            throw new EventException('Bad name');
        }

        return new self(header: $event[0], status: $event[1], content: $event[2], envelope: $envelope);
    }

    /**
     * @throws RemoteException
     */
    public function getContent(): ?string
    {
        if ($this->status === self::STATUS_OK) {
            return $this->content[0] ?? null;
        }

        if ($this->status === self::STATUS_ERROR) {
            throw new RemoteException($this->content);
        }

        return null;
    }
}
