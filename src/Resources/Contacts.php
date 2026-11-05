<?php

declare(strict_types=1);

namespace DataFlows\Sms\Resources;

final class Contacts extends ApiResource
{
    /**
     * @return array<mixed> A page of the group's contacts. The contacts are in "data".
     */
    public function list(string $groupUid, ?int $page = null): array
    {
        return $this->data('POST', '/contacts/' . $this->segment($groupUid, 'groupUid') . '/all', ['page' => $page]);
    }

    /**
     * Adds a contact. This sends the group's welcome text if you've set one up on that group.
     *
     * @param array<string, string> $fields Your group's custom fields, by tag.
     *
     * @return array<string, mixed>
     */
    public function create(string $groupUid, string $phone, ?string $firstName = null, ?string $lastName = null, array $fields = []): array
    {
        $this->required($phone, 'phone');

        return $this->data('POST', '/contacts/' . $this->segment($groupUid, 'groupUid') . '/store', [], $this->body($phone, $firstName, $lastName, $fields));
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $groupUid, string $contactUid): array
    {
        return $this->data('POST', '/contacts/' . $this->segment($groupUid, 'groupUid') . '/search/' . $this->segment($contactUid, 'contactUid'));
    }

    /**
     * Changes a contact. Leave $phone out to keep the current number.
     *
     * @param array<string, string> $fields Your group's custom fields, by tag.
     *
     * @return array<string, mixed>
     */
    public function update(string $groupUid, string $contactUid, ?string $phone = null, ?string $firstName = null, ?string $lastName = null, array $fields = []): array
    {
        return $this->data('PATCH', '/contacts/' . $this->segment($groupUid, 'groupUid') . '/update/' . $this->segment($contactUid, 'contactUid'), [], $this->body($phone, $firstName, $lastName, $fields));
    }

    public function delete(string $groupUid, string $contactUid): void
    {
        $this->client->request('DELETE', '/contacts/' . $this->segment($groupUid, 'groupUid') . '/delete/' . $this->segment($contactUid, 'contactUid'));
    }

    /**
     * @param array<string, string> $fields
     *
     * @return array<string, string>
     */
    private function body(?string $phone, ?string $firstName, ?string $lastName, array $fields): array
    {
        return array_filter(
            ['PHONE' => $phone, 'FIRST_NAME' => $firstName, 'LAST_NAME' => $lastName],
            static fn ($value) => $value !== null,
        ) + $fields;
    }
}
