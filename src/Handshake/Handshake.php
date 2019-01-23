<?php

namespace Proto\Socket\Handshake;

use Evenement\EventEmitter;
use Proto\Pack\PackInterface;
use Proto\Pack\Unpack;
use Proto\Session\Exception\SessionException;
use Proto\Session\SessionInterface;
use Proto\Session\SessionManagerInterface;
use Psr\Log\LoggerAwareTrait;
use React\Socket\ConnectionInterface;

class Handshake extends EventEmitter implements HandshakeInterface
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

        if (!$clientSession->is('SERVER-SESSION-KEY'))
            $this->conn->write((new Parser())->doRequest());
        else
            $this->conn->write((new Parser())->doRequest(
                $clientSession->get('SERVER-SESSION-KEY'),
                $clientSession->get('LAST-ACK'),
                $clientSession->get('LAST-MERGING')
            ));
    }

    public function unpack(PackInterface $pack)
    {
        $parser = new Parser($pack);

        // On request (server side)
        if (!isset($this->clientSession)) {
            $parser->onRequest(function () use ($parser) {

                // Session recovery
                if ($parser->isServerSessionKey()) {
                    $serverSessionKey = $parser->getServerSessionKey();

                    $session = $this->sessionStart($parser, $serverSessionKey);
                    if ($session === false) {
                        $this->emit('error');
                        return;
                    }

                    $this->established($parser, $session);
                    return;
                }

                // New session
                $session = $this->sessionStart($parser);
                if ($session === false) {
                    $this->emit('error');
                    return;
                }

                $this->established($parser, $session);
            });
        }

        // On established (client side)
        $parser->onEstablished(function () use ($parser) {
            if (!$this->clientSession->is('SERVER-SESSION-KEY'))
                $this->clientSession->set('SERVER-SESSION-KEY', $parser->getServerSessionKey());

            $this->conn->removeAllListeners('data');
            $this->emit('established', [$this->clientSession, $parser->getLastAck(), $parser->getLastMerging()]);
        });

        // On Error
        $parser->onError(function () {
            isset($this->logger) && $this->logger->critical("[Handshake] Handshake failed from remote host!");
            $this->emit('error');
        });

        // Parse...
        $parser->parse();
    }

    private function established(ParserInterface $parser, SessionInterface $session)
    {
        $this->conn->write(
            $parser->doEstablished(
                $session->getKey(),
                $session->get('LAST-ACK'),
                $session->get('LAST-MERGING')
            )
        );
        $this->conn->removeAllListeners('data');
        $this->emit('established', [$session, $parser->getLastAck(), $parser->getLastMerging()]);
    }

    private function sessionStart(ParserInterface $parser, $serverSessionKey = null)
    {
        try {
            $session = $this->sessionManager->start($serverSessionKey);
        } catch (SessionException $e) {

            switch ($e->getCode()) {

                case SessionException::ERR_INVALID_SESSION_KEY:
                    isset($this->logger) && $this->logger->critical("[Handshake]: Invalid session's key! key: '$serverSessionKey'");
                    $this->conn->write($parser->doError(Parser::ERR_INVALID_SESSION_KEY));
                    break;

                default:
                    if (isset($this->logger)) {
                        if (isset($serverSessionKey))
                            $this->logger->critical("[Handshake]: Something wrong in recover session! key: '$serverSessionKey'");
                        else
                            $this->logger->critical("[Handshake]: Something wrong in generate new session!");
                    }
                    $this->conn->write($parser->doError(Parser::ERR_SOMETHING_WRONG));
            }
            return false;
        }

        return $session;
    }
}