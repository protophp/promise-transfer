<?php

namespace Proto\PromiseTransfer\Tests;

use Monolog\Handler\ErrorLogHandler;
use Monolog\Logger;
use Proto\Pack\Pack;
use Proto\Pack\PackInterface;
use Proto\PromiseTransfer\Tests\Stub\ConnectionStub;
use Proto\PromiseTransfer\ParserInterface;
use Proto\PromiseTransfer\PromiseTransfer;
use Proto\PromiseTransfer\PromiseTransferInterface;

class TransferTest extends TestCase
{

    public function testSendAndDelivery()
    {
        // Create new connection
        $sConn = new ConnectionStub();
        $cConn = new ConnectionStub();
        $cConn->connect($sConn);

        $sConn->setLogger(new Logger('ServerConn', [new ErrorLogHandler()]));
        $cConn->setLogger(new Logger('ClientConn', [new ErrorLogHandler()]));

        // Setup the transfer
        $sTransfer = new PromiseTransfer($sConn, $this->sessionManager);
        $cTransfer = new PromiseTransfer($cConn, $this->sessionManager);

        $sTransfer->setLogger(new Logger('ServerTransfer', [new ErrorLogHandler()]));
        $cTransfer->setLogger(new Logger('ClientTransfer', [new ErrorLogHandler()]));

        // Init the transfer
        $sTransfer->init();
        $cTransfer->init($this->clientSession);

        $sTransfer->on('established', function (PromiseTransferInterface $transfer) use ($sConn) {
            $transfer->on('data', function (PackInterface $pack, ParserInterface $parser) use ($sConn) {
                $this->assertFalse($parser->isWaitForResponse());
                $this->assertSame('FOO-BAR', $pack->getData());
                $sConn->flush();
            });
        });

        $cTransfer->on('established', function (PromiseTransferInterface $transfer) use ($cConn) {
            $transfer->send((new Pack())->setData('FOO-BAR'), null, function () {
                $this->assertEquals(2, $this->getCount());
            });
            $cConn->flush();
        });

        $cConn->flush();
        $sConn->flush();

        $this->assertEquals(3, $this->getCount());
    }

    public function testResponse()
    {
        // Create new connection
        $sConn = new ConnectionStub();
        $cConn = new ConnectionStub();
        $cConn->connect($sConn);

        $sConn->setLogger(new Logger('ServerConn', [new ErrorLogHandler()]));
        $cConn->setLogger(new Logger('ClientConn', [new ErrorLogHandler()]));

        // Setup the transfer
        $sTransfer = new PromiseTransfer($sConn, $this->sessionManager);
        $cTransfer = new PromiseTransfer($cConn, $this->sessionManager);

        $sTransfer->setLogger(new Logger('ServerTransfer', [new ErrorLogHandler()]));
        $cTransfer->setLogger(new Logger('ClientTransfer', [new ErrorLogHandler()]));

        // Init the transfer
        $sTransfer->init();
        $cTransfer->init($this->clientSession);

        $sTransfer->on('established', function (PromiseTransferInterface $transfer) use ($sConn, $sTransfer) {
            $transfer->on('data', function (PackInterface $pack, ParserInterface $parser) use ($sConn, $sTransfer) {
                $this->assertTrue($parser->isWaitForResponse());
                $this->assertSame('FOO-BAR', $pack->getData());

                // send response
                $sTransfer->response((new Pack())->setData('RESPONSE-FOO-BAR'), $parser->getId());

                $sConn->flush();
            });
        });

        $cTransfer->on('established', function (PromiseTransferInterface $transfer) use ($cConn) {
            $transfer->send(
                (new Pack())->setData('FOO-BAR'),
                function (PackInterface $pack) {
                    $this->assertSame('RESPONSE-FOO-BAR', $pack->getData());
                },
                function () {
                    $this->assertTrue(true);
                });
            $cConn->flush();
        });

        $cConn->flush();
        $sConn->flush();

        $this->assertEquals(4, $this->getCount());
    }

