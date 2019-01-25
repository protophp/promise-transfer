<?php

namespace Proto\Socket\Tests;

use Monolog\Handler\ErrorLogHandler;
use Monolog\Logger;
use Proto\Session\Exception\SessionException;
use Proto\Session\SessionInterface;
use Proto\Session\SessionManager;
use Proto\Session\SessionManagerInterface;
use Proto\Socket\Transfer\Handshake\Handshake;
use Proto\Socket\Transfer\Transfer;
use Proto\Socket\Transfer\TransferInterface;
use React\EventLoop\Factory;

class TestCase extends \PHPUnit\Framework\TestCase
{
    /**
     * @var SessionManagerInterface
     */
    protected $sessionManager;

    /**
     * @var SessionInterface
     */
    protected $clientSession;

    /**
     * @var \React\EventLoop\LoopInterface
     */
    protected $loop;

    /**
     * SessionHandshakeTest constructor.
     * @param string|null $name
     * @param array $data
     * @param string $dataName
     * @throws SessionException
     */
    public function __construct(string $name = null, array $data = [], string $dataName = '')
    {
        parent::__construct($name, $data, $dataName);

        $this->sessionManager = new SessionManager();
        $this->clientSession = $this->sessionManager->start();
        $this->loop = Factory::create();
    }

    protected function connSetup()
    {
        $cTransfer = new Transfer($this->sessionManager);
        $sTransfer = new Transfer($this->sessionManager);

        $cTransfer->on('onWrite', function ($data) use ($sTransfer) {
            $this->loop->futureTick(function () use ($data, $sTransfer) {
                $sTransfer->emit('onIncome', [$data]);
            });

        });

        $sTransfer->on('onWrite', function ($data) use ($cTransfer) {
            $this->loop->futureTick(function () use ($data, $cTransfer) {
                $cTransfer->emit('onIncome', [$data]);
            });
        });

        return [$sTransfer, $cTransfer];
    }

    protected function handshakeSetup(TransferInterface $sTransfer, TransferInterface $cTransfer)
    {
        $sHandshake = new Handshake($sTransfer, $this->sessionManager);
        $cHandshake = new Handshake($cTransfer, $this->sessionManager);

        $sHandshake->setLogger(new Logger('ServerHandshake', [new ErrorLogHandler()]));
        $cHandshake->setLogger(new Logger('ClientHandshake', [new ErrorLogHandler()]));

        return [$sHandshake, $cHandshake];
    }
}