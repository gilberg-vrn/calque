<?php

namespace calque;

/**
 * Class registryCache
 *
 * @package calque
 * @author  Dmitrii Emelyanov <gilberg.vrn@gmail.com>
 * @date    10/22/19 6:55 PM
 */
class registryCache
{

    public $r;
    protected $size;
    protected $start;

    /**
     * registryCache constructor.
     *
     * @param registryCell[] $table
     * @param int            $start
     * @param int            $size
     */
    public function __construct(&$table, $start, $size)
    {
        $this->r = &$table;
        $this->size = $size;
        $this->start = $start;
        $this->Reset();
    }

    public function Reset()
    {
        for ($i = 0; $i < $this->size; $i++) {
            if (!isset($this->r[$i + $this->start])) {
                $this->r[$i + $this->start] = new registryCell();
            }
        }
    }

    public function entry(builderNode $node, builderNodePool $pool)
    {
        if ($this->size == 1) {
            if ($this->r[$this->start]->node != null && $this->r[$this->start]->node->equiv($node)) {
                return [true, $this->r[$this->start]->addr, null];
            }
            $pool->Put($this->r[$this->start]->node);
            $this->r[$this->start]->node = $node;
            return [false, 0, &$this->r[$this->start]];
        }
        foreach ($this->r as $i => $v) {
            if ($i < $this->start) {
                continue;
            }
            if ($i > $this->start+$this->size) {
                continue;
            }
            if ($this->r[$i]->node != null && $this->r[$i]->node->equiv($node)) {
                $addr = $this->r[$i]->addr;
                $this->promote($i);
                return [true, $addr, null];
            }
        }
        // no match
        $last = $this->start+$this->size - 1;
        $pool->Put($this->r[$last]->node);
        $this->r[$last]->node = $node; // discard LRU
        $this->promote($last);

        return [false, 0, &$this->r[$this->start]];

    }

    public function promote(int $i)
    {
        while ($i > $this->start) {
            $this->swap($i - 1, $i);
            $i--;
        }
    }

    public function swap($i, int $j)
    {
        list($this->r[$i], $this->r[$j]) = [$this->r[$j], $this->r[$i]];
    }
}