    public function testResume_packNotReachedToMerging()
    {
        // Create new connection
        $sConn = new ConnectionStub();
        $cConn = new ConnectionStub();
        $cConn->connect($sConn);

        $sConn->setLogger(new Logger('ServerConn', [new ErrorLogHandler()]));
        $cConn->setLogger(new Logger('ClientConn', [new ErrorLogHandler()]));

        // Setup the transfer
        $sTransfer = new PromiseTransfer($sConn, $this->sessionManager);
        $cTransfer = new PromiseTransfer($cConn, $this->sessionManager);

        $sTransfer->setLogger(new Logger('ServerTransfer', [new ErrorLogHandler()]));
        $cTransfer->setLogger(new Logger('ClientTransfer', [new ErrorLogHandler()]));

        // Init the transfer
        $sTransfer->init();
        $cTransfer->init($this->clientSession);

        $cTransfer->on('established', function (PromiseTransferInterface $transfer) use (&$cConn, &$sConn) {
            $transfer->send((new Pack())->setData('FOO-BAR'), null, function () {
                $this->assertEquals(1, $this->getCount());
            });

            $cConn->flush(5);   // Flush 5 bytes

            /////////////////
            /// Reconnect ///
            /////////////////

            $sConn = new ConnectionStub();
            $cConn = new ConnectionStub();
            $cConn->connect($sConn);

            $sConn->setLogger(new Logger('ServerConn', [new ErrorLogHandler()]));
            $cConn->setLogger(new Logger('ClientConn', [new ErrorLogHandler()]));

            // Setup the transfer
            $sTransfer = new PromiseTransfer($sConn, $this->sessionManager);
            $cTransfer = new PromiseTransfer($cConn, $this->sessionManager);

            $sTransfer->setLogger(new Logger('ServerTransfer', [new ErrorLogHandler()]));
            $cTransfer->setLogger(new Logger('ClientTransfer', [new ErrorLogHandler()]));

            // Init the transfer
            $sTransfer->init();
            $cTransfer->init($this->clientSession);

            $sTransfer->on('established', function (PromiseTransferInterface $transfer) use ($sConn) {
                $transfer->on('data', function (PackInterface $pack) use (&$sConn) {
                    $this->assertSame('FOO-BAR', $pack->getData());
                    $sConn->flush();
                });
            });

            // Flush for handshake
            $cConn->flush();
            $sConn->flush();

            // Flush for queue and ack
            $cConn->flush();
            $sConn->flush();

        });

        $cConn->flush();
        $sConn->flush();

        $this->assertEquals(2, $this->getCount());
    }

    public function testResume_packReachedToMerging()
    {
        // Create new connection
        $sConn = new ConnectionStub();
        $cConn = new ConnectionStub();
        $cConn->connect($sConn);

        $sConn->setLogger(new Logger('ServerConn', [new ErrorLogHandler()]));
        $cConn->setLogger(new Logger('ClientConn', [new ErrorLogHandler()]));

        // Setup the transfer
        $sTransfer = new PromiseTransfer($sConn, $this->sessionManager);
        $cTransfer = new PromiseTransfer($cConn, $this->sessionManager);

        $sTransfer->setLogger(new Logger('ServerTransfer', [new ErrorLogHandler()]));
        $cTransfer->setLogger(new Logger('ClientTransfer', [new ErrorLogHandler()]));

        // Init the transfer
        $sTransfer->init();
        $cTransfer->init($this->clientSession);

        $cTransfer->on('established', function (PromiseTransferInterface $transfer) use (&$cConn, &$sConn) {
            $transfer->send((new Pack())->setData('FOO-BAR'), null, function () {
                $this->assertEquals(1, $this->getCount());
            });

            $cConn->flush(8);   // Flush 8 bytes

            /////////////////
            /// Reconnect ///
            /////////////////

            $sConn = new ConnectionStub();
            $cConn = new ConnectionStub();
            $cConn->connect($sConn);

            $sConn->setLogger(new Logger('ServerConn', [new ErrorLogHandler()]));
            $cConn->setLogger(new Logger('ClientConn', [new ErrorLogHandler()]));

            // Setup the transfer
            $sTransfer = new PromiseTransfer($sConn, $this->sessionManager);
            $cTransfer = new PromiseTransfer($cConn, $this->sessionManager);

            $sTransfer->setLogger(new Logger('ServerTransfer', [new ErrorLogHandler()]));
            $cTransfer->setLogger(new Logger('ClientTransfer', [new ErrorLogHandler()]));

            // Init the transfer
            $sTransfer->init();
            $cTransfer->init($this->clientSession);

            $sTransfer->on('established', function (PromiseTransferInterface $transfer) use ($sConn) {
                $transfer->on('data', function (PackInterface $pack) use (&$sConn) {
                    $this->assertSame('FOO-BAR', $pack->getData());
                    $sConn->flush();
                });
            });

            // Flush for handshake
            $cConn->flush();
            $sConn->flush();

            // Flush for queue and ack
            $cConn->flush();
            $sConn->flush();

        });

        $cConn->flush();
        $sConn->flush();

        $this->assertEquals(2, $this->getCount());
    }
}