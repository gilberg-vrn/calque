<?php

namespace calque;

/**
 * Class fstState
 *
 * @package calque
 * @author  Dmitrii Emelyanov <gilberg.vrn@gmail.com>
 * @date    10/24/19 3:37 PM
 */
interface fstState
{

    /** @return  int */
    public function Address();

    /** @return  bool */
    public function Final();

    /** @return  int */
    public function FinalOutput();

    /** @return  int */
    public function NumTransitions();

    /**
     * @param int $b
     *
     * @return array
     */
    public function TransitionFor($b);

    /**
     * @param int $i
     *
     * @return  int
     */
    public function TransitionAt(int $i);
}