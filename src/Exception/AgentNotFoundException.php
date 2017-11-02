<?php

namespace RedirectionIO\Client\Exception;

class AgentNotFoundException extends \RuntimeException implements ExceptionInterface
{
    public function __construct()
    {
        parent::__construct('Agent not found.');
    }
}
