<?php

namespace Proto\Socket\Transfer\Handshake;

use Evenement\EventEmitterInterface;
use Proto\Session\SessionInterface;
use Proto\Session\SessionManagerInterface;
use Proto\Socket\Transfer\TransferInterface;
use Psr\Log\LoggerAwareInterface;

interface HandshakeInterface extends EventEmitterInterface, LoggerAwareInterface
{
    public function __construct(TransferInterface $transfer, SessionManagerInterface $sessionManager);

    public function handshake(SessionInterface $clientSession);
}