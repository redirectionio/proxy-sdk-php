<?php

namespace RedirectionIO\Client\Sdk\Command;

abstract class Command implements CommandInterface
{
    protected $projectKey;

    abstract public function getName();

    abstract public function getRequest();

    abstract public function hasResponse();

    abstract public function parseResponse($response);

    public function setProjectKey(string $projectKey)
    {
        $this->projectKey = $projectKey;
    }
}
