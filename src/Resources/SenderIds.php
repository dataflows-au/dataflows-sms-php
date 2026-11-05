<?php

declare(strict_types=1);

namespace DataFlows\Sms\Resources;

final class SenderIds extends ApiResource
{
    /**
     * @return list<string> Your active sender IDs.
     */
    public function list(): array
    {
        return $this->client->request('GET', '/sender-ids')['value'] ?? [];
    }
}
