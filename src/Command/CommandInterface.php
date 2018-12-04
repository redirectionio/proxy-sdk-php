<?php

namespace RedirectionIO\Client\Sdk\Command;

interface CommandInterface
{
    public function getName();

    public function getRequest();

    public function hasResponse();

    public function parseResponse($response);
}
