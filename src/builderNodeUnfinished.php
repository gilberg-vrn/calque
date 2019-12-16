<?php

namespace calque;

/**
 * Class builderNodeUnfinished
 *
 * @package calque
 * @author  Dmitrii Emelyanov <gilberg.vrn@gmail.com>
 * @date    10/22/19 3:29 PM
 */
class builderNodeUnfinished
{
    /** @var builderNode */
    public $node;
    /** @var int */
    public $lastOut;
    /** @var int */
    public $lastIn;
    /** @var bool */
    public $hasLastT;

    /**
     * builderNodeUnfinished constructor.
     */
    public function __construct()
    {
    }

    public function lastCompiled($addr)
    {
        if ($this->hasLastT) {
            $transIn = $this->lastIn;
            $transOut = $this->lastOut;
            $this->hasLastT = false;
            $this->lastOut = 0;
            $this->node->trans[] = new transition(
                $transIn,
                $transOut,
                $addr
            );
        }
    }

    public function outputCat($l, int $r)
    {
        return $l + $r;
    }

    public function addOutputPrefix($prefix)
    {
        if ($this->node->final) {
            $this->node->finalOutput = $this->outputCat($prefix, $this->node->finalOutput);
        }
        foreach ($this->node->trans as $i => $v) {
            $this->node->trans[$i]->out = $this->outputCat($prefix, $this->node->trans[$i]->out);
        }
        if ($this->hasLastT) {
            $this->lastOut = $this->outputCat($prefix, $this->lastOut);
        }
    }
}