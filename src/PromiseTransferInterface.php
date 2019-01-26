<?php

namespace Proto\PromiseTransfer;

use Evenement\EventEmitterInterface;
use Proto\Pack\PackInterface;
use Proto\Session\SessionManagerInterface;
use Psr\Log\LoggerAwareInterface;
use React\Socket\ConnectionInterface;

interface PromiseTransferInterface extends LoggerAwareInterface, EventEmitterInterface
{
    public function __construct(ConnectionInterface $conn, SessionManagerInterface $sessionManager);

    public function send(PackInterface $pack, callable $onResponse = null, callable $onAck = null);
}