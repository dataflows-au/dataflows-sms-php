<?php

declare(strict_types=1);

namespace DataFlows\Sms\Resources;

final class Account extends ApiResource
{
    /**
     * @return array<string, mixed> Your account details.
     */
    public function profile(): array
    {
        return $this->data('GET', '/me');
    }

    /**
     * @return array{remaining_balance: string, expired_on: string}
     */
    public function balance(): array
    {
        return $this->data('GET', '/balance');
    }
}
