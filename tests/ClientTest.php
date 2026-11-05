<?php

declare(strict_types=1);

namespace DataFlows\Sms\Tests;

use DataFlows\Sms\Client;
use DataFlows\Sms\DataFlowsException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ClientTest extends TestCase
{
    private const TOKEN = 'test-token-not-real';
    private const NUMBER = '61404235133';
    private const BASE = 'https://sms.dataflows.com.au/api/http';

    /**
     * @return array{Client, FakeTransport}
     */
    private function fake(string $body = '{"status":"success","data":{"ok":true},"value":[]}', int $status = 200): array
    {
        $transport = new FakeTransport($body, $status);

        return [new Client(self::TOKEN, transport: $transport), $transport];
    }

    /**
     * One call for each operation in the published API spec.
     *
     * @return array<string, callable(Client): mixed>
     */
    private static function operations(): array
    {
        return [
            'getProfile'    => static fn (Client $c) => $c->account()->profile(),
            'getBalance'    => static fn (Client $c) => $c->account()->balance(),
            'listSenderIds' => static fn (Client $c) => $c->senderIds()->list(),
            'sendSms'       => static fn (Client $c) => $c->sms()->send(self::NUMBER, 'Sender', 'Hi'),
            'listMessages'  => static fn (Client $c) => $c->sms()->list(),
            'getMessage'    => static fn (Client $c) => $c->sms()->get('M1'),
            'listGroups'    => static fn (Client $c) => $c->groups()->list(),
            'createGroup'   => static fn (Client $c) => $c->groups()->create('Group'),
            'getGroup'      => static fn (Client $c) => $c->groups()->get('G1'),
            'updateGroup'   => static fn (Client $c) => $c->groups()->update('G1', 'Group'),
            'deleteGroup'   => static fn (Client $c) => $c->groups()->delete('G1'),
            'listContacts'  => static fn (Client $c) => $c->contacts()->list('G1'),
            'createContact' => static fn (Client $c) => $c->contacts()->create('G1', self::NUMBER),
            'getContact'    => static fn (Client $c) => $c->contacts()->get('G1', 'C1'),
            'updateContact' => static fn (Client $c) => $c->contacts()->update('G1', 'C1', firstName: 'Jo'),
            'deleteContact' => static fn (Client $c) => $c->contacts()->delete('G1', 'C1'),
        ];
    }

    /**
     * @return array<string, array{method: string, path: string}>
     */
    private static function specOperations(): array
    {
        $spec = json_decode((string) file_get_contents(__DIR__ . '/openapi.json'), true);
        $out  = [];

        foreach ($spec['paths'] as $route => $methods) {
            foreach ($methods as $method => $operation) {
                if (is_array($operation) && isset($operation['operationId'])) {
                    $out[$operation['operationId']] = [
                        'method' => strtoupper($method),
                        'path'   => str_replace(['{group_uid}', '{contact_uid}', '{uid}'], ['G1', 'C1', 'M1'], $route),
                    ];
                }
            }
        }

        return $out;
    }

    public function testTheClientCoversExactlyTheOperationsInTheApiSpec(): void
    {
        $ours = array_keys(self::operations());
        $spec = array_keys(self::specOperations());
        sort($ours);
        sort($spec);

        $this->assertSame($spec, $ours);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function operationIds(): array
    {
        return array_map(static fn ($id) => [$id], array_combine(array_keys(self::operations()), array_keys(self::operations())));
    }

    #[DataProvider('operationIds')]
    public function testEachOperationUsesTheMethodAndPathInTheSpecWithBearerLogin(string $operationId): void
    {
        [$client, $transport] = $this->fake();
        self::operations()[$operationId]($client);

        $this->assertCount(1, $transport->calls);
        $call = $transport->calls[0];
        $want = self::specOperations()[$operationId];

        $this->assertSame($want['method'], $call['method']);
        $this->assertSame(self::BASE . $want['path'], $call['url']);
        $this->assertContains('Authorization: Bearer ' . self::TOKEN, $call['headers']);
        $this->assertContains('Accept: application/json', $call['headers']);
        $this->assertStringNotContainsString(self::TOKEN, $call['url'], 'the token must never be in the URL');
        $this->assertStringNotContainsString(self::TOKEN, (string) $call['body'], 'the token must never be in the body');
    }

    public function testSendMapsItsFieldsAndReturnsData(): void
    {
        [$client, $transport] = $this->fake('{"status":"success","data":{"campaign_id":"abc"}}');
        $result = $client->sms()->send(self::NUMBER, 'Sender', 'Hi', '2030-01-31 09:00');

        $this->assertSame(
            ['recipient' => self::NUMBER, 'sender_id' => 'Sender', 'message' => 'Hi', 'schedule_time' => '2030-01-31 09:00'],
            json_decode((string) $transport->calls[0]['body'], true),
        );
        $this->assertContains('Content-Type: application/json', $transport->calls[0]['headers']);
        $this->assertSame(['campaign_id' => 'abc'], $result);
    }

    public function testSendLeavesScheduleTimeOutWhenSendingNow(): void
    {
        [$client, $transport] = $this->fake();
        $client->sms()->send(self::NUMBER, 'Sender', 'Hi');

        $this->assertArrayNotHasKey('schedule_time', json_decode((string) $transport->calls[0]['body'], true));
    }

    public function testSendJoinsAListOfNumbers(): void
    {
        [$client, $transport] = $this->fake('{"status":"success","data":[]}');
        $client->sms()->send([self::NUMBER, ' +' . self::NUMBER . ' '], 'Sender', 'Hi');

        $this->assertSame(self::NUMBER . ',+' . self::NUMBER, json_decode((string) $transport->calls[0]['body'], true)['recipient']);
    }

    /**
     * @return array<string, array{string|list<string>}>
     */
    public static function badRecipients(): array
    {
        return [
            'letters'         => ['abcabcabc'],
            'placeholder'     => ['YOUR_MOBILE_NUMBER'],
            'mixed'           => ['614abc'],
            'plus only'       => ['+'],
            'empty'           => [''],
            'space'           => [' '],
            'one bad of two'  => [self::NUMBER . ',abc'],
            'trailing comma'  => [self::NUMBER . ','],
            'empty list'      => [[]],
            'bad in a list'   => [[self::NUMBER, 'abc']],
            'newline'         => [self::NUMBER . "\nabc"],
        ];
    }

    /**
     * @param string|list<string> $recipient
     */
    #[DataProvider('badRecipients')]
    public function testSendRefusesABadRecipientWithoutCallingTheApi(string|array $recipient): void
    {
        [$client, $transport] = $this->fake();

        try {
            $client->sms()->send($recipient, 'Sender', 'Hi');
            $this->fail('expected a refusal');
        } catch (DataFlowsException $e) {
            $this->assertSame(DataFlowsException::INVALID_INPUT, $e->errorCode);
        }
        $this->assertCount(0, $transport->calls);
    }

    public function testRequiredFieldsAreCheckedBeforeCallingTheApi(): void
    {
        [$client, $transport] = $this->fake();
        $checks = [
            static fn () => $client->sms()->send(self::NUMBER, '', 'Hi'),
            static fn () => $client->sms()->send(self::NUMBER, 'Sender', ''),
            static fn () => $client->sms()->get(''),
            static fn () => $client->groups()->create(''),
            static fn () => $client->groups()->update('G1', ''),
            static fn () => $client->contacts()->create('G1', ''),
            static fn () => $client->contacts()->delete('G1', ''),
        ];

        foreach ($checks as $check) {
            try {
                $check();
                $this->fail('expected a refusal');
            } catch (DataFlowsException $e) {
                $this->assertSame(DataFlowsException::INVALID_INPUT, $e->errorCode);
            }
        }
        $this->assertCount(0, $transport->calls);
    }

    public function testSenderIdsReturnsTheList(): void
    {
        [$client] = $this->fake('{"value":["One","Two"]}');

        $this->assertSame(['One', 'Two'], $client->senderIds()->list());
    }

    public function testListsSendPageAndLimitOnlyWhenGiven(): void
    {
        [$client, $transport] = $this->fake();
        $client->sms()->list(2, 5);
        $client->sms()->list();
        $client->contacts()->list('G1', 3);

        $this->assertSame(self::BASE . '/sms?page=2&limit=5', $transport->calls[0]['url']);
        $this->assertSame(self::BASE . '/sms', $transport->calls[1]['url']);
        $this->assertSame(self::BASE . '/contacts/G1/all?page=3', $transport->calls[2]['url']);
    }

    public function testContactsMapToTheApiFieldNames(): void
    {
        [$client, $transport] = $this->fake();
        $client->contacts()->create('G1', self::NUMBER, 'Jo', 'Lee', ['CITY' => 'Perth']);

        $this->assertSame(
            ['PHONE' => self::NUMBER, 'FIRST_NAME' => 'Jo', 'LAST_NAME' => 'Lee', 'CITY' => 'Perth'],
            json_decode((string) $transport->calls[0]['body'], true),
        );
    }

    public function testUpdatingAContactWithoutAPhoneLeavesTheNumberOut(): void
    {
        [$client, $transport] = $this->fake();
        $client->contacts()->update('G1', 'C1', firstName: 'Jo');

        $this->assertSame(['FIRST_NAME' => 'Jo'], json_decode((string) $transport->calls[0]['body'], true));
    }

    public function testIdsAreEncodedSoOneCanNotChangeThePath(): void
    {
        [$client, $transport] = $this->fake();
        $client->sms()->get('../me');

        $this->assertSame(self::BASE . '/sms/..%2Fme', $transport->calls[0]['url']);
    }

    public function testAnErrorThatAnswersHttp200IsThrown(): void
    {
        [$client] = $this->fake('{"status":"error","message":"These credentials do not match our records."}');

        try {
            $client->account()->balance();
            $this->fail('expected an error');
        } catch (DataFlowsException $e) {
            $this->assertSame(DataFlowsException::API_ERROR, $e->errorCode);
            $this->assertSame(200, $e->httpStatus);
            $this->assertSame('These credentials do not match our records.', $e->getMessage());
        }
    }

    /**
     * @return array<string, array{int}>
     */
    public static function errorStatuses(): array
    {
        return ['403' => [403], '404' => [404], '422' => [422], '500' => [500]];
    }

    #[DataProvider('errorStatuses')]
    public function testHttpErrorsAreThrownWithTheApiMessage(int $status): void
    {
        [$client] = $this->fake('{"status":"error","message":"Refused"}', $status);

        try {
            $client->account()->balance();
            $this->fail('expected an error');
        } catch (DataFlowsException $e) {
            $this->assertSame(DataFlowsException::API_ERROR, $e->errorCode);
            $this->assertSame($status, $e->httpStatus);
            $this->assertSame('Refused', $e->getMessage());
        }
    }

    public function testHttp429IsReportedAsRateLimitedAndNotRetried(): void
    {
        [$client, $transport] = $this->fake('{"status":"error","message":"Too many"}', 429);

        try {
            $client->sms()->send(self::NUMBER, 'Sender', 'Hi');
            $this->fail('expected an error');
        } catch (DataFlowsException $e) {
            $this->assertSame(DataFlowsException::RATE_LIMITED, $e->errorCode);
        }
        $this->assertCount(1, $transport->calls);
    }

    public function testAResponseThatIsNotJsonIsReportedClearly(): void
    {
        [$client] = $this->fake('<html>Bad gateway</html>', 502);

        try {
            $client->account()->balance();
            $this->fail('expected an error');
        } catch (DataFlowsException $e) {
            $this->assertSame(DataFlowsException::INVALID_RESPONSE, $e->errorCode);
            $this->assertSame(502, $e->httpStatus);
        }
    }

    public function testTheTokenDoesNotShowWhenTheClientOrAnErrorIsLogged(): void
    {
        [$client] = $this->fake('{"status":"error","message":"No"}');

        $this->assertStringNotContainsString(self::TOKEN, print_r($client, true));
        ob_start();
        var_dump($client);
        $this->assertStringNotContainsString(self::TOKEN, (string) ob_get_clean());

        try {
            $client->account()->balance();
        } catch (DataFlowsException $e) {
            $this->assertStringNotContainsString(self::TOKEN, (string) $e);
        }
    }

    public function testATokenIsRequiredAndCanComeFromTheEnvironment(): void
    {
        $saved = getenv('DATAFLOWS_API_TOKEN');
        putenv('DATAFLOWS_API_TOKEN');

        try {
            try {
                new Client();
                $this->fail('expected a refusal');
            } catch (DataFlowsException $e) {
                $this->assertSame(DataFlowsException::INVALID_INPUT, $e->errorCode);
            }

            putenv('DATAFLOWS_API_TOKEN=' . self::TOKEN);
            $transport = new FakeTransport();
            (new Client(transport: $transport))->account()->balance();
            $this->assertContains('Authorization: Bearer ' . self::TOKEN, $transport->calls[0]['headers']);
        } finally {
            $saved === false ? putenv('DATAFLOWS_API_TOKEN') : putenv('DATAFLOWS_API_TOKEN=' . $saved);
        }
    }

    public function testTheTokenIsNeverSentOverPlainHttp(): void
    {
        try {
            new Client(self::TOKEN, 'http://example.com/api');
            $this->fail('expected a refusal');
        } catch (DataFlowsException $e) {
            $this->assertSame(DataFlowsException::INVALID_INPUT, $e->errorCode);
        }

        $this->assertInstanceOf(Client::class, new Client(self::TOKEN, 'http://127.0.0.1:3000/api'));
        $this->assertInstanceOf(Client::class, new Client(self::TOKEN, 'https://example.com/api/'));
    }
}
