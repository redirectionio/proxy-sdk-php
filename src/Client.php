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

        $json = json_decode($agentResponse, true);

        if (null === $json) {
            if ($this->debug) {
                throw new \ErrorException(sprintf('Impossible to decode the JSON (%s). Content: "%s"', json_last_error_msg(), $agentResponse));
            }

            return null;
        }

        $ruleId = null;
        $location = null;

        if (isset($json['matched_rule'], $json['matched_rule']['id'])) {
            $ruleId = $json['matched_rule']['id'];
        }

        if (isset($json['location'])) {
            $location = $json['location'];
        }

        return new Response((int) $json['status_code'], $ruleId, $location);
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

        if ($response->getLocation()) {
            $responseContext['target'] = $response->getLocation();
        }

        if ($response->getRuleId()) {
            $responseContext['rule_id'] = $response->getRuleId();
        }

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
        return $this->fwrite($connection, $content);
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

            $this->logger->warning('Impossible to execute a boxed call.', [
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

    /**
     * Replace fwrite behavior as API is broken in PHP.
     *
     * @see https://secure.phabricator.com/rPHU69490c53c9c2ef2002bc2dd4cecfe9a4b080b497
     *
     * @param resource $stream The stream resource
     * @param string   $bytes  Bytes written in the stream
     *
     * @return bool|int false if pipe is broken, number of bytes written otherwise
     */
    private function fwrite($stream, $bytes)
    {
        if (!strlen($bytes)) {
            return 0;
        }
        $result = @fwrite($stream, $bytes);
        if (0 !== $result) {
            // In cases where some bytes are witten (`$result > 0`) or
            // an error occurs (`$result === false`), the behavior of fwrite() is
            // correct. We can return the value as-is.
            return $result;
        }
        // If we make it here, we performed a 0-length write. Try to distinguish
        // between EAGAIN and EPIPE. To do this, we're going to `stream_select()`
        // the stream, write to it again if PHP claims that it's writable, and
        // consider the pipe broken if the write fails.
        $read = [];
        $write = [$stream];
        $except = [];
        @stream_select($read, $write, $except, 0);
        if (!$write) {
            // The stream isn't writable, so we conclude that it probably really is
            // blocked and the underlying error was EAGAIN. Return 0 to indicate that
            // no data could be written yet.
            return 0;
        }
        // If we make it here, PHP **just** claimed that this stream is writable, so
        // perform a write. If the write also fails, conclude that these failures are
        // EPIPE or some other permanent failure.
        $result = @fwrite($stream, $bytes);
        if (0 !== $result) {
            // The write worked or failed explicitly. This value is fine to return.
            return $result;
        }
        // We performed a 0-length write, were told that the stream was writable, and
        // then immediately performed another 0-length write. Conclude that the pipe
        // is broken and return `false`.
        return false;
    }
}
