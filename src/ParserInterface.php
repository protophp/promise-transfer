<?php

namespace Proto\PromiseTransfer;

use Proto\Pack\PackInterface;
use Proto\PromiseTransfer\Exception\ParserException;

define('TRANSFER_RESERVED_KEY', pack('C', 255));

interface ParserInterface
{
    const TYPE_ACK = 0;
    const TYPE_DATA = 1;
    const TYPE_RESPONSE = 2;

    /**
     * ParserInterface constructor.
     * @param PackInterface $pack
     * @throws ParserException
     */
    public function __construct(PackInterface $pack);

    public function getId(): int;

    public function getSeq(): int;

    public function getResponseId(): int;

    public function isAck(): bool;

    public function isResponse(): bool;

    public function isWaitForResponse(): bool;

    public function setAckHeader(): PackInterface;

    public static function setDataHeader(PackInterface $pack, int $id, int $seq, bool $isWaitForResponse = false): PackInterface;

    public static function setResponseHeader(PackInterface $pack, int $id, int $seq, int $responseId): PackInterface;
}