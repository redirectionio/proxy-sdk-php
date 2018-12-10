<?php

namespace RedirectionIO\Client\Sdk;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RedirectionIO\Client\Sdk\Command\LogCommand;
use RedirectionIO\Client\Sdk\Command\MatchCommand;
use RedirectionIO\Client\Sdk\Exception\AgentNotFoundException;
use RedirectionIO\Client\Sdk\Exception\BadConfigurationException;
use RedirectionIO\Client\Sdk\Exception\ExceptionInterface;
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

    /**
     * @deprecated findRedirect() is deprecated since version 0.2 and will be removed in 0.3. Use request(new MatchCommand()) instead.
     */
    public function findRedirect(Request $request)
    {
        @trigger_error('findRedirect() is deprecated since version 0.2 and will be removed in 0.3. Use request(new MatchCommand()) instead.', E_USER_DEPRECATED);

        return $this->request(new MatchCommand($request));
    }

    /**
     * @deprecated log() is deprecated since version 0.2 and will be removed in 0.3. Use request(new LogCommand()) instead.
     */
    public function log(Request $request, Response $response)
    {
        @trigger_error('log() is deprecated since version 0.2 and will be removed in 0.3. Use request(new LogCommand()) instead.', E_USER_DEPRECATED);

        $this->request(new LogCommand($request, $response));

        return true;
    }

    public function request(Command\CommandInterface $command)
    {
        try {
            return $this->doRequest($command);
        } catch (ExceptionInterface $exception) {
            if ($this->debug) {
                throw $exception;
            }

            return null;
        }
    }

    private function doRequest(Command\CommandInterface $command)
    {
        $connection = $this->getConnection();

        $toSend = $command->getName()."\0".$command->getRequest()."\0";
        $sent = $this->box('doSend', false, [$connection, $toSend]);

        if (false === $sent) {
            $this->logger->debug('Impossible to send content to the connection.', [
                'options' => $this->connections[$this->currentConnectionName],
            ]);

            --$this->connections[$this->currentConnectionName]['retries'];
            $this->currentConnection = null;
            $this->box('disconnect', null, [$connection]);

            return $this->doRequest($command);
        }

        if (!$command->hasResponse()) {
            return null;
        }

        $received = $this->box('doGet', false, [$connection]);

        // false: the persistent connection is stale
        if (false === $received) {
            $this->logger->debug('Impossible to get content from the connection.', [
                'options' => $this->connections[$this->currentConnectionName],
            ]);

            --$this->connections[$this->currentConnectionName]['retries'];
            $this->currentConnection = null;
            $this->box('disconnect', null, [$connection]);

            return $this->doRequest($command);
        }

        return $command->parseResponse(trim($received));
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

    private function disconnect($connection)
    {
        fclose($connection);
    }

    private function doSend($connection, $content)
    {
        return $this->fwrite($connection, $content);
    }

    private function doGet($connection)
    {
        $buffer = '';

        while (true) {
            if (feof($connection)) {
                return false;
            }

            $char = fread($connection, 1);

            if (false === $char) {
                return false;
            }

            // On timeout char is empty
            if ('' === $char) {
                return false;
            }

            if ("\0" === $char) {
                return $buffer;
            }

            $buffer .= $char;
        }
    }

    private function box($method, $defaultReturnValue = null, array $args = [])
    {
        set_error_handler(__CLASS__.'::handleInternalError');

        try {
            $returnValue = \call_user_func_array([$this, $method], $args);
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
        if (!\strlen($bytes)) {
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
