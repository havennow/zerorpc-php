<?php

namespace ZeroRPC;

use AllowDynamicProperties;
use ZMQ;

/**
 * Client
 */
#[AllowDynamicProperties]
class Client
{
    /**
     * @var mixed|Context|null
     */
    private mixed      $context;
    /**
     * @var \ZMQSocket
     */
    private \ZMQSocket $socket;
    /**
     * @var int
     */
    private int $timeout = 600;

    /**
     * @throws \ZMQSocketException
     */
    public function __construct($endpoint = null, $version = null, $context = null)
    {
        $this->_endpoint = $endpoint;
        $this->_version = $version;
        $this->context = $context ? : Context::get_instance();
        $this->socket = new \ZMQSocket($this->context, ZMQ::SOCKET_XREQ);
        $this->socket->setSockOpt(ZMQ::SOCKOPT_LINGER, 10);
        $this->connect($endpoint, $version);
        Channel::registerSocket($this->socket);
    }

    /**
     * @param $name
     * @param $args
     * @return mixed|null
     * @throws EventException
     * @throws RemoteException
     * @throws TimeoutException
     * @throws \ZMQPollException
     * @throws \ZMQSocketException
     */
    public function __call($name, $args)
    {
        return $this->sync($name, $args);
    }

    /**
     * @throws \ZMQSocketException
     */
    public function connect(): void
    {
        $endpoint = $this->context->hookResolveEndpoint($this->_endpoint, $this->_version);
        $this->socket->connect($endpoint);
    }

    /**
     * @param $timeout
     * @return void
     */
    public function setTimeout($timeout): void
    {
        $this->timeout = $timeout;
    }

    /**
     * @throws \ZMQSocketException
     * @throws EventException
     * @throws \ZMQPollException
     * @throws RemoteException
     * @throws TimeoutException
     */
    public function sync($name, array $args, $timeout = 0)
    {
        if (!$timeout) {
            $timeout = $this->timeout;
        }

        $event = new Request($name, $args);
        $this->context->hookBeforeSendRequest($event, $this);
        $this->socket->sendMulti($event->serialize());

        $read = $write = array();
        $poll = new \ZMQPoll();
        $poll->add($this->socket, ZMQ::POLL_IN);
        $events = $poll->poll($read, $write, $timeout);

        if ($events) {
            $recv = $this->socket->recvMulti();
            $event = Response::deserialize($recv);
            $this->context->hookAfterResponse($event, $this);
            return $event->getContent();
        }

        throw new TimeoutException('Timout after ' . $this->timeout . ' ms');
    }

    /**
     * @param $name
     * @param array $args
     * @param $response
     * @return void
     * @throws \ZMQSocketException
     */
    public function async($name, array $args, &$response): void
    {
        $event = new Request($name, $args);
        $this->context->hookBeforeSendRequest($event, $this);
        Channel::startRequest($this->socket, $event, $response);
        $this->context->hookAfterResponse($event, $this);
    }

}


