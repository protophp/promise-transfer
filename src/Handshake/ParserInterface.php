<?php

namespace Proto\PromiseTransfer\Handshake;

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

    public function getInProgress();

    public function doRequest(string $serverSessionKey = null, array $lastProgress = null): string;

    public function doEstablished($serverSessionKey, $lastProgress): string;

    public function doError(int $code): string;
}