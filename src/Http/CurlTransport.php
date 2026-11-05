<?php

declare(strict_types=1);

namespace DataFlows\Sms\Http;

use DataFlows\Sms\DataFlowsException;

final class CurlTransport implements Transport
{
    public function send(string $method, string $url, array $headers, ?string $body, int $timeoutSeconds): TransportResponse
    {
        $handle = curl_init($url);

        curl_setopt_array($handle, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeoutSeconds,
            CURLOPT_CONNECTTIMEOUT => min(10, $timeoutSeconds),
            CURLOPT_FOLLOWLOCATION => false,
        ]);

        if ($body !== null) {
            curl_setopt($handle, CURLOPT_POSTFIELDS, $body);
        }

        $result = curl_exec($handle);

        if ($result === false) {
            $timedOut = curl_errno($handle) === CURLE_OPERATION_TIMEOUTED;
            $error    = curl_error($handle);

            throw $timedOut
                ? new DataFlowsException("The request timed out after {$timeoutSeconds} seconds. If this was a send, the message may still have gone out, so check before trying again.", DataFlowsException::TIMEOUT)
                : new DataFlowsException("Could not reach DataFlows: {$error}", DataFlowsException::NETWORK_ERROR);
        }

        return new TransportResponse((int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE), (string) $result);
    }
}
