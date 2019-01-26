<?php

namespace Proto\Socket\Tests;

use Monolog\Handler\ErrorLogHandler;
use Monolog\Logger;
use Proto\Pack\Pack;
use Proto\Pack\PackInterface;
use Proto\Socket\Tests\Stub\ConnectionStub;
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
            $transfer->on('data', function (PackInterface $pack) use ($sConn) {
                $this->assertSame('FOO-BAR', $pack->getData());
                $sConn->flush();
            });
        });

        $cTransfer->on('established', function (TransferInterface $transfer) use ($cConn) {
            $transfer->send((new Pack())->setData('FOO-BAR'), function () {
                $this->assertEquals(1, $this->getCount());
            });
            $cConn->flush();
        });

        $cConn->flush();
        $sConn->flush();

        $this->assertEquals(2, $this->getCount());
    }
}