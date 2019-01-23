<?php

namespace Proto\Socket\Handshake;

use Evenement\EventEmitterInterface;
use Proto\Session\SessionInterface;
use Proto\Session\SessionManagerInterface;
use Psr\Log\LoggerAwareInterface;
use React\Socket\ConnectionInterface;

interface HandshakeInterface extends EventEmitterInterface, LoggerAwareInterface
{
    public function __construct(ConnectionInterface $conn, SessionManagerInterface $sessionManager);

    public function handshake(SessionInterface $clientSession);
}