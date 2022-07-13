<?php

declare(strict_types=1);

namespace iboxs\elasticsearch;

use Elasticsearch\Client;
use Elasticsearch\ClientBuilder;
use Elasticsearch\Common\EmptyLogger;
use Psr\Log\LoggerInterface;

class Factory
{
    /**
     * @param array $config
     * @param LoggerInterface|null $logger
     *
     * @return Builder
     */
    public static function builder(array $config, ?LoggerInterface $logger = null): Builder
    {
        return new Builder(
            new Client(new Grammar(), static::clientBuilder($config, $logger))
        );
    }

    /**
     * @param array $config
     * @param LoggerInterface|null $logger
     *
     * @return Client
     */
    protected static function clientBuilder(array $config, ?LoggerInterface $logger = null): Client
    {
        $clientBuilder = ClientBuilder::create();

        $clientBuilder
            ->setConnectionPool($config['connection_pool'])
            ->setSelector($config['selector'])
            ->setHosts($config['hosts']);

        if (!empty($config['open_log'])) {
            $clientBuilder->setLogger(
                $logger ?? new EmptyLogger()
            );
        }

        return $clientBuilder->build();
    }
}
