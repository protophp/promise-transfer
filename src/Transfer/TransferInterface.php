<?php

namespace Proto\Socket\Transfer;

use Evenement\EventEmitterInterface;
use Proto\Pack\PackInterface;
use Proto\Session\SessionManagerInterface;
use Psr\Log\LoggerAwareInterface;
use React\Socket\ConnectionInterface;

interface TransferInterface extends LoggerAwareInterface, EventEmitterInterface
{
    const TYPE_ACK = 0;
    const TYPE_DATA = 1;

    public function __construct(ConnectionInterface $conn, SessionManagerInterface $sessionManager);

    public function send(PackInterface $pack, callable $onResponse = null, callable $onAck = null);
}