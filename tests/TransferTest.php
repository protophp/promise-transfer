<?php

namespace Proto\Socket\Tests;

use Monolog\Handler\ErrorLogHandler;
use Monolog\Logger;
use Proto\Pack\Pack;
use Proto\Pack\PackInterface;
use Proto\Socket\Tests\Stub\ConnectionStub;
use Proto\Socket\Transfer\ParserInterface;
use Proto\Socket\Transfer\Transfer;
use Proto\Socket\Transfer\TransferInterface;

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
        $sTransfer = new Transfer($sConn, $this->sessionManager);
        $cTransfer = new Transfer($cConn, $this->sessionManager);

        $sTransfer->setLogger(new Logger('ServerTransfer', [new ErrorLogHandler()]));
        $cTransfer->setLogger(new Logger('ClientTransfer', [new ErrorLogHandler()]));

        // Init the transfer
        $sTransfer->init();
        $cTransfer->init($this->clientSession);

        $sTransfer->on('established', function (TransferInterface $transfer) use ($sConn) {
            $transfer->on('data', function (PackInterface $pack, ParserInterface $parser) use ($sConn) {
                $this->assertFalse($parser->isWaitForResponse());
                $this->assertSame('FOO-BAR', $pack->getData());
                $sConn->flush();
            });
        });

        $cTransfer->on('established', function (TransferInterface $transfer) use ($cConn) {
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
        $sTransfer = new Transfer($sConn, $this->sessionManager);
        $cTransfer = new Transfer($cConn, $this->sessionManager);

        $sTransfer->setLogger(new Logger('ServerTransfer', [new ErrorLogHandler()]));
        $cTransfer->setLogger(new Logger('ClientTransfer', [new ErrorLogHandler()]));

        // Init the transfer
        $sTransfer->init();
        $cTransfer->init($this->clientSession);

        $sTransfer->on('established', function (TransferInterface $transfer) use ($sConn, $sTransfer) {
            $transfer->on('data', function (PackInterface $pack, ParserInterface $parser) use ($sConn, $sTransfer) {
                $this->assertTrue($parser->isWaitForResponse());
                $this->assertSame('FOO-BAR', $pack->getData());

                // send response
                $sTransfer->response((new Pack())->setData('RESPONSE-FOO-BAR'), $parser->getId());

                $sConn->flush();
            });
        });

        $cTransfer->on('established', function (TransferInterface $transfer) use ($cConn) {
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
}