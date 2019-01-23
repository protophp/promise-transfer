<?php

namespace Proto\Socket\Handshake;

use Proto\Pack\PackInterface;

interface ParserInterface
{
    const REQUEST = 0;
    const ESTABLISHED = 100;

    const ERR_INVALID_SESSION_KEY = 1;
    const ERR_SOMETHING_WRONG = 10;

    public function __construct(PackInterface $pack = null);

    public function onRequest(callable $callable);

    public function onEstablished(callable $callable);

    public function onError(callable $callable);

    public function parse();

    public function isServerSessionKey(): bool;

    public function getServerSessionKey();

    public function getLastAck();

    public function getLastMerging();

    public function doRequest(string $serverSessionKey = null, array $lastAck = null, array $lastMerging = null): string;

    public function doEstablished($serverSessionKey, $lastAck, $lastMerging): string;

    public function doError(int $code): string;
}