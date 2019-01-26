<?php

namespace Proto\Socket\Transfer\Exception;

class ParserException extends \Exception
{
    const TYPE_NOT_FOUND = 1;
    const ID_NOT_FOUND = 2;
    const SEQ_NOT_FOUND = 3;
    const RESPONSE_ID_NOT_FOUND = 4;
    const INVALID_TYPE = 5;
    const INVALID_ID = 6;
    const INVALID_SEQ = 7;
    const INVALID_RESPONSE_ID = 8;

    const MSG = [
        self::TYPE_NOT_FOUND => "Transfer's Type not found!",
        self::ID_NOT_FOUND => "Transfer's ID not found!",
        self::SEQ_NOT_FOUND => "Transfer's Seq not found!",
        self::INVALID_TYPE => "Invalid transfer's Type!",
        self::INVALID_ID => "Invalid transfer's ID!",
        self::INVALID_SEQ => "Invalid transfer's Seq!",
    ];

    public function getMsg(): string
    {
        return isset(self::MSG[$this->code]) ? self::MSG[$this->code] : '';
    }
}