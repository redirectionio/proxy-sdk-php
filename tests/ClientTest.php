<?php

namespace tests\RedirectionIO\Client;

use PHPUnit\Framework\TestCase;
use RedirectionIO\Client\Client;
use RedirectionIO\Client\HttpMessage\RedirectResponse;
use RedirectionIO\Client\HttpMessage\Request;
use RedirectionIO\Client\HttpMessage\Response;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\ProcessBuilder;

/**
 * @covers \RedirectionIO\Client\Client
 */
class ClientTest extends TestCase
{
    private $host = 'localhost';
    private $port = 3100;
    private $client;
    private $agent;

    public static function setUpBeforeClass()
    {
        static::startAgent();
    }

    public function setUp()
    {
        $this->client = new Client([
            'host1' => ['host' => $this->host, 'port' => $this->port],
        ]);
    }

    public function testFindRedirectWhenExist()
    {
        $request = $this->createRequest(['path' => 'foo']);

        $response = $this->client->findRedirect($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('301', $response->getStatusCode());
        $this->assertSame('http://host1.com/bar', $response->getLocation());
    }

    public function testFindRedirectWhenExistTwice()
    {
        $request = $this->createRequest(['path' => 'foo']);

        $response = $this->client->findRedirect($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('301', $response->getStatusCode());
        $this->assertSame('http://host1.com/bar', $response->getLocation());

        $response = $this->client->findRedirect($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('301', $response->getStatusCode());
        $this->assertSame('http://host1.com/bar', $response->getLocation());
    }

    public function testFindRedirectWhenNotExist()
    {
        $request = $this->createRequest(['path' => 'hello']);

        $response = $this->client->findRedirect($request);

        $this->assertNull($response);
    }

    public function testFindRedirectWhenAgentDown()
    {
        $client = new Client([
            'host1' => ['host' => 'unknown-host', 'port' => 80],
        ]);

        $request = $this->createRequest();

        $response = $client->findRedirect($request);

        $this->assertNull($response);
    }

    /**
     * @expectedException \RedirectionIO\Client\Exception\AgentNotFoundException
     * @expectedExceptionMessage Agent not found.
     */
    public function testFindRedirectWhenAgentDownAndDebug()
    {
        $client = new Client([
            'host1' => ['host' => 'unknown-host', 'port' => 80],
        ], 1000000, true);

        $request = $this->createRequest();

        $client->findRedirect($request);
    }

    public function testLogRedirection()
    {
        $request = $this->createRequest();
        $response = new Response();

        $this->assertTrue($this->client->log($request, $response));
    }

    public function testLogRedirectionWhenAgentDown()
    {
        $client = new Client([
            'host1' => ['host' => 'unknown-host', 'port' => 80],
        ]);

        $request = $this->createRequest();
        $response = new Response();

        $this->assertFalse($client->log($request, $response));
    }

    /**
     * @expectedException \RedirectionIO\Client\Exception\AgentNotFoundException
     * @expectedExceptionMessage Agent not found.
     */
    public function testLogRedirectionWhenAgentDownAndDebug()
    {
        $client = new Client([
            'host1' => ['host' => 'unknown-host', 'port' => 80],
        ], 1000000, true);

        $request = $this->createRequest();
        $response = new Response();

        $client->log($request, $response);
    }

    public function testCanFindWorkingHostInMultipleHostsArray()
    {
        $client = new Client([
            'host1' => ['host' => 'unknown-host', 'port' => 80],
            'host2' => ['host' => 'unknown-host', 'port' => 81],
            'host3' => ['host' => $this->host, 'port' => $this->port],
        ]);
        $request = $this->createRequest(['path' => 'foo']);

        $response = $client->findRedirect($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame('301', $response->getStatusCode());
        $this->assertSame('http://host1.com/bar', $response->getLocation());
    }

    public function testWhenAgentGoesDown()
    {
        $agent = static::startAgent(3101);

        $client = new Client([
            'host1' => ['host' => 'unknown-host', 'port' => 80],
            'host2' => ['host' => 'unknown-host', 'port' => 81],
            'host3' => ['host' => $this->host, 'port' => 3101],
        ]);
        $request = $this->createRequest(['path' => 'foo']);

        $response = $client->findRedirect($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame('301', $response->getStatusCode());
        $this->assertSame('http://host1.com/bar', $response->getLocation());

        $agent->stop();

        // Ensure the agent is killed
        usleep(200000);

        $response = $client->findRedirect($request);

        $this->assertNull($response);
    }

    /**
     * @expectedException \RedirectionIO\Client\Exception\BadConfigurationException
     * @expectedExceptionMessage At least one connection is required.
     */
    public function testCannotAllowInstantiationWithEmptyConnectionsOptions()
    {
        $client = new Client([]);
    }

    /**
     * @expectedException \RedirectionIO\Client\Exception\BadConfigurationException
     * @expectedExceptionMessage The required options "host", "port" are missing.
     */
    public function testCannotAllowInstantiationWithEmptyConnectionOptions()
    {
        $client = new Client([[]]);
    }

    private static function startAgent($port = 3100)
    {
        $finder = new PhpExecutableFinder();
        if (false === $binary = $finder->find()) {
            throw new \RuntimeException('Unable to find PHP binary to run a fake agent.');
        }

        $agent = ProcessBuilder::create(['exec', $binary, __DIR__ . '/../src/Resources/fake_agent.php'])
            ->setEnv('RIO_PORT', $port)
            ->getProcess()
        ;
        $agent->start();

        // the php script may take few time before being ready
        usleep(200000);

        if ($agent->isTerminated() && !$agent->isSuccessful()) {
            throw new ProcessFailedException($agent);
        }

        register_shutdown_function(function () use ($agent) {
            $agent->stop();
        });

        return $agent;
    }

    private function createRequest($options = [])
    {
        $host = array_key_exists('host', $options) ? $options['host'] : 'host1.com';
        $path = array_key_exists('path', $options) ? $options['path'] : '';
        $userAgent = array_key_exists('user_agent', $options) ? $options['user_agent'] : 'redirection-io-client/0.0.1';
        $referer = array_key_exists('referer', $options) ? $options['referer'] : 'http://host0.com';

        return new Request($host, $path, $userAgent, $referer);
    }
}
