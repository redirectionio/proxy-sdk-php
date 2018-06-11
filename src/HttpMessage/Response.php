<?php

namespace RedirectionIO\Client\Sdk\HttpMessage;

class Response
{
    private $statusCode;
    private $ruleId;

    /**
     * @param int    $statusCode
     * @param string $ruleId
     */
    public function __construct($statusCode = 200, $ruleId = null)
    {
        $this->statusCode = $statusCode;
        $this->ruleId = $ruleId;
    }

    public function getStatusCode()
    {
        return $this->statusCode;
    }

    public function getRuleId()
    {
        return $this->ruleId;
    }
}
