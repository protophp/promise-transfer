<?php

namespace Proto\Socket\Transfer;

use Proto\Pack\PackInterface;
use Proto\Socket\Transfer\Exception\ParserException;

interface ParserInterface
{
    const TYPE_ACK = 0;
    const TYPE_DATA = 1;

    /**
     * ParserInterface constructor.
     * @param PackInterface $pack
     * @throws ParserException
     */
    public function __construct(PackInterface $pack);

    public function getId(): int;

    public function getSeq(): int;

    public function isAck(): bool;

    public function setAckHeader(): PackInterface;

    public static function setDataHeader(PackInterface $pack, int $id, int $seq): PackInterface;
}