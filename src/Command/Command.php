<?php

namespace RedirectionIO\Client\Sdk\Command;

abstract class Command implements CommandInterface
{
    protected $projectKey;

    public function setProjectKey(string $projectKey)
    {
        $this->projectKey = $projectKey;
    }
}
