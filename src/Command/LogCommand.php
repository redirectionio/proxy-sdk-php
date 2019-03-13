<?php

namespace RedirectionIO\Client\Sdk\Command;

use RedirectionIO\Client\Sdk\Client;
use RedirectionIO\Client\Sdk\HttpMessage\Request;
use RedirectionIO\Client\Sdk\HttpMessage\Response;

/**
 * Log a request and a response to be used in analysis on redirection io manager.
 */
class LogCommand extends Command
{
    private $request;
    private $response;

    public function __construct(Request $request, Response $response)
    {
        $this->request = $request;
        $this->response = $response;
    }

    public function getName()
    {
        return 'LOG';
    }

    public function getRequest()
    {
        $data = [
            'project_id' => $this->projectKey,
            'status_code' => $this->response->getStatusCode(),
            'host' => $this->request->getHost(),
            'request_uri' => $this->request->getPath(),
            'method' => $this->request->getMethod(),
            'user_agent' => $this->request->getUserAgent(),
            'referer' => $this->request->getReferer(),
            'scheme' => $this->request->getScheme(),
            'proxy' => 'php-sdk-redirectionio:'.Client::VERSION,
            'use_json' => true,
        ];

        if ($this->response->getLocation()) {
            $data['target'] = $this->response->getLocation();
        }

        if ($this->response->getRuleId()) {
            $data['rule_id'] = $this->response->getRuleId();
        }

        return json_encode($data);
    }

    public function hasResponse()
    {
        return false;
    }

    public function parseResponse($response)
    {
        return null;
    }
}
