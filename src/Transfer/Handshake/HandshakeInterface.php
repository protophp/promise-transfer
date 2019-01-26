<?php

namespace Proto\Socket\Transfer\Handshake;

use Evenement\EventEmitterInterface;
use Proto\Session\SessionInterface;
use Proto\Session\SessionManagerInterface;
use Proto\Socket\Transfer\Transfer;
use Psr\Log\LoggerAwareInterface;

interface HandshakeInterface extends EventEmitterInterface, LoggerAwareInterface
{
    public function __construct(Transfer $transfer, SessionManagerInterface $sessionManager);

    public function handshake(SessionInterface $clientSession);
}