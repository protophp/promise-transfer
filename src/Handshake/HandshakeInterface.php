<?php

namespace Proto\PromiseTransfer\Handshake;

use Evenement\EventEmitterInterface;
use Proto\Session\SessionInterface;
use Proto\PromiseTransfer\PromiseTransfer;
use Psr\Log\LoggerAwareInterface;

interface HandshakeInterface extends EventEmitterInterface, LoggerAwareInterface
{
    public function __construct(PromiseTransfer $transfer);

    public function handshake(SessionInterface $clientSession);
}