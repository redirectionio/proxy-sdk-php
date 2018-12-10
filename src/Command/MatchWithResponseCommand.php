<?php

namespace RedirectionIO\Client\Sdk\Command;

use RedirectionIO\Client\Sdk\HttpMessage\Request;
use RedirectionIO\Client\Sdk\HttpMessage\Response;

/**
 * Find matching rule for a specific request.
 */
class MatchWithResponseCommand implements CommandInterface
{
    private $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function getName()
    {
        return 'MATCH_WITH_RESPONSE';
    }

    public function getRequest()
    {
        return json_encode([
            'host' => $this->request->getHost(),
            'request_uri' => $this->request->getPath(),
            'user_agent' => $this->request->getUserAgent(),
            'referer' => $this->request->getReferer(),
            'scheme' => $this->request->getScheme(),
            'use_json' => true,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function hasResponse()
    {
        return true;
    }

    public function parseResponse($response)
    {
        $json = json_decode($response, true);

        if (null === $json) {
            throw new \ErrorException(sprintf('Impossible to decode the JSON (%s). Content: "%s"', json_last_error_msg(), $response));
        }

        $ruleId = null;
        $location = null;
        $matchOnResponseStatus = 0;

        if (isset($json['matched_rule'], $json['matched_rule']['id'])) {
            $ruleId = $json['matched_rule']['id'];
        }

        if (isset($json['location'])) {
            $location = $json['location'];
        }

        if (isset($json['match_on_response_status'])) {
            $matchOnResponseStatus = $json['match_on_response_status'];
        }

        if (0 === (int) $json['status_code']) {
            return null;
        }

        return new Response((int) $json['status_code'], $ruleId, $location, $matchOnResponseStatus);
    }
}
