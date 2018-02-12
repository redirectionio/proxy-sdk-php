<?php

/**
 * Examples of payload.
 *
 * GET.
 * Request: GET {"host": "host1.com", "request_uri": "foo", "user_agent": "redirection-io-client/0.0.1", "referer": "http://host0.com"}
 * Response: 301|http://host1.com/bar
 *
 * LOG.
 * Request: LOG {"status_code": 301, "host": "host1.com", "request_uri": "foo", "user_agent": "redirection-io-client/0.0.1", "referer": "http://host0.com"}
 * Response: ok
 */
set_time_limit(0);

$socket_type = isset($_SERVER['RIO_SOCKET_TYPE']) ? $_SERVER['RIO_SOCKET_TYPE'] : 'AF_INET';
$socket_path = isset($_SERVER['RIO_SOCKET_PATH']) ? $_SERVER['RIO_SOCKET_PATH'] : sys_get_temp_dir() . '/fake_agent.sock';
$ip = isset($_SERVER['RIO_HOST']) ? $_SERVER['RIO_HOST'] : 'localhost';
$port = isset($_SERVER['RIO_PORT']) ? $_SERVER['RIO_PORT'] : 3100;
$timeout = 1000000; // seconds
$matcher = [
    ['foo', 'bar', 301],
    ['baz', 'qux', 302],
    ['quux', 'corge', 307],
    ['uier', 'grault', 308],
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

    while (true) {
        if (stream_get_meta_data($client)['eof']) {
            break;
        }
        if (!$req = fgets($client)) {
            continue;
        }

        $req = rtrim(trim($req), "\n");

        $cmd = substr($req, 0, strpos($req, ' '));

        if ($cmd === 'GET') {
            findRedirect($client, $req, $matcher);
        } elseif ($cmd === 'LOG') {
            logRedirect($client, $req);
        } else {
            echo "Unknown command: '$cmd'\n";

            continue;
        }
    }

    fclose($client);
}

fclose($socket);

function findRedirect($client, $req, $matcher)
{
    $req = json_decode(ltrim($req, 'GET '), true);

    $found = false;

    for ($i = 0; $i < count($matcher); ++$i) {
        if ($matcher[$i][0] === $req['request_uri']) {
            $res = "{$matcher[$i][2]}|http://{$req['host']}/{$matcher[$i][1]}";
            $res = preg_replace('/\s+/', '', $res);
            fwrite($client, $res, strlen($res));
            $found = true;
            break;
        }
    }

    if (!$found) {
        $res = ' ';
        fwrite($client, $res, strlen($res));
    }
}

function logRedirect($client, $req)
{
    $req = json_decode(ltrim($req, 'LOG '), true);
    fwrite($client, 1, 1);
}
