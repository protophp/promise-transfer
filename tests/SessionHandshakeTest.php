<?php

namespace Proto\Socket\Test;

use PHPUnit\Framework\TestCase;
use Proto\Session\SessionInterface;
use Proto\Session\SessionManager;
use Proto\Session\SessionManagerInterface;
use Proto\Socket\Transmission\SessionHandshake;
use Proto\Socket\Transmission\SessionHandshakeInterface;
use React\EventLoop\Factory;
use React\Socket\ConnectionInterface;
use React\Socket\Connector;
use React\Socket\Server;

class SessionHandshakeTest extends TestCase
{
    /**
     * @var SessionManagerInterface
     */
    private $sessionManager;

    /**
     * @var SessionInterface
     */
    private $serverSession;

    /**
     * @var SessionInterface
     */
    private $clientSession;

    /**
     * @var \React\EventLoop\LoopInterface
     */
    private $loop;

    /**
     * @var SessionHandshakeInterface
     */
    private $serverHandshake;

    /**
     * @var SessionHandshakeInterface
     */
    private $clientHandshake;

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

    public function testNoneKey()
    {
        $this->prepare(
            function () {
                $this->serverHandshake->on('established', function (SessionInterface $serverSession, $lastAck, $lastMerging) {
                    $this->serverSession = $serverSession;
                    $this->assertNull($lastAck);
                    $this->assertNull($lastMerging);
                });
            },
            function () {
                $this->clientHandshake->handshake($this->clientSession);
                $this->clientHandshake->on('established', function (SessionInterface $clientSession, $lastAck, $lastMerging) {
                    $this->assertSame($this->clientSession, $clientSession);
                    $this->assertSame($this->clientSession->get('SERVER-SESSION-KEY'), $this->serverSession->getKey());
                    $this->assertNull($lastAck);
                    $this->assertNull($lastMerging);
                    $this->loop->stop();
                });
            }
        );
        $this->loop->run();
    }

    public function testWithKey()
    {
        $this->testNoneKey();

        $this->prepare(
            function () {
                $this->serverHandshake->on('established', function (SessionInterface $serverSession, $lastAck, $lastMerging) {

                    // new server session and the old one is the same.
                    $this->assertSame($this->serverSession, $serverSession);

                    $this->assertNull($lastAck);
                    $this->assertNull($lastMerging);
                });
            },
            function () {
                $this->clientHandshake->handshake($this->clientSession);
                $this->clientHandshake->on('established', function (SessionInterface $clientSession, $lastAck, $lastMerging) {
                    $this->assertSame($this->clientSession, $clientSession);
                    $this->assertSame($this->clientSession->get('SERVER-SESSION-KEY'), $this->serverSession->getKey());
                    $this->assertNull($lastAck);
                    $this->assertNull($lastMerging);
                    $this->loop->stop();
                });
            }
        );
        $this->loop->run();
    }

    public function testInvalidKey()
    {
        $this->testNoneKey();

        // Change key
        $this->clientSession->set('SERVER-SESSION-KEY', 'Key-is-changed!');

        $SHE = false;   // Server Handshake Error
        $this->prepare(
            function () use (&$SHE) {
                $this->serverHandshake->on('error', function () use (&$SHE) {
                    $SHE = true;
                });
            },
            function () use (&$SHE) {
                $this->clientHandshake->handshake($this->clientSession);
                $this->clientHandshake->on('error', function () use (&$SHE) {
                    $this->assertTrue($SHE);
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
            $this->serverHandshake = new SessionHandshake($conn, $this->sessionManager);
            call_user_func($onServer, $conn);
        });
        $server->on('error', function (\Exception $e) {
            die($e->getTraceAsString());
        });

        // Client setup
        $client = new Connector($this->loop);
        $client->connect("tcp://127.0.0.1:$port")->then(function (ConnectionInterface $conn) use ($onClient) {
            $this->clientHandshake = new SessionHandshake($conn, $this->sessionManager);
            call_user_func($onClient, $conn);
        })->otherwise(function (\Exception $e) {
            die($e->getTraceAsString());
        });
    }
}