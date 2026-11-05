<?php

declare(strict_types=1);

namespace DataFlows\Sms\Http;

interface Transport
{
    /**
     * @param list<string> $headers
     */
    public function send(string $method, string $url, array $headers, ?string $body, int $timeoutSeconds): TransportResponse;
}
