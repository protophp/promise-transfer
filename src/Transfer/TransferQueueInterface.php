<?php

namespace Proto\Socket\Transfer;

use Proto\Pack\PackInterface;

interface TransferQueueInterface
{
    public function add(PackInterface $pack, callable $onAck): array;

    public function ack(int $id);
}