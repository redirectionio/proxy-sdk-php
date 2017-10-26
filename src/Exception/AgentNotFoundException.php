<?php

namespace RedirectionIO\Client\Exception;

class AgentNotFoundException extends \RuntimeException
{
    protected $message = 'Agent not found';
    protected $code = 0;

    public function __construct()
    {
        parent::__construct($this->message, $this->code);
    }

    public function __toString()
    {
        return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
    }
}
