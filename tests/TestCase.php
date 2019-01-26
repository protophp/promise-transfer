<?php

namespace Proto\PromiseTransfer\Tests;

use Proto\Session\Exception\SessionException;
use Proto\Session\SessionInterface;
use Proto\Session\SessionManager;
use Proto\Session\SessionManagerInterface;

class TestCase extends \PHPUnit\Framework\TestCase
{
    /**
     * @var SessionManagerInterface
     */
    protected $sessionManager;

    /**
     * @var SessionInterface
     */
    protected $clientSession;

    /**
     * SessionHandshakeTest constructor.
     * @param string|null $name
     * @param array $data
     * @param string $dataName
     * @throws SessionException
     */
    public function __construct(string $name = null, array $data = [], string $dataName = '')
    {
        parent::__construct($name, $data, $dataName);

        $this->sessionManager = new SessionManager();
        $this->clientSession = $this->sessionManager->start();
    }
}