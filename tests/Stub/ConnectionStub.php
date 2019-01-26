<?php

namespace Proto\Socket\Tests\Stub;

use Evenement\EventEmitter;
use React\Socket\ConnectionInterface;
use React\Stream\Util;
use React\Stream\WritableStreamInterface;

class ConnectionStub extends EventEmitter implements ConnectionInterface
{
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
        if ($bytes === null) {
            $this->remote->emit('data', [$this->data]);
            $this->data = '';
        } else {
            $this->remote->emit('data', [substr($this->data, 0, $bytes)]);
            $this->data = substr($this->data, $bytes);
        }
    }

    public function connect(ConnectionInterface $conn)
    {
        if(isset($this->remote))
            return;

        $this->remote = $conn;
        $this->remote->connect($this);
    }
}
