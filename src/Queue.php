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

    public function correction(array $inProgress)
    {
        list($id, $seq, $progress) = $inProgress;

        if ($id === null)
            return '';

        // Examples:
        // ID=20 for "!isset($this->seq[$id])"
        // ID=1 & SEQ=0 for "$this->seq[$id] !== $seq"
        // ---------------------
        // ID | SEQ | Delivered
        // 1  | 0   | 1     <= This pack has delivered already
        // 5  | 0   | 0
        // 7  | 0   | 0
        // 1  | 1   | 0     <= New pack is sent by ID = 1 and it has not yet delivered
        // ---------------------
        if (!isset($this->seq[$id]) || $this->seq[$id] !== $seq)
            return '';     // Nothing to do...

        // Example:
        // ID=1 & SEQ=0
        // ---------------------
        // ID | SEQ | Delivered
        // 1  | 0   | 1     <= This pack has delivered already
        // 5  | 0   | 0
        // 7  | 0   | 0
        // ---------------------
        if (isset($this->seq[$id]) && !isset($this->queue[$id]))
            return '';     // Nothing to do...

        $buffer = '';
        $progressReached = false;
        while (($queueId = key($this->queue)) !== null) {

            if ($progressReached === false && $queueId === $id) {
                if ($progress === true)
                    $this->ack($queueId);
                else
                    $buffer .= substr($this->queue[$queueId][0]->toString(), $progress);

                $progressReached = true;
            }

            // Ack pack
            if ($progressReached === false)
                $this->ack($queueId);
            else
                $buffer .= $this->queue[$queueId][0]->toString();

            next($this->queue);
        }

        reset($this->queue);
        return $buffer;
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