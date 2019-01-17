<?php

namespace Proto\Socket\Transmission;

use Evenement\EventEmitterInterface;
use Proto\Session\SessionInterface;
use Proto\Session\SessionManagerInterface;
use Psr\Log\LoggerAwareInterface;
use React\Socket\ConnectionInterface;

interface SessionHandshakeInterface extends EventEmitterInterface, LoggerAwareInterface
{
    const ACTION_REQUEST = 0;
    const ACTION_ESTABLISHED = 1;
    const ACTION_ERROR = 2;

    public function __construct(ConnectionInterface $conn, SessionManagerInterface $sessionManager);

    public function handshake(SessionInterface $clientSession);
}