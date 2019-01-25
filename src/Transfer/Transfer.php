<?php

namespace Proto\Socket\Transfer;

use Evenement\EventEmitter;
use Proto\Pack\PackInterface;
use Proto\Pack\Unpack;
use Proto\Pack\UnpackInterface;
use Proto\Session\SessionInterface;
use Proto\Socket\Transfer\Exception\ParserException;
use Proto\Socket\Transfer\Exception\TransferException;
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
        $this->conn->write(Parser::setDataHeader($pack, $id, $seq)->toString());
    }

    /**
     * Process completed packs
     * @param PackInterface $pack
     * @throws TransferException
     */
    public function income(PackInterface $pack)
    {
        try {
            $parser = new Parser($pack);
        } catch (ParserException $e) {
            isset($this->logger) && $this->logger->critical("[Parser]: " . $e->getMsg());
            throw new TransferException(TransferException::PARSING_ERROR);
        }

        // Is incoming ACK?
        if ($parser->isAck()) {
            $this->queue->ack($parser->getId());
            return;
        }

        // Emit data
        $this->emit('data', [$pack]);

        // Send ACK
        $this->session->set('LAST-ACK', [$parser->getId(), $parser->getSeq()]);
        $this->conn->write($parser->setAckHeader()->toString());
    }

    /**
     * Mark incoming pack as merging
     * @param PackInterface $pack
     * @throws TransferException
     */
    public function merging(PackInterface $pack)
    {
        try {
            $parser = new Parser($pack);
        } catch (ParserException $e) {
            isset($this->logger) && $this->logger->critical("[Parser]: " . $e->getMsg());
            throw new TransferException(TransferException::PARSING_ERROR);
        }

        // skip on ack
        if ($parser->isAck())
            return;

        $this->session->set('LAST-MERGING', [$parser->getId(), $parser->getSeq()]);
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
        $this->unpack->removeAllListeners('unpack-header');

        $this->unpack->on('unpack', [$this, 'income']);
        $this->unpack->on('unpack-header', [$this, 'merging']);

        $this->conn->on('data', [$this->unpack, 'feed']);
    }
}