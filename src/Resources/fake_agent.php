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

$projectKey = 'szio2389-bfdz-51e8-8468-02dcop129501:ep6a4805-eo6z-dzo6-aeb0-8c1lbmo40242';
$socketType = isset($_SERVER['RIO_SOCKET_TYPE']) ? $_SERVER['RIO_SOCKET_TYPE'] : 'AF_INET';
$socketPath = isset($_SERVER['RIO_SOCKET_PATH']) ? $_SERVER['RIO_SOCKET_PATH'] : sys_get_temp_dir().'/fake_agent.sock';
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

switch ($socketType) {
    case 'AF_INET':
        $local_socket = "tcp://$ip:$port";
        break;
    case 'AF_UNIX':
        $local_socket = "unix://$socketPath";
        break;
    default:
        echo 'Please set a `RIO_SOCKET_TYPE` env var to `AF_INET` or `AF_UNIX`';
        exit(1);
}

@unlink($socketPath);
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
    };

    while (true) {
        $cmdName = $readPart($client);

        if (false === $cmdName) {
            fclose($client);
            break;
        }

        $cmdData = $readPart($client);

        if (false === $cmdData) {
            fclose($client);
            break;
        }

        if ('MATCH' === $cmdName) {
            findRedirect($client, $cmdData, $matcher, $projectKey);
        } elseif ('LOG' === $cmdName) {
            logRedirect($client);
        } else {
            echo "Unknown command: '$cmdName'\n";
            fclose($client);

            break;
        }
    }

    fclose($client);
}

fclose($socket);

function findRedirect($client, $cmdData, $matcher, $projectKey)
{
    $req = json_decode($cmdData, true);

    $nbMatchers = count($matcher);

    $res = json_encode([
        'status_code' => 0,
        'location' => '',
    ]);

    for ($i = 0; $i < $nbMatchers; ++$i) {
        if ($matcher[$i][0] === $req['request_uri'] && $projectKey === $req['project_id']) {
            $res = json_encode([
                'status_code' => $matcher[$i][2],
                'location' => $matcher[$i][1],
            ]);

            break;
        }
    }

    $writed = fwrite($client, $res."\0", strlen($res) + 1);

    var_dump($writed);
}

function logRedirect($client)
{
}
