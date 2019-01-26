<?php

namespace Proto\Socket\Transfer;

use Proto\Pack\PackInterface;
use Proto\Socket\Transfer\Exception\ParserException;

class Parser implements ParserInterface
{
    /**
     * @var PackInterface
     */
    private $pack;

    private $type = null;
    private $id = null;
    private $seq = null;
    private $responseId = null;
    private $waitForResponse = false;

    function __construct(PackInterface $pack)
    {
        $info = $pack->getHeaderByKey(0);
        if (!isset($info[0]))
            throw new ParserException(ParserException::TYPE_NOT_FOUND);

        if (!isset($info[1]))
            throw new ParserException(ParserException::ID_NOT_FOUND);

        if (!isset($info[2]))
            throw new ParserException(ParserException::SEQ_NOT_FOUND);

        if (!is_int($info[1]))
            throw new ParserException(ParserException::INVALID_ID);

        if ($info[0] !== 0 && $info[0] !== 1 && $info[0] !== 2)
            throw new ParserException(ParserException::INVALID_TYPE);

        if ($info[2] !== 0 && $info[2] !== 1)
            throw new ParserException(ParserException::INVALID_SEQ);

        $this->pack = $pack;
        $this->type = $info[0];
        $this->id = $info[1];
        $this->seq = $info[2];

        if ($this->isResponse()) {
            if (!isset($info[3]))
                throw new ParserException(ParserException::RESPONSE_ID_NOT_FOUND);

            if (!is_int($info[3]))
                throw new ParserException(ParserException::INVALID_RESPONSE_ID);

            $this->responseId = $info[3];
        } else {
            $this->waitForResponse = isset($info[3]) && $info[3] === true ? true : false;
        }
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getSeq(): int
    {
        return $this->seq;
    }

    public function getResponseId(): int
    {
        return $this->responseId;
    }

    public function isAck(): bool
    {
        return $this->type === self::TYPE_ACK;
    }

    public function isResponse(): bool
    {
        return $this->type === self::TYPE_RESPONSE;
    }

    public function isWaitForResponse(): bool
    {
        return $this->waitForResponse;
    }

    public function setAckHeader(): PackInterface
    {
        $pack = clone $this->pack;
        return $pack->setHeaderByKey(0, [self::TYPE_ACK, $this->getId(), $this->getSeq()]);
    }

    public static function setDataHeader(PackInterface $pack, int $id, int $seq, bool $isWaitForResponse = false): PackInterface
    {
        if ($isWaitForResponse)
            return $pack->setHeaderByKey(0, [self::TYPE_DATA, $id, $seq, true]);
        else
            return $pack->setHeaderByKey(0, [self::TYPE_DATA, $id, $seq]);
    }

    public static function setResponseHeader(PackInterface $pack, int $id, int $seq, int $responseId): PackInterface
    {
        return $pack->setHeaderByKey(0, [self::TYPE_RESPONSE, $id, $seq, $responseId]);
    }
}