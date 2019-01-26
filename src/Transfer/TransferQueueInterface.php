<?php

namespace Proto\Socket\Transfer;

use Proto\Pack\PackInterface;

interface TransferQueueInterface
{
    public function add(PackInterface $pack, callable $onResponse = null, callable $onAck = null): array;

    public function ack(int $id);

    public function response(int $id, PackInterface $pack);
}