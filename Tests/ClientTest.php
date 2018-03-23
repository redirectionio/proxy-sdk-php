<?php

namespace RedirectionIO\Client\Sdk\Tests;

use PHPUnit\Framework\TestCase;
use RedirectionIO\Client\Sdk\Client;
use RedirectionIO\Client\Sdk\HttpMessage\RedirectResponse;
use RedirectionIO\Client\Sdk\HttpMessage\Request;
use RedirectionIO\Client\Sdk\HttpMessage\Response;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * @covers \RedirectionIO\Client\Sdk\Client
 */
class ClientTest extends TestCase
{
    private $connection = 'tcp://localhost:3100';
    private $client;

    public static function setUpBeforeClass()
    {
        static::startAgent();
    }

    public function setUp()
    {
        $this->client = new Client([
            'host1' => $this->connection,
        ]);
    }

    public function testFindRedirectWhenExist()
    {
        $request = $this->createRequest(['path' => '/foo']);

        $response = $this->client->findRedirect($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(301, $response->getStatusCode());
        $this->assertSame('/bar', $response->getLocation());
    }

    public function testFindRedirectWhenExistUsingUnixSocket()
    {
        $agent = static::startAgent(['socket_type' => 'AF_UNIX']);

        $client = new Client(['host1' => 'unix://'.sys_get_temp_dir().'/fake_agent.sock']);

        $request = $this->createRequest(['path' => '/foo']);

        $response = $client->findRedirect($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(301, $response->getStatusCode());
        $this->assertSame('/bar', $response->getLocation());
    }

    public function testFindRedirectWhenExistTwice()
    {
        $request = $this->createRequest(['path' => '/foo']);

        $response = $this->client->findRedirect($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(301, $response->getStatusCode());
        $this->assertSame('/bar', $response->getLocation());

        $response = $this->client->findRedirect($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(301, $response->getStatusCode());
        $this->assertSame('/bar', $response->getLocation());
    }

    public function testFindRedirectWhenNotExist()
    {
        $request = $this->createRequest(['path' => '/hello']);

        $response = $this->client->findRedirect($request);

        $this->assertNull($response);
    }

    public function testFindRedirectWhenAgentDown()
    {
        $client = new Client([
            'host1' => 'tcp://unknown-host:80',
        ]);

        $request = $this->createRequest();

        $response = $client->findRedirect($request);

        $this->assertNull($response);
    }

    /**
     * @expectedException \RedirectionIO\Client\Sdk\Exception\AgentNotFoundException
     * @expectedExceptionMessage Agent not found.
     */
    public function testFindRedirectWhenAgentDownAndDebug()
    {
        $client = new Client([
            'host1' => 'tcp://unknown-host:80',
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
            'host1' => 'tcp://unknown-host:80',
        ]);

        $request = $this->createRequest();
        $response = new Response();

        $this->assertFalse($client->log($request, $response));
    }

    /**
     * @expectedException \RedirectionIO\Client\Sdk\Exception\AgentNotFoundException
     * @expectedExceptionMessage Agent not found.
     */
    public function testLogRedirectionWhenAgentDownAndDebug()
    {
        $client = new Client([
            'host1' => 'tcp://unknown-host:80',
        ], 1000000, true);

        $request = $this->createRequest();
        $response = new Response();

        $client->log($request, $response);
    }

    public function testCanFindWorkingHostInMultipleHostsArray()
    {
        $client = new Client([
            'host1' => 'tcp://unknown-host:80',
            'host2' => 'tcp://unknown-host:81',
            'host3' => $this->connection,
        ]);
        $request = $this->createRequest(['path' => '/foo']);

        $response = $client->findRedirect($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(301, $response->getStatusCode());
        $this->assertSame('/bar', $response->getLocation());
    }

    public function testWhenAgentGoesDown()
    {
        $agent = static::startAgent(['port' => 3101]);

        $client = new Client([
            'host1' => 'tcp://unknown-host:80',
            'host2' => 'tcp://unknown-host:81',
            'host3' => 'tcp://localhost:3101',
        ]);
        $request = $this->createRequest(['path' => '/foo']);

        $response = $client->findRedirect($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(301, $response->getStatusCode());
        $this->assertSame('/bar', $response->getLocation());

        $agent->stop();

        $response = $client->findRedirect($request);

        $this->assertNull($response);
    }

    /**
     * @expectedException \RedirectionIO\Client\Sdk\Exception\BadConfigurationException
     * @expectedExceptionMessage At least one connection is required.
     */
    public function testCannotAllowInstantiationWithEmptyConnectionsOptions()
    {
        $client = new Client([]);
    }

    public function testFind410ResponseWhenExist()
    {
        $request = $this->createRequest(['path' => '/garply']);

        $response = $this->client->findRedirect($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(410, $response->getStatusCode());
    }

    private static function startAgent($options = [])
    {
        $socket_type = isset($options['socket_type']) ? $options['socket_type'] : 'AF_INET';
        $port = isset($options['port']) ? $options['port'] : 3100;

        $finder = new PhpExecutableFinder();
        if (false === $binary = $finder->find()) {
            throw new \RuntimeException('Unable to find PHP binary to run a fake agent.');
        }

        $agent = new Process([$binary, __DIR__.'/../src/Resources/fake_agent.php']);
        $agent
            ->inheritEnvironmentVariables(true)
            ->setEnv(['RIO_SOCKET_TYPE' => $socket_type, 'RIO_PORT' => $port])
            ->start()
        ;

        static::waitUntilProcReady($agent);

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
        $host = isset($options['host']) ? $options['host'] : 'host1.com';
        $path = isset($options['path']) ? $options['path'] : '';
        $userAgent = isset($options['user_agent']) ? $options['user_agent'] : 'redirection-io-client/0.0.1';
        $referer = isset($options['referer']) ? $options['referer'] : 'http://host0.com';

        return new Request($host, $path, $userAgent, $referer);
    }

    private static function waitUntilProcReady(Process $proc)
    {
        while (true) {
            usleep(50000);
            foreach ($proc as $type => $data) {
                if ($proc::OUT === $type || $proc::ERR === $type) {
                    break 2;
                }
            }
        }
    }
}
