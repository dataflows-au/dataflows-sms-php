<?php

declare(strict_types=1);

namespace DataFlows\Sms\Resources;

use DataFlows\Sms\DataFlowsException;

final class Sms extends ApiResource
{
    // Digits and the usual separators only. The API's number parser reads letters
    // as a phone keypad, so "61412345xxx" would otherwise reach a real number.
    private const VALID_NUMBER = '/^(?=.*[0-9])[0-9+()\-\s]+$/D';

    /**
     * Sends or schedules an SMS. This really sends a text and uses your credit.
     *
     * @param string|list<string> $recipient    One number in international format, or a list. A list
     *                                          is sent during the request, so a long one takes longer.
     * @param string              $senderId     One of your active sender IDs or numbers.
     * @param string|null         $scheduleTime Send later, as "Y-m-d H:i" in your account's timezone.
     *
     * @return array<mixed> One result, or one per number when you pass a list.
     */
    public function send(string|array $recipient, string $senderId, string $message, ?string $scheduleTime = null): array
    {
        $body = [
            'recipient' => $this->recipientValue($recipient),
            'sender_id' => $this->required($senderId, 'senderId'),
            'message'   => $this->required($message, 'message'),
        ];
        if ($scheduleTime !== null) {
            $body['schedule_time'] = $scheduleTime;
        }

        return $this->data('POST', '/sms/send', [], $body);
    }

    /**
     * @return array<mixed> A page of your sent messages. The messages are in "data".
     */
    public function list(?int $page = null, ?int $limit = null): array
    {
        return $this->data('GET', '/sms', ['page' => $page, 'limit' => $limit]);
    }

    /**
     * @return array<string, mixed> A "customer_status" of "Delivered" means the carrier accepted
     *                              the message. It doesn't confirm it reached the phone.
     */
    public function get(string $uid): array
    {
        return $this->data('GET', '/sms/' . $this->segment($uid, 'uid'));
    }

    /**
     * @param string|list<string> $recipient
     */
    private function recipientValue(string|array $recipient): string
    {
        $numbers = array_map(
            static fn ($number) => trim((string) $number),
            is_array($recipient) ? $recipient : explode(',', $recipient),
        );

        if ($numbers === [] || $numbers === ['']) {
            throw new DataFlowsException('recipient is required.', DataFlowsException::INVALID_INPUT);
        }

        foreach ($numbers as $number) {
            if (! preg_match(self::VALID_NUMBER, $number)) {
                throw new DataFlowsException("\"{$number}\" is not a valid phone number. Use digits only.", DataFlowsException::INVALID_INPUT);
            }
        }

        return implode(',', $numbers);
    }
}
