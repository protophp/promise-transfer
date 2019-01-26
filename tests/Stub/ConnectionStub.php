<?php

namespace Proto\Socket\Tests\Stub;

use Evenement\EventEmitter;
use Psr\Log\LoggerAwareTrait;
use React\Socket\ConnectionInterface;
use React\Stream\Util;
use React\Stream\WritableStreamInterface;

class ConnectionStub extends EventEmitter implements ConnectionInterface
{
    use LoggerAwareTrait;

    private $data = '';

    /**
     * @var ConnectionStub
     */
    private $remote;

    public function isReadable()
    {
        return true;
    }

    public function isWritable()
    {
        return true;
    }

    public function pause()
    {
    }

    public function resume()
    {
    }

    public function pipe(WritableStreamInterface $dest, array $options = array())
    {
        Util::pipe($this, $dest, $options);

        return $dest;
    }

    public function write($data)
    {
        $this->data .= $data;
        isset($this->logger) && $this->logger->debug('BUFFER: ' . $this->data);
        return true;
    }

    public function end($data = null)
    {
    }

    public function close()
    {
    }

    public function getRemoteAddress()
    {
        return '127.0.0.1';
    }

    public function getLocalAddress()
    {
        return '127.0.0.1';
    }

    public function flush(int $bytes = null)
    {
        if($this->data == '')
            return;

        if ($bytes === null) {
            $flush = $this->data;
            $this->data = '';
        } else {
            $flush = substr($this->data, 0, $bytes);
            $this->data = substr($this->data, $bytes);
        }
        isset($this->logger) && $this->logger->debug('FLUSH: ' . $flush);
        $this->remote->emit('data', [$flush]);
    }

    public function connect(ConnectionInterface $conn)
    {
        if (isset($this->remote))
            return;

        $this->remote = $conn;
        $this->remote->connect($this);
    }
}
