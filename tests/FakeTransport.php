<?php

declare(strict_types=1);

namespace DataFlows\Sms\Tests;

use DataFlows\Sms\Http\Transport;
use DataFlows\Sms\Http\TransportResponse;

/**
 * Records requests instead of making them, so no test can reach the network or send a text.
 */
final class FakeTransport implements Transport
{
    /** @var list<array{method: string, url: string, headers: list<string>, body: ?string, timeout: int}> */
    public array $calls = [];

    public function __construct(
        private readonly string $responseBody = '{"status":"success","data":{"ok":true},"value":[]}',
        private readonly int $status = 200,
    ) {
    }

    public function send(string $method, string $url, array $headers, ?string $body, int $timeoutSeconds): TransportResponse
    {
        $this->calls[] = ['method' => $method, 'url' => $url, 'headers' => $headers, 'body' => $body, 'timeout' => $timeoutSeconds];

        return new TransportResponse($this->status, $this->responseBody);
    }
}
