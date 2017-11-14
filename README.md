# redirection.io PHP SDK

redirection.io is a tool to track HTTP errors and setup useful HTTP
redirections. It listens your website's HTTP traffic and logs every HTTP errors,
so you can check that the project's redirection rules apply efficiently.

Quick demo (see below for detailed info):

```php
$client = new RedirectionIO\Client\Client($connectionsOptions);
$request = new RedirectionIO\Client\HttpMessage\Request(
    $_SERVER['HTTP_HOST'],
    $_SERVER['REQUEST_URI'],
    $_SERVER['HTTP_USER_AGENT'],
    $_SERVER['HTTP_REFERER']
);
$response = $client->findRedirect($request);

// There are no redirection for this Request
if (null === $response) {
    $response = '...' // Handle your request with your application
}

$client->log($request, $response);

// Finally, Returns your content or redirect
```

## Requirements

- [Composer](https://getcomposer.org/)
- PHP 5.5+

## Installation

To use redirection.io in your project, add it to your composer.json file:

    $ composer require redirectionio/sdk

## Usage

### Instantiate Client

Before starting, you need to instantiate a new Client.

```php
use RedirectionIO\Client\Client;

$client = new Client(array $connectionsOptions, $timeout = 1000000, $debug = false, LoggerInterface $logger = null);
```

Parameters:

- `array $connectionsOptions` array of connection(s) parameters to the Agent(s)
    ```php
    $connectionsOptions = [
        'connection1' => ['host' => 'host1', 'port' => 8001],
        'connection2' => ['host' => 'host2', 'port' => 8002],
        ...
    ];

    // Note: 'host' and 'port' options are both required
    ```
- `$timeout` timeout in microsecond for connection/request;
- `$debug` enable or disable debug mode. In debug mode an exception is thrown is something goes wrong, if not every errors is silenced;
- `\Psr\Log\LoggerInterface $logger` A logger.

### Find if a redirection rule exists

Check if request URI matches a redirect rule in the agent. If yes return a
`RedirectResponse`, else return `null`.

```php
use RedirectionIO\Client\Client;
use RedirectionIO\Client\HttpMessage\Request;

$client->findRedirect(Request $request);
```

Parameter:
- `\RedirectionIO\Client\HttpMessage\Request $request`.

Return values:
- `\RedirectionIO\Client\HttpMessage\RedirectResponse $response` if agent has found a redirect rule for the current request uri;
- `null` if there isn't redirect rule set for the current uri in the agent.

### Log a request/response couple

Allow you to log a request/response couple for every request.

```php
use RedirectionIO\Client\Client;
use RedirectionIO\Client\HttpMessage\Response;
use RedirectionIO\Client\HttpMessage\Request;

$client->log(Request $request, Response $response);
```

Parameters:
- `\RedirectionIO\Client\HttpMessage\Response $request`
- `\RedirectionIO\Client\HttpMessage\Request $response`


Return value:
- `bool` is `true` if log has been successfully added, else `false`

## Contribution

We take care of all new PRs. Any contribution is welcome :) Thanks.

### Install

    $ composer install

### Run tests

    $ composer run test
