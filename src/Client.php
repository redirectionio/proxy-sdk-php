<?php

namespace RedirectionIO\Client;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RedirectionIO\Client\Exception\AgentNotFoundException;
use RedirectionIO\Client\HTTPMessage\RedirectResponse;
use RedirectionIO\Client\HTTPMessage\ServerRequest;
use Symfony\Component\OptionsResolver\OptionsResolver;

class Client
{
    /**
     * @var array[] A list of connections options
     */
    private $connectionsOptions;

    /**
     * @var resource|null current connection
     */
    private $currentConnection;

    /**
     * @var string Current connection name
     */
    private $currentConnectionName;

    /**
     * @var int Timeout in microseconds for connection / request
     */
    private $timeout;

    private $debug;
    private $logger;

    public function __construct(array $connectionsOptions = [], $timeout = 1000000, $debug = false, LoggerInterface $logger = null)
    {
        foreach ($connectionsOptions as $connectionName => $connectionOptions) {
            $this->connectionsOptions[$connectionName] = $this->resolveConnectionOptions($connectionOptions);
        }

        $this->timeout = $timeout;
        $this->debug = $debug;
        $this->logger = $logger ?: new NullLogger();
    }

    /**
     * Find redirect response from a request.
     *
     * @param ServerRequest $request
     *
     * @return RedirectResponse|null
     */
    public function findRedirect(ServerRequest $request)
    {
        $requestContext = [
            'host' => $request->getHost(),
            'request_uri' => $request->getPath(),
            'user_agent' => $request->getUserAgent(),
            'referer' => $request->getReferer(),
        ];

        try {
            $redirectResponse = $this->request('GET', $requestContext);
        } catch (ExceptionInterface $exception) {
            if ($this->debug) {
                throw $exception;
            }

            return null;
        }

        if (strlen($redirectResponse) === 0) {
            return null;
        }

        list($code, $link) = explode('|', $redirectResponse);

        return new RedirectResponse($link, $code);
    }

    /**
     * Log request/response couple.
     *
     * @param ServerRequest    $request
     * @param RedirectResponse $response
     *
     * @return bool
     */
    public function log(ServerRequest $request, RedirectResponse $response)
    {
        $responseContext = [
            'status_code' => $response->getStatusCode(),
            'host' => $request->getHost(),
            'request_uri' => $request->getPath(),
            'user_agent' => $request->getUserAgent(),
            'referer' => $request->getReferer(),
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
        $optionsResolver = new OptionsResolver();
        $optionsResolver->setRequired(['host', 'port']);

        $options = $optionsResolver->resolve($options);
        $options['retries'] = 2;

        return $options;
    }

    private function request($command, $context)
    {
        $connection = $this->getConnection();

        $content = $command . ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
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
            sprintf('tcp://%s:%s', $options['host'], $options['port']),
            $errNo,
            $errMsg,
            1, // timeout in seconds
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
        set_error_handler(__CLASS__ . '::handleInternalError');

        $returnValue = $defaultReturnValue;

        try {
            $returnValue = call_user_func_array([$this, $method], $args);
        } catch (\ErrorException $exception) {
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
