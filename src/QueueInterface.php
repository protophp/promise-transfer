<?php

namespace Proto\PromiseTransfer;

use Proto\Pack\PackInterface;

interface QueueInterface
{
    public function add(PackInterface $pack, callable $onResponse = null, callable $onAck = null): array;

    public function ack(int $id);

    public function response(int $id, PackInterface $pack);

    public function correction(array $inProgress);
}