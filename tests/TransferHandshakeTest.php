<?php

namespace Proto\Socket\Tests;

use Proto\Session\SessionInterface;
use Proto\Socket\Transfer\Handshake\HandshakeInterface;
use Proto\Socket\Transfer\TransferInterface;

class TransferHandshakeTest extends TestCase
{
    /**
     * @var SessionInterface
     */
    private $serverSession;

    public function testNoneKey()
    {
        /** @var TransferInterface $sTransfer */
        /** @var TransferInterface $cTransfer */
        list($sTransfer, $cTransfer) = $this->connSetup();

        /** @var HandshakeInterface $sHandshake */
        /** @var HandshakeInterface $cHandshake */
        list($sHandshake, $cHandshake) = $this->handshakeSetup($sTransfer, $cTransfer);

        $sHandshake->on('established', function (SessionInterface $serverSession) {
            $this->serverSession = $serverSession;
        });

        $cHandshake->on('established', function (SessionInterface $clientSession) {
            $this->assertSame($this->clientSession, $clientSession);
            $this->assertSame($this->clientSession->get('SERVER-SESSION-KEY'), $this->serverSession->getKey());
            $this->loop->stop();
        });

        $cHandshake->handshake($this->clientSession);

        $this->loop->run();
    }

    public function testWithKey()
    {
        $this->testNoneKey();

        /** @var TransferInterface $sTransfer */
        /** @var TransferInterface $cTransfer */
        list($sTransfer, $cTransfer) = $this->connSetup();

        /** @var HandshakeInterface $sHandshake */
        /** @var HandshakeInterface $cHandshake */
        list($sHandshake, $cHandshake) = $this->handshakeSetup($sTransfer, $cTransfer);

        $sHandshake->on('established', function (SessionInterface $serverSession) {
            // new server session and the old one is the same.
            $this->assertSame($this->serverSession, $serverSession);
        });

        $cHandshake->on('established', function (SessionInterface $clientSession, $lastAck, $lastMerging) {
            $this->assertSame($this->clientSession, $clientSession);
            $this->assertSame($this->clientSession->get('SERVER-SESSION-KEY'), $this->serverSession->getKey());
            $this->assertNull($lastAck);
            $this->assertNull($lastMerging);
            $this->loop->stop();
        });

        $cHandshake->handshake($this->clientSession);

        $this->loop->run();
    }

    public function testInvalidKey()
    {
        $this->testNoneKey();

        // Change key
        $this->clientSession->set('SERVER-SESSION-KEY', 'Key-is-changed!');
        $SHE = false;   // Server Handshake Error

        /** @var TransferInterface $sTransfer */
        /** @var TransferInterface $cTransfer */
        list($sTransfer, $cTransfer) = $this->connSetup();

        /** @var HandshakeInterface $sHandshake */
        /** @var HandshakeInterface $cHandshake */
        list($sHandshake, $cHandshake) = $this->handshakeSetup($sTransfer, $cTransfer);

        $sHandshake->on('error', function () use (&$SHE) {
            $SHE = true;
        });

        $cHandshake->on('error', function () use (&$SHE) {
            $this->assertTrue($SHE);
            $this->loop->stop();
        });

        $cHandshake->handshake($this->clientSession);

        $this->loop->run();
    }
}