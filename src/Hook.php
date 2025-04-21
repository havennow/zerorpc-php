<?php

namespace ZeroRPC\Hook;

use AllowDynamicProperties;
use ZeroRPC\ClientException;

/**
 * ConfigMiddleware
 */
#[AllowDynamicProperties]
class ConfigMiddleware
{
    /**
     * @var array
     */
    public array $config;

    /**
     * @param $config
     */
    public function __construct($config)
    {
        $this->config = $config;
    }

    /**
     * @throws ClientException
     */
    public function getConfigName($name)
    {
        $configName = "ZERORPC_" . strtoupper($name);

        if (!isset($this->config[$configName])) {
            throw new ClientException("Missing config $configName.");
        }

        return $configName;
    }

    /**
     * @throws ClientException
     */
    public function getVersion($name, $version)
    {
        $configName = $this->getConfigName($name);

        if (!$version) {
            if (isset($this->config[$configName]['default'])) {
                $version = $this->config[$configName]['default'];
            } else {
                throw new ClientException("Missing version in the request.");
            }
            if (!isset($this->config[$configName][$version])) {
                $exception = "Missing config {$configName}['{$version}'].";
                throw new ClientException($exception);
            }
        }

        return $version;
    }

    /**
     * @throws ClientException
     */
    public function getAccessKey($name)
    {
        $configName = $this->getConfigName($name);
        if (isset($this->config[$configName]['access_key'])) {
            return $this->config[$configName]['access_key'];
        }

        throw new ClientException("Missing access_key in the {$configName}.");
    }

    /**
     * @return \Closure
     */
    public function resolveEndpoint(): \Closure
    {
        return function ($name, $version) {
            $configName = $this->getConfigName($name);
            $version = $this->getVersion($name, $version);
            $config = $this->config[$configName][$version];

            if (is_array($config)) {
                $endpoint = $config[array_rand($config)];
            } else {
                $endpoint = $config;
            }

            return $endpoint;
        };
    }

    /**
     * @return \Closure
     */
    public function beforeSendRequest(): \Closure
    {
        return function ($event, $client) {
            $event->header['access_key'] = $this->getAccessKey($client->_endpoint);
            $event->header['service_version'] = $this->getVersion($client->_endpoint,
                $client->_version);
            $event->header['service_name'] = $client->_endpoint;
        };
    }
}
