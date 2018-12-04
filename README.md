# redirection.io Proxy PHP SDK

[redirection.io](https://redirection.io) is a tool to track HTTP errors and
setup useful HTTP redirections. It listens your website's HTTP traffic and logs
every HTTP errors, so you can check that the project's redirection rules apply
efficiently.

Quick demo (see below for detailed info):

```php
$client = new RedirectionIO\Client\Sdk\Client($connections);
$request = new RedirectionIO\Client\Sdk\HttpMessage\Request(
    $_SERVER['HTTP_HOST'],
    $_SERVER['REQUEST_URI'],
    $_SERVER['HTTP_USER_AGENT'],
    $_SERVER['HTTP_REFERER']
);

$response = $client->request(new RedirectionIO\Client\Sdk\Command\MatchWithResponseCommand($request));

// There are no redirection for this Request
if (null === $response) {
    $response = '...' // Handle your request with your application
}

$client->request(new RedirectionIO\Client\Sdk\Command\LogCommand($request, $response));

// Finally, Returns your response
```

## Requirements

- [Composer](https://getcomposer.org/)
- PHP 5.5+

## Installation

To use redirection.io in your project, add it to your composer.json file:

    $ composer require redirectionio/proxy-sdk

## Usage

### Instantiate Client

Before starting, you need to instantiate a new Client.

```php
use RedirectionIO\Client\Sdk\Client;

$client = new Client(array $connections, int $timeout = 1000000, bool $debug = false, LoggerInterface $logger = null);
```

Parameters:

- `array $connections` array of connection(s) parameters to the Agent(s)
    ```php
    $connections = [
        'connection_tcp' => 'tcp://127.0.0.1:20301',
        'connection_unix' => 'unix:///var/run/redirectionio_agent.sock',
    ];

    ```
- `$timeout` timeout in microsecond for connection/request;
- `$debug` enable or disable debug mode. In debug mode an exception is thrown is something goes wrong, if not every errors is silenced;
- `\Psr\Log\LoggerInterface $logger` A logger.

### Find if a redirection rule exists

Check if request URI matches a redirect rule in the agent. If yes return a
`RedirectResponse`, else return `null`.

```php
use RedirectionIO\Client\Sdk\Client;
use RedirectionIO\Client\Sdk\HttpMessage\Request;
use RedirectionIO\Client\Sdk\Command\MatchCommand;

$client->request(new MatchWithResponseCommand(Request $request);
```

Parameter:
- `\RedirectionIO\Client\Sdk\HttpMessage\Request $request`.

Return values:
- `\RedirectionIO\Client\Sdk\HttpMessage\RedirectResponse $response` if agent has found a redirect rule for the current request uri;
- `null` if there isn't redirect rule set for the current uri in the agent.

### Find if a redirection rule exists for old agent (<1.4.0)

Check if request URI matches a redirect rule in the agent. If yes return a
`RedirectResponse`, else return `null`.

This will also return null if the rule should have been matched against a Response Status Code. This is mainly
for BC Compatibility and avoid old proxy to handle rules that it should not.

```php
use RedirectionIO\Client\Sdk\Client;
use RedirectionIO\Client\Sdk\HttpMessage\Request;
use RedirectionIO\Client\Sdk\Command\MatchCommand;

$client->request(new MatchCommand(Request $request);
```

Parameter:
- `\RedirectionIO\Client\Sdk\HttpMessage\Request $request`.

Return values:
- `\RedirectionIO\Client\Sdk\HttpMessage\RedirectResponse $response` if agent has found a redirect rule for the current request uri;
- `null` if there isn't redirect rule set for the current uri in the agent.

### Log a request/response couple

Allow you to log a request/response couple for every request.

```php
use RedirectionIO\Client\Sdk\Client;
use RedirectionIO\Client\Sdk\Command\LogCommand;
use RedirectionIO\Client\Sdk\HttpMessage\Response;
use RedirectionIO\Client\Sdk\HttpMessage\Request;

$client->request(new LogCommand(Request $request, Response $response));
```

Parameters:
- `\RedirectionIO\Client\Sdk\HttpMessage\Response $request`
- `\RedirectionIO\Client\Sdk\HttpMessage\Request $response`


Return value:
- `bool` is `true` if log has been successfully added, else `false`

## Contribution

We take care of all new PRs. Any contribution is welcome :) Thanks.

### Install

    $ composer install

### Run tests

    $ composer test
