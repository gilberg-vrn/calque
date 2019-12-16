<?php

namespace calque;

/**
 * Class unfinishedNodes
 *
 * @package calque
 * @author  Dmitrii Emelyanov <gilberg.vrn@gmail.com>
 * @date    10/22/19 3:17 PM
 */
class unfinishedNodes
{
    /** @var builderNodeUnfinished[] */
    public $stack;
    public $stackAddr = 0;

    // cache allocates a reasonable number of builderNodeUnfinished
    // objects up front and tries to keep reusing them
    // because the main data structure is a stack, we assume the
    // same access pattern, and don't track items separately
    // this means calls get() and pushXYZ() must be paired,
    // as well as calls put() and popXYZ()
    /** @var builderNodeUnfinished[] */
    public $cache;

    public $cacheSize = 128;

    /** @var builderNodePool */
    public $builderNodePool;

    /**
     * unfinishedNodes constructor.
     *
     * @param array           $stack
     * @param array           $cache
     * @param builderNodePool $builderNodePool
     */
    public function __construct(array $stack, array $cache, builderNodePool $builderNodePool)
    {
        $this->stack = $stack;
        $this->cache = $cache;
        $this->builderNodePool = $builderNodePool;
    }

    public function pushEmpty(bool $final)
    {
        $next = $this->get();
        $next->node = $this->builderNodePool->Get();
        $next->node->final = $final;
        $this->stack[$this->stackAddr++] = $next;
    }

    public function get()
    {
        $stackSize = $this->stackAddr;
//        $stackSize = count($this->stack);
        if ($stackSize < $this->cacheSize && isset($this->cache[$stackSize])) {
            return $this->cache[$stackSize];
        }
        // full now allocate a new one
        return new builderNodeUnfinished();// &builderNodeUnfinished;
    }

    private function put()
    {
//        $stackSize = count($this->stack);
        if ($this->stackAddr >= $this->cacheSize) {
            return;
            // do nothing, not part of cache
        }
        $this->cache[$this->stackAddr] = new builderNodeUnfinished();
    }

    public function setRootOutput($out)
    {
        $this->stack[0]->node->final = true;
        $this->stack[0]->node->finalOutput = $out;
    }

    public function findCommonPrefixAndSetOutput($chars, $charsCount, $out)
    {
        $i = 0;
        $stackSize = $this->stackAddr;
//        $stackSize = count($this->stack);
        while ($i < $charsCount) {
            if ($i >= $stackSize) {
                break;
            }

            if (!$this->stack[$i]->hasLastT) {
                break;
            }
            if ($this->stack[$i]->lastIn == $chars[$i]) {
                $commonPre = $this->stack[$i]->lastOut < $out ? $this->stack[$i]->lastOut : $out; //$this->outputPrefix($this->stack[$i]->lastOut, $out);
                $addPrefix = $this->stack[$i]->lastOut - $commonPre; //$this->outputSub($this->stack[$i]->lastOut, $commonPre);
                $out = $out - $commonPre; //$this->outputSub($out, $commonPre);
                $this->stack[$i]->lastOut = $commonPre;
                $i++;
            } else {
                break;
            }

            if ($addPrefix !== 0) {
                $this->stack[$i]->addOutputPrefix($addPrefix);
            }
        }

        return [$i, $out];
    }

    public function outputPrefix($l, int $r): int
    {
        if ($l < $r) {
            return $l;
        }
        return $r;
    }


    public function outputSub($l, int $r)
    {
        return $l - $r;
    }

    public function addSuffix($bsChars, $offset, $bsCharsCount, int $out)
    {
        if ($bsCharsCount == 0) {
            return;
        }
        $last = $this->stackAddr - 1;
//        $last = count($this->stack) - 1;
        $this->stack[$last]->hasLastT = true;
        $this->stack[$last]->lastIn = $bsChars[$offset + 0];
        $this->stack[$last]->lastOut = $out;

        for ($i = $offset + 1; $i < $bsCharsCount; $i++) {
            $next = $this->get();
            $next->node = $this->builderNodePool->Get();
            $next->hasLastT = true;
            $next->lastIn = $bsChars[$i];
            $next->lastOut = 0;
            $this->stack[$this->stackAddr++] = $next;
        }
        $this->pushEmpty(true);
    }

    public function popRoot()
    {
        $unfinished = $this->stack[--$this->stackAddr];
//        @TODO: check, why array_pop has same performance
//        $unfinished = array_pop($this->stack);
//        $this->stackAddr--;
        $rv = $unfinished->node;
        $this->put();

        return $rv;
    }

    public function popEmpty()
    {
        $unfinished = $this->stack[--$this->stackAddr];
//        $unfinished = array_pop($this->stack);
//        $this->stackAddr--;
        $rv = $unfinished->node;
        $this->put();

        return $rv;
    }

    public function popFreeze(int $addr)
    {
        $unfinished = $this->stack[--$this->stackAddr];
//        $unfinished = array_pop($this->stack);
//        $this->stackAddr--;
        $unfinished->lastCompiled($addr);
        $rv = $unfinished->node;
        $this->put();
        return $rv;
    }

    public function topLastFreeze(int $addr)
    {
//        $last = count($this->stack) - 1;
        $this->stack[$this->stackAddr - 1]->lastCompiled($addr);
    }
}