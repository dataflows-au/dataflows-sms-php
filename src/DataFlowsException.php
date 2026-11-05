<?php

declare(strict_types=1);

namespace DataFlows\Sms;

use RuntimeException;
use Throwable;

final class DataFlowsException extends RuntimeException
{
    public const API_ERROR = 'api_error';
    public const INVALID_INPUT = 'invalid_input';
    public const INVALID_RESPONSE = 'invalid_response';
    public const NETWORK_ERROR = 'network_error';
    public const RATE_LIMITED = 'rate_limited';
    public const TIMEOUT = 'timeout';

    /**
     * @param array<mixed>|null $response The parsed error response, when there was one.
     */
    public function __construct(
        string $message,
        public readonly string $errorCode = self::API_ERROR,
        public readonly ?int $httpStatus = null,
        public readonly ?array $response = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
