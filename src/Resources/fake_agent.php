<?php

/**
 * Examples of payload.
 *
 * GET.
 * Request: GET {"host": "host1.com", "request_uri": "/foo", "user_agent": "redirection-io-client/0.0.1", "referer": "http://host0.com", "use_json": true}
 * Response: {"status_code":301,"location":"/bar"}
 *
 * LOG.
 * Request: LOG {"status_code": 301, "host": "host1.com", "request_uri": "/foo", "user_agent": "redirection-io-client/0.0.1", "referer": "http://host0.com", "use_json": true}
 * Response: ok
 */
set_time_limit(0);

$socket_type = isset($_SERVER['RIO_SOCKET_TYPE']) ? $_SERVER['RIO_SOCKET_TYPE'] : 'AF_INET';
$socket_path = isset($_SERVER['RIO_SOCKET_PATH']) ? $_SERVER['RIO_SOCKET_PATH'] : sys_get_temp_dir().'/fake_agent.sock';
$ip = isset($_SERVER['RIO_HOST']) ? $_SERVER['RIO_HOST'] : 'localhost';
$port = isset($_SERVER['RIO_PORT']) ? $_SERVER['RIO_PORT'] : 3100;
$timeout = 1000000; // seconds
$matcher = [
    ['/foo', '/bar', 301],
    ['/baz', '/qux', 302],
    ['/quux', '/corge', 307],
    ['/uier', '/grault', 308],
    ['/garply', '', 410],
];

switch ($socket_type) {
    case 'AF_INET':
        $local_socket = "tcp://$ip:$port";
        break;
    case 'AF_UNIX':
        $local_socket = "unix://$socket_path";
        break;
    default:
        echo 'Please set a `RIO_SOCKET_TYPE` env var to `AF_INET` or `AF_UNIX`';
        exit(1);
}

@unlink($socket_path);
if (!$socket = stream_socket_server($local_socket, $errNo, $errMsg)) {
    echo "Couldn't create stream_socket_server: [$errNo] $errMsg";
    exit(1);
}

echo "Fake agent started on $local_socket\n";

while (true) {
    $client = stream_socket_accept($socket, $timeout);

    if (!$client) {
        continue;
    }

    $readPart = function ($client) {
        $buffer = '';
        while (true) {
            if (stream_get_meta_data($client)['eof']) {
                break;
            }

            $char = fread($client, 1);

            if ($char === false) {
                return false;
            }

            // On timeout char is empty
            if ($char === '') {
                return false;
            }

            if ($char === "\0") {
                return $buffer;
            }

            $buffer .= $char;
        }
    };

    $cmdName = $readPart($client);

    if ($cmdName === false) {
        fclose($client);
        continue;
    }

    $cmdData = $readPart($client);

    if ($cmdData === false) {
        fclose($client);
        continue;
    }

    if ('MATCH' === $cmdName) {
        findRedirect($client, $cmdData, $matcher);
    } elseif ('LOG' === $cmdName) {
        logRedirect($client);
    } else {
        echo "Unknown command: '$cmdName'\n";
        fclose($client);

        continue;
    }

    fclose($client);
}

fclose($socket);

function findRedirect($client, $cmdData, $matcher)
{
    $req = json_decode($cmdData, true);

    $nbMatchers = count($matcher);

    $res = json_encode([
        'status_code' => 0,
        'location' => '',
    ]);

    for ($i = 0; $i < $nbMatchers; ++$i) {
        if ($matcher[$i][0] === $req['request_uri']) {
            $res = json_encode([
                'status_code' => $matcher[$i][2],
                'location' => $matcher[$i][1],
            ]);

            break;
        }
    }

    $writed = fwrite($client, $res . "\0", strlen($res) + 1);

    var_dump($writed);
}

function logRedirect($client)
{
    //fwrite($client, 1, 1);
}
