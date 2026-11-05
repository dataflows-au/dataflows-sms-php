<?php

declare(strict_types=1);

namespace DataFlows\Sms\Http;

final class TransportResponse
{
    public function __construct(
        public readonly int $status,
        public readonly string $body,
    ) {
    }
}
