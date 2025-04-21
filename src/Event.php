<?php

namespace ZeroRPC;

/**
 * Event
 */
class Event
{
    /**
     *
     */
    const int VERSION = 3;
    /**
     * @var array|null
     */
    public ?array $envelope = null;
    /**
     * @var array
     */
    public array $header;

    /**
     * @return mixed
     */
    public function getMessageID(): mixed
    {
        return $this->header['message_id'];
    }
}

