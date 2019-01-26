<?php

namespace Proto\PromiseTransfer;

use Proto\Pack\PackInterface;

class Queue implements QueueInterface
{
    private $queue = [];
    private $seq = [];

    public function add(PackInterface $pack, callable $onResponse = null, callable $onAck = null): array
    {
        list($id, $seq) = $return = $this->getIdleId();

        $this->queue[$id] = [$pack, $onAck, $onResponse];
        $this->seq[$id] = $seq;

        return $return;
    }

    public function ack(int $id)
    {
        if (is_callable($this->queue[$id][1]))
            call_user_func($this->queue[$id][1]);

        // Wait for response
        if (isset($this->queue[$id][2])) {
            unset($this->queue[$id][0], $this->queue[$id][1]);
        } else {
            unset($this->queue[$id]);
        }
    }

    public function response(int $id, PackInterface $pack)
    {
        if (is_callable($this->queue[$id][2]))
            call_user_func($this->queue[$id][2], $pack);

        unset($this->queue[$id]);
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