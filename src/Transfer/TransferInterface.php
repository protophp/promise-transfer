<?php

namespace Proto\Socket\Transfer;

use Evenement\EventEmitterInterface;
use Proto\Pack\PackInterface;
use Proto\Session\SessionInterface;
use Psr\Log\LoggerAwareInterface;

/**
 * Transfer interface
 *
 * Setup to socket:
 *  1. Use the 'onWrite' event to write the transfer's packs to the socket.
 *     Example for the ReactPHP:
 *     $transfer->on('onWrite', function($data) use (ConnectionInterface $conn){
 *         $conn->write($data);
 *     });
 *
 *  2. Use the 'onIncome' event to send the incoming chunk from the socket to the transfer.
 *     Example:
 *     $transfer->emit('onIncome', [$chunk]);
 *
 * @package Proto\Socket\Transfer
 */
interface TransferInterface extends LoggerAwareInterface, EventEmitterInterface
{
    const TYPE_ACK = 0;
    const TYPE_DATA = 1;

    public function __construct(SessionInterface $session);

    public function send(PackInterface $pack, callable $onAck = null);
}