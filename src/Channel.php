<?php 
namespace ZeroRPC;

Use ZMQ;

/**
 * Channel
 */
class Channel
{
    /**
     *
     */
    const int DEFAULT_TIMEOUT = 600;
    /**
     *
     */
    const int INTERVAL = 100;

    /**
     * @var array
     */
    private static array $pendingRequests = [];
    /**
     * @var array
     */
    private static array $sockets = [];
    /**
     * @var int|null
     */
    private static mixed $ID = null;

    /**
     * @param $messageID
     * @return false|mixed
     */
    public static function get($messageID): mixed
    {
        return self::$pendingRequests[$messageID] ?? false;
    }

    /**
     * @param $socket
     * @return void
     */
    public static function registerSocket($socket): void
    {
        self::$sockets[] = $socket;
    }

    /**
     * @param $socket
     * @param $event
     * @param $response
     * @return void
     */
    public static function registerRequest($socket, $event, &$response): void
    {
        $messageID = $event->getMessageID();
        self::$pendingRequests[$messageID] = array(
            'socket' => $socket,
            'event' => $event,
            'callback' => function ($content) use (&$response) {
                $response = $content;
            }
        );
    }

    /**
     * @throws \ZMQPollException
     * @throws EventException
     * @throws TimeoutException
     */
    public static function dispatch($timeout = self::DEFAULT_TIMEOUT): void
    {
        $origin_timeout = $timeout;

        $poll = new \ZMQPoll();
        foreach (self::$sockets as $socket) {
            $poll->add($socket, ZMQ::POLL_IN);    
        }

        $read = $write = array();

        while (count(self::$pendingRequests) > 0) {
            $_start = microtime(true);

            $events = $poll->poll($read, $write, self::INTERVAL);
            if ($events > 0) {
                foreach ($read as $socket) {
                    $recv = $socket->recvMulti();
                    $event = Response::deserialize($recv);
                    self::handleEvent($event); 
                }
            }

            $_end = microtime(true);
            $timeout -= ($_end - $_start) * 1000;
            if ($timeout < 0) {
                break;
            }
        }

        if (count(self::$pendingRequests) > 0) {
            $exception = count(self::$pendingRequests) . " requests timeout after {$origin_timeout} ms:\n";
            foreach (self::$pendingRequests as $id => $pending) {
                $exception .= "  # {$pending['event']->name}\n";
            }
            throw new TimeoutException(trim($exception, "\n"));
        }

        $poll->clear();
        self::clear();
    }

    /**
     * @return void
     */
    public static function clear(): void
    {
        self::$pendingRequests = array();
    }

    /**
     * @throws \ZMQSocketException
     */
    public static function startRequest(\ZMQSocket $socket, Event $event, &$response): void
    {
        self::registerRequest($socket, $event, $response);
        $socket->sendMulti($event->serialize());
    }

    /**
     * @param Event $event
     * @return mixed
     */
    public static function handleEvent(Event $event): mixed
    {
        $messageID = $event->header['response_to'];
        
        if (!isset(self::$pendingRequests[$messageID])) {
            return null;
        }

        $pendingRequest = self::$pendingRequests[$messageID];
        $socket = $pendingRequest['socket'];

        if ($event?->status === '_zpc_hb') {
            $request = new Request('_zpc_hb', array(), $messageID, $event->getMessageID());
            return $socket->sendMulti($request->serialize());
        }

        $callback = self::$pendingRequests[$messageID]['callback'];
        $callback($event->getContent());
        unset(self::$pendingRequests[$messageID]);

        return null;
    }

}
