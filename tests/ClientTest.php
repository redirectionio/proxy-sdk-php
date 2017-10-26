<?php

namespace tests\RedirectionIO\Client;

use PHPUnit\Framework\TestCase;
use RedirectionIO\Client\Client;
use RedirectionIO\Client\HTTPMessage\RedirectResponse;
use RedirectionIO\Client\HTTPMessage\ServerRequest;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\ProcessBuilder;

/**
 * @covers \RedirectionIO\Client\Client
 */
class ClientTest extends TestCase
{
    private $host = 'localhost';
    private $port = 8000;
    private $client;

    public static function setUpBeforeClass()
    {
        $finder = new PhpExecutableFinder();
        if (false === $binary = $finder->find()) {
            throw new \RuntimeException('Unable to find PHP binary to run server.');
        }

        $agent = (new ProcessBuilder(['exec', $binary, __DIR__ . '/../src/FakeAgent/Agent.php']))->getProcess();
        $agent->start();
        usleep(250000);
        if ($agent->isTerminated() && !$agent->isSuccessful()) {
            throw new ProcessFailedException($agent);
        }
        register_shutdown_function(function () use ($agent) {
            $agent->stop();
        });
    }

    public function setUp()
    {
        $this->client = new Client([
            'host1' => ['host' => $this->host, 'port' => $this->port],
        ]);
    }

    public function testCanFindRedirectLink()
    {
        $request = $this->buildRequest(['path' => 'foo']);
        $response = $this->client->findRedirect($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('301', $response->getStatusCode());
        $this->assertSame('http://host1.com/bar', $response->getLocation());
    }

    public function testCannotFindRedirectLink()
    {
        $request = $this->buildRequest(['path' => 'hello']);
        $response = $this->client->findRedirect($request);

        $this->assertNull($response);
    }

    public function testCanLogRedirection()
    {
        $request = $this->buildRequest(['path' => 'foo']);
        $response = new RedirectResponse();

        $this->assertTrue($this->client->log($request, $response));
    }

    /**
     * @expectedException           \RedirectionIO\Client\Exception\AgentNotFoundException
     * @expectedExceptionMessage    Agent not found
     */
    public function testCannotConnectToAgent()
    {
        $this->client = new Client([
            'host1' => ['host' => 'unknown-host', 'port' => 80],
        ]);
        $request = $this->buildRequest();
        $this->client->findRedirect($request);
    }

    public function testCanFindWorkingHostInMultipleHostsArray()
    {
        $this->client = new Client([
            'host1' => ['host' => 'unknown-host', 'port' => 80],
            'host2' => ['host' => 'unknown-host', 'port' => 81],
            'host3' => ['host' => $this->host, 'port' => $this->port],
        ]);
        $request = $this->buildRequest(['path' => 'foo']);
        $response = $this->client->findRedirect($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('301', $response->getStatusCode());
        $this->assertSame('http://host1.com/bar', $response->getLocation());
    }

    /**
     * @expectedException           \Symfony\Component\OptionsResolver\Exception\MissingOptionsException
     * @expectedExceptionMessage    The required options "host", "port" are missing.
     */
    public function testCannotAllowInstantiationWithEmptyConnectionsOptions()
    {
        $request = $this->buildRequest();
        $this->client = new Client([[]]);
    }

    private function buildRequest($opts = [])
    {
        $host = array_key_exists('host', $opts) ? $opts['host'] : 'host1.com';
        $path = array_key_exists('path', $opts) ? $opts['path'] : '';
        $userAgent = array_key_exists('user_agent', $opts) ? $opts['user_agent'] : 'redirection-io-client/0.0.1';
        $referer = array_key_exists('referer', $opts) ? $opts['referer'] : 'http://host0.com';

        $request = new ServerRequest($host, $path, $userAgent, $referer);

        return $request;
    }
}
