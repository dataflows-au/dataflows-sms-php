<?php

declare(strict_types=1);

namespace DataFlows\Sms\Resources;

use DataFlows\Sms\Client;
use DataFlows\Sms\DataFlowsException;

abstract class ApiResource
{
    public function __construct(protected readonly Client $client)
    {
    }

    /**
     * @param array<string, int|string|null> $query
     * @param array<string, mixed>|null      $body
     *
     * @return array<mixed>
     */
    protected function data(string $method, string $path, array $query = [], ?array $body = null): array
    {
        return $this->client->request($method, $path, $query, $body)['data'] ?? [];
    }

    protected function required(string $value, string $name): string
    {
        if ($value === '') {
            throw new DataFlowsException("{$name} is required.", DataFlowsException::INVALID_INPUT);
        }

        return $value;
    }

    protected function segment(string $value, string $name): string
    {
        return rawurlencode($this->required($value, $name));
    }
}
