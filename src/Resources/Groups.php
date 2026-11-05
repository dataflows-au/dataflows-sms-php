<?php

declare(strict_types=1);

namespace DataFlows\Sms\Resources;

final class Groups extends ApiResource
{
    /**
     * @return array<mixed> A page of your groups. The groups are in "data".
     */
    public function list(?int $page = null): array
    {
        return $this->data('GET', '/contacts', ['page' => $page]);
    }

    /**
     * @return array<string, mixed>
     */
    public function create(string $name): array
    {
        return $this->data('POST', '/contacts', [], ['name' => $this->required($name, 'name')]);
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $groupUid): array
    {
        return $this->data('POST', '/contacts/' . $this->segment($groupUid, 'groupUid') . '/show');
    }

    /**
     * @return array<string, mixed>
     */
    public function update(string $groupUid, string $name): array
    {
        return $this->data('PATCH', '/contacts/' . $this->segment($groupUid, 'groupUid'), [], ['name' => $this->required($name, 'name')]);
    }

    public function delete(string $groupUid): void
    {
        $this->client->request('DELETE', '/contacts/' . $this->segment($groupUid, 'groupUid'));
    }
}
