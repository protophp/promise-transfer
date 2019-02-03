<?php

namespace Proto\PromiseTransfer;

use Evenement\EventEmitter;
use Proto\Pack\PackInterface;
use Proto\Pack\Unpack;
use Proto\Pack\UnpackInterface;
use Proto\Session\SessionInterface;
use Proto\Session\SessionManagerInterface;
use Proto\PromiseTransfer\Exception\ParserException;
use Proto\PromiseTransfer\Exception\TransferException;
use Proto\PromiseTransfer\Handshake\Handshake;
use Psr\Log\LoggerAwareTrait;
use React\Socket\ConnectionInterface;

class PromiseTransfer extends EventEmitter implements PromiseTransferInterface
{
    use LoggerAwareTrait;

    /**
     * @var SessionManagerInterface
     */
    public $sessionManager;

    /**
     * @var SessionInterface
     */
    private $session;

    /**
     * @var UnpackInterface
     */
    private $unpack;

    /**
     * @var QueueInterface
     */
    private $queue;

    /**
     * @var ConnectionInterface
     */
    public $conn;

    public function __construct(ConnectionInterface $conn, SessionManagerInterface $sessionManager)
    {
        $this->conn = $conn;
        $this->sessionManager = $sessionManager;
    }

    public function init(SessionInterface $clientSession = null)
    {
        $handshake = new Handshake($this);

        // Set logger to handshake
        if (isset($this->logger))
            $handshake->setLogger($this->logger);

        if ($clientSession !== null)
            $handshake->handshake($clientSession);

        $handshake->on('established', function (SessionInterface $session, array $inProgress) {
            $this->session = $session;

            $this->initQueue($inProgress);
            $this->initUnpack();

            isset($this->logger) && $this->logger->debug('[PromiseTransfer] The transfer established successfully.');
            $this->emit('established', [$this, $session]);
        });
    }

    public function send(PackInterface $pack, callable $onResponse = null, callable $onAck = null)
    {
        list($id, $seq) = $this->queue->add($pack, $onResponse, $onAck);
        $this->conn->write(
            Parser::setDataHeader($pack, $id, $seq, is_callable($onResponse) ? true : false)->toString()
        );
        isset($this->logger) && $this->logger->debug("[PromiseTransfer] The Pack#$id.$seq.0 is sent.");
    }

    public function response(PackInterface $pack, int $targetPackId, callable $onAck = null)
    {
        list($id, $seq) = $this->queue->add($pack, null, $onAck);
        $this->conn->write(Parser::setResponseHeader($pack, $id, $seq, $targetPackId)->toString());
        isset($this->logger) && $this->logger->debug("[PromiseTransfer] The Pack#$id.$seq.$targetPackId is sent.");
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
            isset($this->logger) && $this->logger->critical("[PromiseTransfer/Parser] " . $e->getMsg());
            throw new TransferException(TransferException::PARSING_ERROR);
        }

        // Is incoming ACK?
        if ($parser->isAck()) {
            isset($this->logger) && $this->logger->debug("[PromiseTransfer] The ACK#{$parser->getId()} is received.");
            $this->queue->ack($parser->getId());
            return;
        }

        isset($this->logger) && $this->logger->debug("[PromiseTransfer] The Pack#{$parser->getId()}.{$parser->getSeq()} is received.");

        // Send ACK
        isset($this->logger) && $this->logger->debug("[PromiseTransfer] The ACK#{$parser->getId()} is sent.");
        $this->session->set('IN-PROGRESS', [$parser->getId(), $parser->getSeq(), true]);
        $this->conn->write($parser->setAckHeader()->toString());

        // Is response?
        if ($parser->isResponse()) {
            isset($this->logger) && $this->logger->debug("[PromiseTransfer] The Response#{$parser->getResponseId()} is received.");
            $this->queue->response($parser->getResponseId(), $pack);
            return;
        }

        // Emit data
        $this->emit('data', [$pack, $parser]);
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
            isset($this->logger) && $this->logger->critical("[PromiseTransfer/Parser] " . $e->getMsg());
            throw new TransferException(TransferException::PARSING_ERROR);
        }

        // skip on ack
        if ($parser->isAck())
            return;

        isset($this->logger) && $this->logger->debug("[PromiseTransfer] The Pack#{$parser->getId()}.{$parser->getSeq()} is added to progressing.");
        $this->session->set('IN-PROGRESS', $pack);
    }

    /**
     * Initial queue
     * @param array $inProgress [ID, Seq, Progress]
     */
    private function initQueue(array $inProgress)
    {
        if (!$this->session->is('TRANSFER-QUEUE'))
            $this->session->set('TRANSFER-QUEUE', new Queue());

        $this->queue = $this->session->get('TRANSFER-QUEUE');

        // Correction queue
        $buffer = $this->queue->correction($inProgress);

        if ($buffer)
            $this->conn->write($buffer);
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

        // The merging buffer should be cleared if it isn't reached to pack's header.
        if ($this->session->is('IN-PROGRESS')) {
            if (!$this->session->get('IN-PROGRESS') instanceof PackInterface)
                $this->unpack->clear();
        } else
            $this->unpack->clear();

        $this->unpack->on('unpack', [$this, 'income']);
        $this->unpack->on('unpack-header', [$this, 'merging']);

        $this->conn->on('data', [$this->unpack, 'feed']);
    }
}