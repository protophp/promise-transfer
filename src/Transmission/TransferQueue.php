<?php

namespace Proto\Socket\Transmission;

use Proto\Pack\PackInterface;

class TransferQueue implements TransferQueueInterface
{
    private $queue = [null];
    private $seq = [];

    public function add(PackInterface $pack, callable $onAck = null): array
    {
        list($id, $seq) = $return = $this->getIdleId();

        $this->queue[$id] = [$pack, $onAck];
        $this->seq[$id] = $seq;

        return $return;
    }

    public function ack(int $id)
    {
        if (is_callable($this->queue[$id][1]))
            call_user_func($this->queue[$id][1]);

        $this->queue[$id] = null;
    }

    private function getIdleId()
    {
        $id = array_search(null, $this->queue);
        if ($id === false)
            $id = count($this->queue);

        if (isset($this->seq[$id]))
            $seq = $this->seq[$id] === 0 ? 1 : 0;
        else
            $seq = 0;

        return [$id, $seq];
    }
}