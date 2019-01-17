<?php

namespace Proto\Socket\Transmission;

use Evenement\EventEmitter;
use Proto\Pack\Pack;
use Proto\Pack\PackInterface;
use Proto\Pack\Unpack;
use Proto\Session\Exception\SessionException;
use Proto\Session\SessionInterface;
use Proto\Session\SessionManagerInterface;
use Psr\Log\LoggerAwareTrait;
use React\Socket\ConnectionInterface;

class SessionHandshake extends EventEmitter implements SessionHandshakeInterface
{
    use LoggerAwareTrait;

    /**
     * @var ConnectionInterface
     */
    private $conn;

    /**
     * @var Unpack
     */
    private $unpack;

    /**
     * @var SessionManagerInterface
     */
    private $sessionManager;

    /**
     * @var SessionInterface
     */
    private $clientSession;

    public function __construct(ConnectionInterface $conn, SessionManagerInterface $sessionManager)
    {
        $this->conn = $conn;
        $this->unpack = new Unpack();
        $this->sessionManager = $sessionManager;

        $this->conn->on('data', [$this->unpack, 'feed']);
        $this->unpack->on('unpack', [$this, 'unpack']);
    }

    public function handshake(SessionInterface $clientSession)
    {
        $this->clientSession = $clientSession;

        if (!$this->clientSession->is('SERVER-SESSION-KEY')) {
            $this->conn->write((new Pack())->setHeader([self::ACTION_REQUEST])->toString());
            return;
        }

        $this->conn->write(
            (new Pack())
                ->setHeader([
                    self::ACTION_REQUEST,
                    $this->clientSession->get('SERVER-SESSION-KEY'),
                    $this->clientSession->get('LAST-ACK'),
                    $this->clientSession->get('LAST-MERGING')
                ])
                ->toString()
        );
    }

    public function unpack(PackInterface $pack)
    {
        $action = $pack->getHeaderByKey(0);
        $serverSessionKey = $pack->getHeaderByKey(1);
        $lastAck = $pack->getHeaderByKey(2);
        $lastMerging = $pack->getHeaderByKey(3);
        switch ($action) {

            // Server side
            case self::ACTION_REQUEST:

                // Recover Session
                if (isset($serverSessionKey)) {
                    try {
                        $session = $this->sessionManager->start($serverSessionKey);
                    } catch (SessionException $e) {
                        switch ($e->getCode()) {
                            case SessionException::ERR_INVALID_SESSION_KEY:
                                isset($this->logger) && $this->logger->critical("[Handshake]: Invalid session's key! key: '$serverSessionKey'");
                                $this->conn->write((new Pack)->setHeader([self::ACTION_ERROR])->toString());
                                break;

                            default:
                                isset($this->logger) && $this->logger->critical("[Handshake]: Something wrong in recover session! key: '$serverSessionKey'");
                                $this->conn->write((new Pack)->setHeader([self::ACTION_ERROR])->toString());
                        }
                        $this->emit('error');
                        return;
                    }

                    $this->conn->write(
                        (new Pack)
                            ->setHeader([
                                self::ACTION_ESTABLISHED,
                                null,
                                $session->get('LAST-ACK'),
                                $session->get('LAST-MERGING')
                            ])
                            ->toString());

                    $this->conn->removeAllListeners('data');
                    $this->emit('established', [$session, $lastAck, $lastMerging]);
                    return;
                }

                // New Session
                try {
                    $session = $this->sessionManager->start();
                } catch (SessionException $e) {
                    isset($this->logger) && $this->logger->critical("[Handshake]: Something wrong in generate new session!");
                    $this->conn->write((new Pack)->setHeader([self::ACTION_ERROR])->toString());
                    $this->emit('error');
                    return;
                }

                $this->conn->write(
                    (new Pack)
                        ->setHeader([
                            self::ACTION_ESTABLISHED,
                            $session->getKey(),
                            null,
                            null
                        ])
                        ->toString());

                $this->conn->removeAllListeners('data');
                $this->emit('established', [$session, null, null]);
                return;

            // Client side
            case self::ACTION_ESTABLISHED:

                if (!$this->clientSession->is('SERVER-SESSION-KEY'))
                    $this->clientSession->set('SERVER-SESSION-KEY', $serverSessionKey);

                $this->conn->removeAllListeners('data');
                $this->emit('established', [$this->clientSession, $lastAck, $lastMerging]);
                return;

            case self::ACTION_ERROR:
                isset($this->logger) && $this->logger->critical("[Handshake] Handshake failed from remote host!");
                $this->emit('error');
                return;
        }
    }
}