<?php

namespace ZeroRPC;

/**
 * Context
 */
class Context extends \ZMQContext
{
    /**
     * @var array|array[]
     */
    private array $hooks = [
        'resolve_endpoint' => [],
        'before_send_request' => [],
        'after_response' => [],
    ];

    /**
     * @var Context|null
     */
    private static ?Context $instance = null;

    /**
     * @return Context|null
     */
    public static function get_instance(): ?Context
    {
        if (!self::$instance) {
            self::$instance = new Context();
        }
        return self::$instance;
    }

    /**
     * @param $name
     * @param $func
     * @return void
     */
    public function registerHook($name, $func): void
    {
        if (isset($this->hooks[$name]) && is_callable($func)) {
            $this->hooks[$name][] = $func;
        }
    }

    /**
     * @param $endpoint
     * @param $version
     * @return false
     */
    public function hookResolveEndpoint($endpoint, $version): false
    {
        $endpoint_ = false;
        foreach ($this->hooks['resolve_endpoint'] as $func) {
            $endpoint_ = $func($endpoint, $version);
        }
        return $endpoint_ ? : $endpoint;
    }

    /**
     * @param $event
     * @param $client
     * @return void
     */
    public function hookBeforeSendRequest($event, $client): void
    {
        foreach ($this->hooks['before_send_request'] as $func) {
            $func($event, $client);
        }
    }

    /**
     * @param $event
     * @param $client
     * @return void
     */
    public function hookAfterResponse($event, $client): void
    {
        foreach ($this->hooks['after_response'] as $func) {
            $func($event, $client);
        }
    }
}