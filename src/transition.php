<?php

namespace calque;

/**
 * Class transition
 *
 * @package calque
 * @author  Dmitrii Emelyanov <gilberg.vrn@gmail.com>
 * @date    10/22/19 3:11 PM
 */
class transition
{
    /** @var int */
    public $out;
    /** @var int */
    public $addr;
    /** @var int */
    public $in;

    public function __construct($transIn, $transOut, $addr)
    {
        $this->in = $transIn;
        $this->out = $transOut;
        $this->addr = $addr;
    }
}