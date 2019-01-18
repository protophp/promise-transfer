<?php

namespace Proto\Socket\Transmission;

use Evenement\EventEmitterInterface;
use Proto\Pack\PackInterface;
use Proto\Session\SessionInterface;
use Psr\Log\LoggerAwareInterface;
use React\Socket\ConnectionInterface;

interface TransferInterface extends LoggerAwareInterface, EventEmitterInterface
{
    const TYPE_ACK = 0;
    const TYPE_DATA = 1;

    public function __construct(ConnectionInterface $conn, SessionInterface $session, $lastAck, $lastMerging);

    public function send(PackInterface $pack, callable $onAck = null);
}