<?php

namespace Proto\Socket\Transfer\Exception;

class TransferException extends \Exception
{
    const PARSING_ERROR = 1;

    const MSG = [
        self::PARSING_ERROR => "Parsing error!",
    ];

    public function getMsg(): string
    {
        return isset(self::MSG[$this->code]) ? self::MSG[$this->code] : '';
    }
}