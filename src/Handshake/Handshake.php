<?php

namespace Proto\PromiseTransfer\Handshake;

use Evenement\EventEmitter;
use Proto\Pack\PackInterface;
use Proto\Pack\Unpack;
use Proto\Session\Exception\SessionException;
use Proto\Session\SessionInterface;
use Proto\PromiseTransfer\PromiseTransfer;
use Proto\PromiseTransfer\PromiseTransferInterface;
use Psr\Log\LoggerAwareTrait;

class Handshake extends EventEmitter implements HandshakeInterface
{
    use LoggerAwareTrait;

    /**
     * @var PromiseTransferInterface
     */
    private $transfer;

    /**
     * @var Unpack
     */
    private $unpack;

    /**
     * @var SessionInterface
     */
    private $clientSession;

    public function __construct(PromiseTransfer $transfer)
    {
        $this->transfer = $transfer;
        $this->unpack = new Unpack();

        $this->transfer->conn->on('data', [$this->unpack, 'feed']);
        $this->unpack->on('unpack', [$this, 'unpack']);
    }

    public function handshake(SessionInterface $clientSession)
    {
        $this->clientSession = $clientSession;

        if (!$clientSession->is('SERVER-SESSION-KEY')) {
            isset($this->logger) && $this->logger->debug('[PromiseTransfer/Handshake] The new request sent.');
            $this->transfer->conn->write((new Parser())->doRequest());

        } else {
            $serverSessionKey = $clientSession->get('SERVER-SESSION-KEY');

            isset($this->logger) && $this->logger->debug('[PromiseTransfer/Handshake] The recovery request sent. SessionKey: ' . $serverSessionKey);
            $this->transfer->conn->write((new Parser())->doRequest(
                $serverSessionKey,
                $clientSession->get('LAST-PROGRESS')
            ));
        }
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

                    isset($this->logger) && $this->logger->debug('[PromiseTransfer/Handshake] The incoming recovery request received. SessionKey: ' . $serverSessionKey);
                    $session = $this->sessionStart($parser, $serverSessionKey);
                    if ($session === false) {
                        isset($this->logger) && $this->logger->critical('[PromiseTransfer/Handshake] Unable to recover session!');
                        $this->emit('error');
                        return;
                    }

                    $this->established($parser, $session);
                    return;
                }

                // New session
                isset($this->logger) && $this->logger->debug('[PromiseTransfer/Handshake] The incoming new request received.');
                $session = $this->sessionStart($parser);
                if ($session === false) {
                    isset($this->logger) && $this->logger->critical('[PromiseTransfer/Handshake] Unable to start new session!');
                    $this->emit('error');
                    return;
                }

                $this->established($parser, $session);
            });
        }

        // On established (client side)
        $parser->onEstablished(function () use ($parser) {
            isset($this->logger) && $this->logger->debug('[PromiseTransfer/Handshake] The session is establishing...');

            if (!$this->clientSession->is('SERVER-SESSION-KEY'))
                $this->clientSession->set('SERVER-SESSION-KEY', $parser->getServerSessionKey());

            $this->transfer->conn->removeAllListeners('data');
            $this->emit('established', [$this->clientSession, $parser->getLastProgress()]);
            isset($this->logger) && $this->logger->info('[PromiseTransfer/Handshake] The session established.');
        });

        // On Error
        $parser->onError(function () {
            isset($this->logger) && $this->logger->critical("[PromiseTransfer/Handshake] Something wrong with the remote host!");
            $this->emit('error');
        });

        // Parse...
        $parser->parse();
    }

    private function established(ParserInterface $parser, SessionInterface $session)
    {
        isset($this->logger) && $this->logger->debug('[PromiseTransfer/Handshake] The establishing message is sent to client. SessionKey: '.$session->getKey());
        $this->transfer->conn->write(
            $parser->doEstablished(
                $session->getKey(),
                $session->get('LAST-PROGRESS')
            )
        );
        $this->transfer->conn->removeAllListeners('data');
        $this->emit('established', [$session, $parser->getLastProgress()]);
        isset($this->logger) && $this->logger->info('[PromiseTransfer/Handshake] The session established.');
    }

    private function sessionStart(ParserInterface $parser, $serverSessionKey = null)
    {
        try {
            $session = $this->transfer->sessionManager->start($serverSessionKey);
        } catch (SessionException $e) {

            switch ($e->getCode()) {

                case SessionException::ERR_INVALID_SESSION_KEY:
                    isset($this->logger) && $this->logger->critical("[PromiseTransfer/Handshake] Invalid session's key!");
                    $this->transfer->conn->write($parser->doError(Parser::ERR_INVALID_SESSION_KEY));
                    break;

                default:
                    if (isset($this->logger)) {
                        if (isset($serverSessionKey))
                            $this->logger->critical("[PromiseTransfer/Handshake] Something wrong in recover session!");
                        else
                            $this->logger->critical("[PromiseTransfer/Handshake] Something wrong in generate new session!");
                    }
                    $this->transfer->conn->write($parser->doError(Parser::ERR_SOMETHING_WRONG));
            }
            return false;
        }

        return $session;
    }
}