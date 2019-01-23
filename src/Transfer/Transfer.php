<?php

namespace Proto\Socket\Transfer;

use Evenement\EventEmitter;
use Proto\Pack\Pack;
use Proto\Pack\PackInterface;
use Proto\Pack\Unpack;
use Proto\Pack\UnpackInterface;
use Proto\Session\SessionInterface;
use Psr\Log\LoggerAwareTrait;
use React\Socket\ConnectionInterface;

class Transfer extends EventEmitter implements TransferInterface
{
    use LoggerAwareTrait;

    /**
     * @var SessionInterface
     */
    private $session;

    /**
     * @var ConnectionInterface
     */
    private $conn;

    /**
     * @var UnpackInterface
     */
    private $unpack;

    /**
     * @var TransferQueueInterface
     */
    private $queue;

    public function __construct(ConnectionInterface $conn, SessionInterface $session, $lastAck, $lastMerging)
    {
        $this->conn = $conn;
        $this->session = $session;

        $this->initQueue();
        $this->initUnpack();
    }

    public function send(PackInterface $pack, callable $onAck = null)
    {
        list($id, $seq) = $this->queue->add($pack, $onAck);
        $pack->setHeaderByKey(0, [self::TYPE_DATA, $id, $seq]);
        $this->conn->write($pack->toString());
    }

    public function income(PackInterface $pack)
    {
        $info = $pack->getHeaderByKey(0);

        // Is incoming ACK?
        if ($info[0] === self::TYPE_ACK) {
            $this->queue->ack($info[1]);
            return;
        }

        // Emit data
        $this->emit('data', [$pack]);

        // Send ACK
        $this->session->set('LAST-ACK', [$info[1], $info[2]]);
        $this->conn->write((new Pack())->setHeaderByKey(0, [self::TYPE_ACK, $info[1], $info[2]])->toString());
    }

    public function merging(PackInterface $pack)
    {
        $info = $pack->getHeaderByKey(0);

        // skip on ack
        if ($info[0] === self::TYPE_ACK)
            return;

        $this->session->set('LAST-MERGING', [$info[1], $info[2]]);
    }

    /**
     * Initial queue
     */
    private function initQueue()
    {
        if (!$this->session->is('TRANSFER-QUEUE'))
            $this->session->set('TRANSFER-QUEUE', new TransferQueue());

        $this->queue = $this->session->get('TRANSFER-QUEUE');
    }

    /**
     * Initial unpack
     */
    private function initUnpack()
    {
        if (!$this->session->is('UNPACK'))
            $this->session->set('UNPACK', new Unpack());

        $this->unpack = $this->session->get('UNPACK');
        $this->unpack->removeAllListeners('unpack');
        $this->unpack->removeAllListeners('header');

        $this->unpack->on('unpack', [$this, 'income']);
        $this->unpack->on('header', [$this, 'merging']);

        $this->conn->on('data', [$this->unpack, 'feed']);
    }
}