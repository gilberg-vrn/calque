<?php

namespace calque;

/**
 * Class builderNodePool
 *
 * @package calque
 * @author  Dmitrii Emelyanov <gilberg.vrn@gmail.com>
 * @date    10/22/19 3:09 PM
 */
class builderNodePool
{
    /** @var builderNode */
    public $head;

    public function Get()
    {
        if ($this->head === null) {
            return new builderNode();
        }
        $head = $this->head;
        $this->head = $this->head->next;

        return $head;
    }

    public function Put(builderNode $v = null)
    {
        if ($v == null) {
            return;
        }
        $v->reset();
        $v->next = $this->head;
        $this->head = $v;
    }
}