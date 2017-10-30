# redirection.io PHP Client

redirection.io is a tool to track HTTP errors and setup useful HTTP redirections.
It listens your website's HTTP traffic and logs every HTTP error, so you can check that the project's redirection rules apply efficiently.

Quick demo (see below for detailed info):

```
$client = new Client($connectionsOptions);
$request = new ServerRequest(
    $_SERVER['HTTP_HOST'],
    $_SERVER['REQUEST_URI'],
    $_SERVER['HTTP_USER_AGENT'],
    $_SERVER['HTTP_REFERER']
);
$response = $client->findRedirect($request);

if (null === $response) {
    $response = ... // Handle your request
}

$client->log($request, $response);

... // Then, output your content or redirect
```

## Requirements

- [composer](https://getcomposer.org/)
- PHP 5.6+

## Installation

To use redirection.io in your project, add it to your composer.json file:

```
$ composer require redirectionio/redirectionio
```

## Usage

### Instantiate Client 

Before starting, you need to instantiate a new Client.

```
use RedirectionIO\Client\Client;

$client = new Client(array $connectionsOptions = [], $timeout = 1000000, $debug = false, Logger $logger = null);
```

Parameters:
- `array $connectionsOptions` array of connection(s) parameters to the Agent

```
$connectionsOptions = [
    'connection1' => ['host' => 'host1', 'port' => 8001],
    'connection2' => ['host' => 'host2', 'port' => 8002],
    ...
];

// Note: 'host' and 'port' options are both required
```

- `$timeout` timeout in microsecond for connection/request
- `$debug` enable or disable debug mode
- `\RedirectionIO\Client\Log\Logger $logger` logger

### Find if a redirection rule exists

Check if request uri matches a redirect rule in the agent. If yes return a redirect response, else return null.

```
use RedirectionIO\Client\Client;
use RedirectionIO\Client\HTTPMessage\ServerRequest;

$client->findRedirect(ServerRequest $request);
```

Parameter:
- `\RedirectionIO\Client\HTTPMessage\ServerRequest $request`

Return values: 
- `\RedirectionIO\Client\HTTPMessage\RedirectResponse $response` if agent has found a redirect rule for the current request uri
- `null` if there isn't redirect rule set for the current uri in the agent

### Log a request/response couple

Allow you to log a request/response couple for each interaction with the agent.

```
use RedirectionIO\Client\Client;
use RedirectionIO\Client\HTTPMessage\RedirectResponse;
use RedirectionIO\Client\HTTPMessage\ServerRequest;

$client->log(ServerRequest $request, RedirectResponse $response);
```

Parameters:
- `\RedirectionIO\Client\HTTPMessage\RedirectResponse $request`
- `\RedirectionIO\Client\HTTPMessage\ServerRequest $response`


Return value:
- `bool` is `true` if log has been successfully added, else `false`

## Contribution

We take care of all new PRs. Any contribution is welcome :) Thanks.

### Install

```
$ composer install
```

### Run tests

```
$ composer run test
```
