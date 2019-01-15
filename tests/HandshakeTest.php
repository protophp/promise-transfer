<?php

namespace Proto\Socket\Test;

use PHPUnit\Framework\TestCase;
use Proto\Session\SessionInterface;
use Proto\Session\SessionManager;
use Proto\Session\SessionManagerInterface;
use Proto\Socket\Transmission\Handshake;
use React\EventLoop\Factory;
use React\Socket\ConnectionInterface;
use React\Socket\Connector;
use React\Socket\Server;

class HandshakeTest extends TestCase
{
    /**
     * @var SessionManagerInterface
     */
    private $sessionManager;
    private $key = null;

    public function test()
    {
        $this->testNoneKey();
        $this->testWithKey();
    }

    private function testNoneKey()
    {
        $this->sessionManager = new SessionManager();

        $unix = __DIR__ . '/handshake-unix.socks';
        if (file_exists($unix))
            unlink($unix);

        $loop = Factory::create();

        $server = new Server("unix://$unix", $loop);
        $client = new Connector($loop);

        $server->on('connection', function (ConnectionInterface $conn) {
            $handshake = new Handshake($conn, $this->sessionManager);

            $handshake->on('error', function () {
                die('Server Handshake Error!');
            });

            $handshake->on('established', function (SessionInterface $session) {
                $this->key = $session->getKey();
            });
        });

        $client->connect("unix://$unix")->then(function (ConnectionInterface $conn) use ($loop, $unix) {

            $handshake = new Handshake($conn, $this->sessionManager);
            $handshake->handshake();

            $handshake->on('error', function () {
                die('Client Handshake Error!');
            });

            $handshake->on('established', function (string $key) use ($loop, $unix) {
                $this->assertSame($this->key, $key);
                unlink($unix);
                $loop->stop();
            });

        })->otherwise(function (\Exception $e) {
            die($e->getTraceAsString());
        });

        $loop->run();
    }

    private function testWithKey()
    {
        $unix = __DIR__ . '/handshake-unix.socks';
        if (file_exists($unix))
            unlink($unix);

        $loop = Factory::create();

        $server = new Server("unix://$unix", $loop);
        $client = new Connector($loop);

        $server->on('connection', function (ConnectionInterface $conn) {
            $handshake = new Handshake($conn, $this->sessionManager);

            $handshake->on('error', function () {
                die('Server Handshake Error!');
            });

            $handshake->on('established', function (SessionInterface $session) {
                $this->assertSame($this->key, $session->getKey());
            });
        });

        $client->connect("unix://$unix")->then(function (ConnectionInterface $conn) use ($loop, $unix) {

            $handshake = new Handshake($conn, $this->sessionManager);
            $handshake->handshake($this->key);

            $handshake->on('error', function () {
                die('Client Handshake Error!');
            });

            $handshake->on('established', function (string $key) use ($loop, $unix) {
                $this->assertSame($this->key, $key);
                unlink($unix);
                $loop->stop();
            });

        })->otherwise(function (\Exception $e) {
            die($e->getTraceAsString());
        });

        $loop->run();
    }
}