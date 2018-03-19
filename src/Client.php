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
    private $connectionsOptions;
    private $currentConnection;
    private $currentConnectionName;
    private $timeout;
    private $debug;
    private $logger;

    /**
     * @param array           $connectionsOptions
     * @param int             $timeout
     * @param bool            $debug
     * @param LoggerInterface $logger
     */
    public function __construct(array $connectionsOptions, $timeout = 10000, $debug = false, LoggerInterface $logger = null)
    {
        if (empty($connectionsOptions)) {
            throw new BadConfigurationException('At least one connection is required.');
        }

        foreach ($connectionsOptions as $connectionName => $connectionOptions) {
            $this->connectionsOptions[$connectionName] = $this->resolveConnectionOptions($connectionOptions);
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

        return new RedirectResponse($agentResponse->location, (int) $agentResponse->status_code);
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

    private function resolveConnectionOptions(array $options = [])
    {
        if (isset($options['remote_socket'])) {
            $remoteSocket = explode(':', $options['remote_socket']);

            if (!isset($remoteSocket[0]) || isset($remoteSocket[2])) {
                throw new BadConfigurationException('The option "remote_socket" should have "/link/to/agent/socket" or "ip_agent:port" format.');
            }

            if (!isset($remoteSocket[1])) {
                $options['remote_socket'] = 'unix://'.$remoteSocket[0];
            } else {
                $options['remote_socket'] = sprintf('tcp://%s:%s', $remoteSocket[0], $remoteSocket[1]);
            }

            $options['retries'] = 2;

            return $options;
        }

        if (!isset($options['host']) || !isset($options['port'])) {
            throw new BadConfigurationException('The required options "host", "port" are missing.');
        }

        $options['remote_socket'] = sprintf('tcp://%s:%s', $options['host'], $options['port']);
        $options['retries'] = 2;

        return $options;
    }

    private function request($command, $context)
    {
        $connection = $this->getConnection();

        $content = $command.' '.json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n";
        $sent = $this->box('doSend', false, [$connection, $content]);

        // if the pipe is broken, `fwrite` will throw a Notice
        if (false === $sent) {
            $this->logger->debug('Impossible to send content to the connection.', [
                'options' => $this->connectionsOptions[$this->currentConnectionName],
            ]);

            --$this->connectionsOptions[$this->currentConnectionName]['retries'];
            $this->currentConnection = null;

            return $this->request($command, $context);
        }

        $received = $this->box('doGet', false, [$connection]);

        // false: the persistent connection is stale
        if (false === $received) {
            $this->logger->debug('Impossible to get content from the connection.', [
                'options' => $this->connectionsOptions[$this->currentConnectionName],
            ]);

            --$this->connectionsOptions[$this->currentConnectionName]['retries'];
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

        foreach ($this->connectionsOptions as $name => $connectionOptions) {
            if ($connectionOptions['retries'] <= 0) {
                continue;
            }

            $this->logger->debug('New connection chosen. Trying to connect.', [
                'connectionOptions' => $connectionOptions,
                'name' => $name,
            ]);

            $connection = $this->box('doConnect', false, [$connectionOptions]);

            if (false === $connection) {
                $this->logger->debug('Impossible to connect to the connection.', [
                    'connectionOptions' => $connectionOptions,
                    'name' => $name,
                ]);

                $this->connectionsOptions[$name]['retries'] = 0;

                continue;
            }

            $this->logger->debug('New connection approved.', [
                'connectionOptions' => $connectionOptions,
                'name' => $name,
            ]);

            stream_set_timeout($connection, 0, $this->timeout);

            $this->currentConnection = $connection;
            $this->currentConnectionName = $name;

            return $connection;
        }

        $this->logger->error('Can not find an agent.', [
            'connections_options' => $this->connectionsOptions,
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
