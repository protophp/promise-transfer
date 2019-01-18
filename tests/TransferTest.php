<?php

namespace Proto\Socket\Tests;

use PHPUnit\Framework\TestCase;
use Proto\Pack\Pack;
use Proto\Pack\PackInterface;
use Proto\Session\SessionInterface;
use Proto\Session\SessionManager;
use Proto\Socket\Transmission\SessionHandshake;
use Proto\Socket\Transmission\Transfer;
use Proto\Socket\Transmission\TransferInterface;
use React\EventLoop\Factory;
use React\Socket\ConnectionInterface;
use React\Socket\Connector;
use React\Socket\Server;

class TransferTest extends TestCase
{
    /**
     * @var SessionManager
     */
    private $sessionManager;

    /**
     * @var SessionInterface
     */
    private $clientSession;

    /**
     * @var
     */
    private $loop;

    /**
     * SessionHandshakeTest constructor.
     * @param string|null $name
     * @param array $data
     * @param string $dataName
     * @throws \Proto\Session\Exception\SessionException
     */
    public function __construct(string $name = null, array $data = [], string $dataName = '')
    {
        parent::__construct($name, $data, $dataName);

        $this->sessionManager = new SessionManager();
        $this->clientSession = $this->sessionManager->start();
        $this->loop = Factory::create();
    }

    public function testSendAndDelivery()
    {
        $this->prepare(
            function (TransferInterface $serverTransfer) {
                $serverTransfer->on('data', function (PackInterface $pack) {
                    $this->assertSame('FOO-BAR', $pack->getData());
                });
            },
            function (TransferInterface $clientTransfer) {
                $clientTransfer->send((new Pack())->setData('FOO-BAR'), function () {
                    $this->loop->stop();
                });
            }
        );
        $this->loop->run();
    }

    private function prepare(callable $onServer, callable $onClient)
    {
        // Find an unused unprivileged TCP port
        $port = (int)shell_exec('netstat -atn | awk \' /tcp/ {printf("%s\n",substr($4,index($4,":")+1,length($4) )) }\' | sed -e "s/://g" | sort -rnu | awk \'{array [$1] = $1} END {i=32768; again=1; while (again == 1) {if (array[i] == i) {i=i+1} else {print i; again=0}}}\'');

        // Server setup
        $server = new Server("tcp://127.0.0.1:$port", $this->loop);
        $server->on('connection', function (ConnectionInterface $conn) use ($onServer) {
            $serverHandshake = new SessionHandshake($conn, $this->sessionManager);
            $serverHandshake->on('established', function (SessionInterface $session, $lastAck, $lastMerging) use ($conn, $onServer) {
                $transfer = new Transfer($conn, $session, $lastAck, $lastMerging);
                call_user_func($onServer, $transfer);
            });
        });
        $server->on('error', function (\Exception $e) {
            die($e->getTraceAsString());
        });

        // Client setup
        $client = new Connector($this->loop);
        $client->connect("tcp://127.0.0.1:$port")->then(function (ConnectionInterface $conn) use ($onClient) {
            $clientHandshake = new SessionHandshake($conn, $this->sessionManager);
            $clientHandshake->handshake($this->clientSession);
            $clientHandshake->on('established', function (SessionInterface $session, $lastAck, $lastMerging) use ($conn, $onClient) {
                $transfer = new Transfer($conn, $session, $lastAck, $lastMerging);
                call_user_func($onClient, $transfer);
            });
        })->otherwise(function (\Exception $e) {
            die($e->getTraceAsString());
        });
    }
}