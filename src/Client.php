<?php

declare(strict_types=1);

namespace DataFlows\Sms;

use DataFlows\Sms\Http\CurlTransport;
use DataFlows\Sms\Http\Transport;
use DataFlows\Sms\Resources\Account;
use DataFlows\Sms\Resources\Contacts;
use DataFlows\Sms\Resources\Groups;
use DataFlows\Sms\Resources\SenderIds;
use DataFlows\Sms\Resources\Sms;
use SensitiveParameter;

final class Client
{
    public const VERSION = '0.1.0';
    public const DEFAULT_BASE_URL = 'https://sms.dataflows.com.au/api/http';
    public const DEFAULT_TIMEOUT_SECONDS = 60;

    private readonly string $apiToken;
    private readonly string $baseUrl;
    private readonly Transport $transport;

    /**
     * @param string|null $apiToken Your API token. Defaults to the DATAFLOWS_API_TOKEN environment variable.
     * @param int         $timeout  Seconds to wait for each request.
     */
    public function __construct(
        #[SensitiveParameter] ?string $apiToken = null,
        string $baseUrl = self::DEFAULT_BASE_URL,
        private readonly int $timeout = self::DEFAULT_TIMEOUT_SECONDS,
        ?Transport $transport = null,
    ) {
        $apiToken ??= getenv('DATAFLOWS_API_TOKEN') ?: null;

        if ($apiToken === null || $apiToken === '') {
            throw new DataFlowsException('An API token is required. Pass it to the client or set DATAFLOWS_API_TOKEN.', DataFlowsException::INVALID_INPUT);
        }
        if (! preg_match('#^https://#', $baseUrl) && ! preg_match('#^http://(localhost|127\.0\.0\.1)(:|/|$)#', $baseUrl)) {
            throw new DataFlowsException('baseUrl must use https, so your API token is never sent unencrypted.', DataFlowsException::INVALID_INPUT);
        }

        $this->apiToken  = $apiToken;
        $this->baseUrl   = rtrim($baseUrl, '/');
        $this->transport = $transport ?? new CurlTransport();
    }

    public function account(): Account
    {
        return new Account($this);
    }

    public function senderIds(): SenderIds
    {
        return new SenderIds($this);
    }

    public function sms(): Sms
    {
        return new Sms($this);
    }

    public function groups(): Groups
    {
        return new Groups($this);
    }

    public function contacts(): Contacts
    {
        return new Contacts($this);
    }

    /**
     * Makes one API call and returns the decoded response. Nothing is retried,
     * so a message can't go out twice because of this client.
     *
     * @internal Use the resource classes.
     *
     * @param array<string, int|string|null> $query
     * @param array<string, mixed>|null      $body
     *
     * @return array<mixed>
     */
    public function request(string $method, string $path, array $query = [], ?array $body = null): array
    {
        $queryString = http_build_query(array_filter($query, static fn ($value) => $value !== null));
        $url         = $this->baseUrl . $path . ($queryString === '' ? '' : '?' . $queryString);

        $headers = [
            'Accept: application/json',
            'Authorization: Bearer ' . $this->apiToken,
            'User-Agent: dataflows-sms-php/' . self::VERSION,
        ];
        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
        }

        $response = $this->transport->send(
            $method,
            $url,
            $headers,
            $body === null ? null : json_encode($body, JSON_THROW_ON_ERROR),
            $this->timeout,
        );

        $payload = json_decode($response->body, true);

        if (! is_array($payload)) {
            throw new DataFlowsException("DataFlows sent a response that isn't JSON (HTTP {$response->status}).", DataFlowsException::INVALID_RESPONSE, $response->status);
        }

        // Many errors answer HTTP 200 with status "error", so the HTTP code alone isn't enough.
        if ($response->status >= 400 || ($payload['status'] ?? null) === 'error') {
            throw new DataFlowsException(
                is_string($payload['message'] ?? null) && $payload['message'] !== '' ? $payload['message'] : "DataFlows answered HTTP {$response->status}.",
                $response->status === 429 ? DataFlowsException::RATE_LIMITED : DataFlowsException::API_ERROR,
                $response->status,
                $payload,
            );
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return ['baseUrl' => $this->baseUrl, 'timeout' => $this->timeout, 'apiToken' => '[hidden]'];
    }
}
