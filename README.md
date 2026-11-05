# DataFlows SMS for PHP

The official PHP client for the [DataFlows SMS API](https://dataflows.com.au/developers). It needs PHP 8.1 or newer with the curl and json extensions, and has no other dependencies.

## Install

```bash
composer require dataflows-au/dataflows-sms
```

## Send your first SMS

Find your API token in your DataFlows account under Developers, and keep it in an environment variable:

```bash
export DATAFLOWS_API_TOKEN="your-token"
```

```php
<?php

require 'vendor/autoload.php';

use DataFlows\Sms\Client;

$client = new Client();

$result = $client->sms()->send(
    recipient: '61404235133',
    senderId: 'YOUR_SENDER_ID',
    message: 'Hello from DataFlows',
);

echo $result['campaign_id'];
```

This really sends a text and uses your credit, so change the number first.

## What you can do

| Call | What it does |
|---|---|
| `$client->account()->profile()` | Your account details |
| `$client->account()->balance()` | Your remaining balance |
| `$client->senderIds()->list()` | Your active sender IDs |
| `$client->sms()->send($recipient, $senderId, $message, $scheduleTime)` | Send or schedule an SMS |
| `$client->sms()->list($page, $limit)` | A page of your sent messages |
| `$client->sms()->get($uid)` | One message |
| `$client->groups()->list($page)` | A page of your contact groups |
| `$client->groups()->create($name)` | Add a group |
| `$client->groups()->get($groupUid)` | One group |
| `$client->groups()->update($groupUid, $name)` | Rename a group |
| `$client->groups()->delete($groupUid)` | Delete a group |
| `$client->contacts()->list($groupUid, $page)` | A page of a group's contacts |
| `$client->contacts()->create($groupUid, $phone, $firstName, $lastName, $fields)` | Add a contact |
| `$client->contacts()->get($groupUid, $contactUid)` | One contact |
| `$client->contacts()->update($groupUid, $contactUid, $phone, $firstName, $lastName, $fields)` | Change a contact |
| `$client->contacts()->delete($groupUid, $contactUid)` | Delete a contact |

## Sending

**Numbers** go in international format, such as `61404235133`. A number containing letters is refused before anything is sent.

**Several numbers:** pass an array as `recipient`. They're sent during the request, so a long list takes longer, and you get one result per number.

**Scheduling:** set `scheduleTime` to `Y-m-d H:i` in your account's timezone.

**Delivery status:** a message's `customer_status` of `Delivered` means the carrier accepted it. It doesn't confirm the message reached the phone.

**Trial accounts** can only send to numbers they have verified.

## Contacts

Adding a contact sends the group's welcome text if you've set one up on that group. `$fields` holds your group's custom fields, by tag. Leave `$phone` out of an update to keep the current number:

```php
$client->contacts()->update($groupUid, $contactUid, firstName: 'Jo');
```

## Errors

Every failure throws a `DataFlowsException`. This includes errors the API answers with HTTP 200, which many of them do.

```php
use DataFlows\Sms\DataFlowsException;

try {
    $client->sms()->send('61404235133', 'YOUR_SENDER_ID', 'Hello');
} catch (DataFlowsException $e) {
    error_log($e->errorCode . ' ' . $e->httpStatus . ' ' . $e->getMessage());
}
```

| `$e->errorCode` | Meaning |
|---|---|
| `api_error` | DataFlows refused the request. `getMessage()` says why |
| `invalid_input` | Something is missing or wrong. Nothing was sent to DataFlows |
| `rate_limited` | Over 600 requests per minute for this token |
| `timeout` | No answer in time. A send may still have gone out |
| `network_error` | DataFlows couldn't be reached |
| `invalid_response` | DataFlows sent back something unexpected |

**Nothing is retried automatically**, so a message can't go out twice because of this client. After a `timeout`, check `$client->sms()->list()` before you send again.

## Options

```php
$client = new Client(
    apiToken: 'your-token', // defaults to DATAFLOWS_API_TOKEN
    timeout: 60,            // seconds per request, this is the default
);
```

## More

- [Developer hub](https://dataflows.com.au/developers)
- [API reference](https://docs.dataflows.com.au/)
- [OpenAPI spec](https://sms.dataflows.com.au/openapi.json)
- Help: support@dataflows.com.au

## License

MIT
