<?php

namespace Proto\PromiseTransfer\Handshake;

use Proto\Pack\Pack;
use Proto\Pack\PackInterface;

class Parser implements ParserInterface
{
    private $onRequest;
    private $onEstablished;
    private $onError;

    private $action;
    private $inProgress;
    private $serverSessionKey;

    public function __construct(PackInterface $pack = null)
    {
        if ($pack) {
            $this->action = $pack->getHeaderByKey(0);
            $this->serverSessionKey = $pack->getHeaderByKey(1);
            $this->inProgress = $pack->getHeaderByKey(2);

            $this->inProgress = [
                isset($this->inProgress[0]) ? (int)$this->inProgress[0] : null,
                isset($this->inProgress[1]) ? (int)$this->inProgress[1] : null,
                isset($this->inProgress[2]) ? ($this->inProgress[2] === true ? true : (int)$this->inProgress[2]) : 0,
            ];
        }
    }

    public function onRequest(callable $callable)
    {
        $this->onRequest = $callable;
    }

    public function onEstablished(callable $callable)
    {
        $this->onEstablished = $callable;
    }

    public function onError(callable $callable)
    {
        $this->onError = $callable;
    }

    public function parse()
    {
        if (!isset($this->action))
            return;

        switch ($this->action) {
            case self::REQUEST:
                call_user_func($this->onRequest);
                break;

            case self::ESTABLISHED:
                call_user_func($this->onEstablished);
                break;

            case self::ERR_SOMETHING_WRONG:
            case self::ERR_INVALID_SESSION_KEY:
                call_user_func($this->onError);
                break;

            default:
                return;
        }
    }

    public function isServerSessionKey(): bool
    {
        return isset($this->serverSessionKey);
    }

    public function getServerSessionKey()
    {
        return $this->serverSessionKey;
    }

    public function getInProgress()
    {
        return $this->inProgress;
    }

    public function doRequest(string $serverSessionKey = null, array $lastProgress = null): string
    {
        return (new Pack())->setHeader([self::REQUEST, $serverSessionKey, $lastProgress])->toString();
    }

    public function doEstablished($serverSessionKey, $lastProgress): string
    {
        return (new Pack)->setHeader([self::ESTABLISHED, $serverSessionKey, $lastProgress])->toString();
    }

    public function doError(int $code): string
    {
        return (new Pack)->setHeader([$code])->toString();
    }
}