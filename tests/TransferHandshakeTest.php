<?php

namespace Proto\PromiseTransfer\Tests;

use Monolog\Handler\ErrorLogHandler;
use Monolog\Logger;
use Proto\Session\SessionInterface;
use Proto\PromiseTransfer\Tests\Stub\ConnectionStub;
use Proto\PromiseTransfer\Handshake\Handshake;
use Proto\PromiseTransfer\PromiseTransfer;

class TransferHandshakeTest extends TestCase
{
    /**
     * @var SessionInterface
     */
    private $serverSession;

    public function testNoneKey()
    {
        // Create new connection
        $sConn = new ConnectionStub();
        $cConn = new ConnectionStub();
        $cConn->connect($sConn);

        // Setup the transfer
        $sTransfer = new PromiseTransfer($sConn, $this->sessionManager);
        $cTransfer = new PromiseTransfer($cConn, $this->sessionManager);

        // Setup the handshake
        $sHandshake = new Handshake($sTransfer);
        $cHandshake = new Handshake($cTransfer);

        // Set logger
        $sHandshake->setLogger(new Logger('ServerHandshake', [new ErrorLogHandler()]));
        $cHandshake->setLogger(new Logger('ClientHandshake', [new ErrorLogHandler()]));

        $sHandshake->on('established', function (SessionInterface $serverSession) {
            $this->serverSession = $serverSession;
        });

        $cHandshake->on('established', function (SessionInterface $clientSession) {
            $this->assertSame($this->clientSession, $clientSession);
            $this->assertSame($this->clientSession->get('SERVER-SESSION-KEY'), $this->serverSession->getKey());
        });

        // Handshake
        $cHandshake->handshake($this->clientSession);

        // Flush connections
        $cConn->flush();
        $sConn->flush();

        $this->assertEquals(2, $this->getCount());
    }

    public function testWithKey()
    {
        $this->testNoneKey();

        // Create new connection
        $sConn = new ConnectionStub();
        $cConn = new ConnectionStub();
        $cConn->connect($sConn);

        // Setup the transfer
        $sTransfer = new PromiseTransfer($sConn, $this->sessionManager);
        $cTransfer = new PromiseTransfer($cConn, $this->sessionManager);

        // Setup the handshake
        $sHandshake = new Handshake($sTransfer);
        $cHandshake = new Handshake($cTransfer);

        // Set logger
        $sHandshake->setLogger(new Logger('ServerHandshake', [new ErrorLogHandler()]));
        $cHandshake->setLogger(new Logger('ClientHandshake', [new ErrorLogHandler()]));

        $sHandshake->on('established', function (SessionInterface $serverSession) {
            // new server session and the old one is the same.
            $this->assertSame($this->serverSession, $serverSession);
        });

        $cHandshake->on('established', function (SessionInterface $clientSession) {
            $this->assertSame($this->clientSession, $clientSession);
            $this->assertSame($this->clientSession->get('SERVER-SESSION-KEY'), $this->serverSession->getKey());
        });

        // Handshake
        $cHandshake->handshake($this->clientSession);

        // Flush connections
        $cConn->flush();
        $sConn->flush();

        $this->assertEquals(6, $this->getCount());
    }

    public function testInvalidKey()
    {
        $this->testNoneKey();

        // Change key
        $this->clientSession->set('SERVER-SESSION-KEY', 'Key-is-changed!');
        $SHE = false;   // Server Handshake Error

        // Create new connection
        $sConn = new ConnectionStub();
        $cConn = new ConnectionStub();
        $cConn->connect($sConn);

        // Setup the transfer
        $sTransfer = new PromiseTransfer($sConn, $this->sessionManager);
        $cTransfer = new PromiseTransfer($cConn, $this->sessionManager);

        // Setup the handshake
        $sHandshake = new Handshake($sTransfer);
        $cHandshake = new Handshake($cTransfer);

        // Set logger
        $sHandshake->setLogger(new Logger('ServerHandshake', [new ErrorLogHandler()]));
        $cHandshake->setLogger(new Logger('ClientHandshake', [new ErrorLogHandler()]));

        $sHandshake->on('error', function () use (&$SHE) {
            $SHE = true;
        });

        $cHandshake->on('error', function () use (&$SHE) {
            $this->assertTrue($SHE);
        });

        // Handshake
        $cHandshake->handshake($this->clientSession);

        // Flush connections
        $cConn->flush();
        $sConn->flush();

        $this->assertEquals(4, $this->getCount());
    }
}