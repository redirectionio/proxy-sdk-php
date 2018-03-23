<?php

namespace RedirectionIO\Client\Sdk;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RedirectionIO\Client\Sdk\Exception\AgentNotFoundException;
use RedirectionIO\Client\Sdk\Exception\BadConfigurationException;
use RedirectionIO\Client\Sdk\Exception\ExceptionInterface;
use RedirectionIO\Client\Sdk\HttpMessage\RedirectResponse;
use RedirectionIO\Client\Sdk\HttpMessage\Request;
use RedirectionIO\Client\Sdk\HttpMessage\Response;

class Client
{
    private $connections;
    private $timeout;
    private $debug;
    private $logger;
    private $currentConnection;
    private $currentConnectionName;

    /**
     * @param int  $timeout
     * @param bool $debug
     */
    public function __construct(array $connections, $timeout = 10000, $debug = false, LoggerInterface $logger = null)
    {
        if (!$connections) {
            throw new BadConfigurationException('At least one connection is required.');
        }

        foreach ($connections as $name => $connection) {
            $this->connections[$name] = [
                'remote_socket' => $connection,
                'retries' => 2,
            ];
        }

        $this->timeout = $timeout;
        $this->debug = $debug;
        $this->logger = $logger ?: new NullLogger();
    }

    public function findRedirect(Request $request)
    {
        $requestContext = [
            'host' => $request->getHost(),
            'request_uri' => $request->getPath(),
            'user_agent' => $request->getUserAgent(),
            'referer' => $request->getReferer(),
            'scheme' => $request->getScheme(),
            'use_json' => true,
        ];

        try {
            $agentResponse = $this->request('GET', $requestContext);
        } catch (ExceptionInterface $exception) {
            if ($this->debug) {
                throw $exception;
            }

            return null;
        }

        if (0 === strlen($agentResponse)) {
            return null;
        }

        $agentResponse = json_decode($agentResponse);

        return 410 == $agentResponse->status_code
            ? new Response(410)
            : new RedirectResponse($agentResponse->location, (int) $agentResponse->status_code);
    }

    public function log(Request $request, Response $response)
    {
        $responseContext = [
            'status_code' => $response->getStatusCode(),
            'host' => $request->getHost(),
            'request_uri' => $request->getPath(),
            'user_agent' => $request->getUserAgent(),
            'referer' => $request->getReferer(),
            'scheme' => $request->getScheme(),
            'use_json' => true,
        ];

        try {
            return (bool) $this->request('LOG', $responseContext);
        } catch (ExceptionInterface $exception) {
            if ($this->debug) {
                throw $exception;
            }

            return false;
        }
    }

    private function request($command, $context)
    {
        $connection = $this->getConnection();

        $content = $command.' '.json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n";
        $sent = $this->box('doSend', false, [$connection, $content]);

        // if the pipe is broken, `fwrite` will throw a Notice
        if (false === $sent) {
            $this->logger->debug('Impossible to send content to the connection.', [
                'options' => $this->connections[$this->currentConnectionName],
            ]);

            --$this->connections[$this->currentConnectionName]['retries'];
            $this->currentConnection = null;

            return $this->request($command, $context);
        }

        $received = $this->box('doGet', false, [$connection]);

        // false: the persistent connection is stale
        if (false === $received) {
            $this->logger->debug('Impossible to get content from the connection.', [
                'options' => $this->connections[$this->currentConnectionName],
            ]);

            --$this->connections[$this->currentConnectionName]['retries'];
            $this->currentConnection = null;

            return $this->request($command, $context);
        }

        return trim($received);
    }

    private function getConnection()
    {
        if (null !== $this->currentConnection) {
            return $this->currentConnection;
        }

        foreach ($this->connections as $name => $connection) {
            if ($connection['retries'] <= 0) {
                continue;
            }

            $this->logger->debug('New connection chosen. Trying to connect.', [
                'connection' => $connection,
                'name' => $name,
            ]);

            $connection = $this->box('doConnect', false, [$connection]);

            if (false === $connection) {
                $this->logger->debug('Impossible to connect to the connection.', [
                    'connection' => $connection,
                    'name' => $name,
                ]);

                $this->connections[$name]['retries'] = 0;

                continue;
            }

            $this->logger->debug('New connection approved.', [
                'connection' => $connection,
                'name' => $name,
            ]);

            stream_set_timeout($connection, 0, $this->timeout);

            $this->currentConnection = $connection;
            $this->currentConnectionName = $name;

            return $connection;
        }

        $this->logger->error('Can not find an agent.', [
            'connections_options' => $this->connections,
        ]);

        throw new AgentNotFoundException();
    }

    private function doConnect($options)
    {
        return stream_socket_client(
            $options['remote_socket'],
            $errNo,
            $errMsg,
            1, // This value is not used but it should not be 0
            STREAM_CLIENT_CONNECT | STREAM_CLIENT_PERSISTENT
        );
    }

    private function doSend($connection, $content)
    {
        return fwrite($connection, $content);
    }

    private function doGet($connection)
    {
        return fgets($connection);
    }

    private function box($method, $defaultReturnValue = null, array $args = [])
    {
        set_error_handler(__CLASS__.'::handleInternalError');

        try {
            $returnValue = call_user_func_array([$this, $method], $args);
        } catch (\ErrorException $exception) {
            $returnValue = $defaultReturnValue;

            $this->logger->warning('Impossible to execute a boxed called.', [
                'method' => $method,
                'default_return_value' => $defaultReturnValue,
                'args' => $args,
                'exception' => $exception,
            ]);
        }

        restore_error_handler();

        return $returnValue;
    }

    private static function handleInternalError($type, $message, $file, $line)
    {
        throw new \ErrorException($message, 0, $type, $file, $line);
    }
}
