<?php

namespace calque;

/**
 * Class builderNode
 *
 * @package calque
 * @author  Dmitrii Emelyanov <gilberg.vrn@gmail.com>
 * @date    10/22/19 3:10 PM
 */
class builderNode
{
    /** @var int */
    public $finalOutput = 0;
    /** @var transition[] */
    public $trans = [];
    /** @var bool */
    public $final;

    /**
     * intrusive linked list
     * @var builderNode
     */
    public $next;

    public function reset()
    {
        $this->final = false;
        $this->finalOutput = 0;
//        foreach ($this->trans as $i => $v) {
//            $this->trans[$i] = new transition();
//        }
        $this->trans = [];
        $this->next = null;
    }

    public function equiv(builderNode $o): bool
    {
        if ($this->final != $o->final) {
            return false;
        }
        if ($this->finalOutput != $o->finalOutput) {
            return false;
        }
        if (count($this->trans) != count($o->trans)) {
            return false;
        }
        foreach ($this->trans as $i => $ntrans) {
            $otrans = $o->trans[$i];
            if ($ntrans->in != $otrans->in) {
                return false;
            }
            if ($ntrans->addr != $otrans->addr) {
                return false;
            }
            if ($ntrans->out != $otrans->out) {
                return false;
            }
        }
        return true;
    }
}