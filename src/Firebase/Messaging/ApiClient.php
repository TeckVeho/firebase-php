<?php

declare(strict_types=1);

namespace Kreait\Firebase\Messaging;

use Beste\Json;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Pool;
use GuzzleHttp\Promise\PromiseInterface;
use Iterator;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * @internal
 */
class ApiClient
{
    public function __construct(
        private readonly ClientInterface $client,
        private readonly string $projectId,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
    ) {
    }

    public function createSendRequestForMessage(Message $message, bool $validateOnly): RequestInterface
    {
        $protocolVersion = '1.1';
        if (defined('CURL_VERSION_HTTP2')) {
            try {
                $curlInfo = curl_version();
                $supportsHttp2 = ($curlInfo['features'] & CURL_VERSION_HTTP2) !== 0;
                $protocolVersion = $supportsHttp2 ? '2.0' : '1.1';
            } catch (\Exception $exception) {
                $protocolVersion = '1.1';
            }
        }
        $request = $this->requestFactory
            ->createRequest(
                'POST',
                'https://fcm.googleapis.com/v1/projects/'.$this->projectId.'/messages:send',
            )
        ;

        $payload = ['message' => $message];

        if ($validateOnly === true) {
            $payload['validate_only'] = true;
        }

        $body = $this->streamFactory->createStream(Json::encode($payload));

        return $request
            ->withProtocolVersion($protocolVersion)
            ->withBody($body)
            ->withHeader('Content-Type', 'application/json; charset=UTF-8')
            ->withHeader('Content-Length', (string) $body->getSize())
        ;
    }

    /**
     * @param list<RequestInterface>|Iterator<RequestInterface> $requests
     * @param array<string, mixed> $config
     */
    public function pool(array|Iterator $requests, array $config): PromiseInterface
    {
        $pool = new Pool($this->client, $requests, $config);

        return $pool->promise();
    }
}
