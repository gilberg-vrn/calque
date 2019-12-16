<?php

namespace calque;

/**
 * Class builder
 *
 * @author Dmitrii Emelyanov <gilberg.vrn@gmail.com>
 * @date   10/22/19 3:06 PM
 */
class builder_v1
{
    const NONE_ADDR  = 1;
    const EMPTY_ADDR = 0;

    /** @var unfinishedNodes */
    public $unfinished;
    /** @var registry */
    public $registry;
    /** @var int[] */
    public $last;
    /** @var int */
    public $len;

    /** @var int */
    public $lastAddr;

    /** @var encoder_v1 */
    public $encoder;
    /** @var BuilderOpts */
    public $opts;

    /** @var builderNodePool */
    public $builderNodePool;

    public function __construct($unfinished, $registry, $builderNodePool, $opts, $lastAddr)
    {
        $this->unfinished = $unfinished;
        $this->registry = $registry;
        $this->builderNodePool = $builderNodePool;
        $this->opts = $opts;
        $this->lastAddr = $lastAddr;
    }

    /**
     * @param                  $w
     * @param BuilderOpts|null $opts
     *
     * @return builder
     * @throws \Exception
     */
    public static function newBuilder($w, BuilderOpts $opts = null)
    {
        if ($opts === null) {
            $opts = new BuilderOpts();
        }
        $builderNodePool = new builderNodePool();

        $rv = new Builder(
            self::newUnfinishedNodes($builderNodePool),
            registry::newRegistry($builderNodePool, $opts->RegistryTableSize, $opts->RegistryMRUSize),
            $builderNodePool,
            $opts,
            self::NONE_ADDR
        );

        $rv->encoder = encoding::loadEncoder($opts->Encoder, $w);
        $rv->encoder->start();

        return $rv;
    }

    protected static function newUnfinishedNodes(builderNodePool $p)
    {
        $rv = new unfinishedNodes(
            [], //make([]*builderNodeUnfinished, 0, 64),
            [], //make([]builderNodeUnfinished, 64),
            $p
        );
        $rv->pushEmpty(false);
        return $rv;
    }

    private $stats = [
        'cmnpfx' => 0,
        'compfr' => 0,
        'cplsky' => 0,
        'addsfx' => 0,
    ];
    private $cnt = 0;
    public function Insert($key, $val)
    {
        if ($key < $this->last) {
            throw new \OutOfRangeException("{$key} must be greater than {$this->last}");
        }

        if (mb_strlen($key) === 0) {
            $this->len = 1;
            $this->unfinished->setRootOutput($val);

            return null;
        }

        $timer = microtime(1);
        list($prefixLen, $out) = $this->unfinished->findCommonPrefixAndSetOutput($key, $val);
        $findCommonPrefix = microtime(1) - $timer;
        $this->stats['cmnpfx'] += $findCommonPrefix;
        $timer = microtime(1);
        $this->len++;
        $err = $this->compileFrom($prefixLen);
        $compileFrom = microtime(1) - $timer;
        $this->stats['compfr'] += $compileFrom;
        $timer = microtime(1);
        if ($err != null) {
            return $err;
        }
        $this->copyLastKey($key);
        $copyLastKey = microtime(1) - $timer;
        $this->stats['cplsky'] += $copyLastKey;
        $timer = microtime(1);
        $this->unfinished->addSuffix(mb_substr($key, $prefixLen), $out);
        $addSuffix = microtime(1) - $timer;
        $this->stats['addsfx'] += $addSuffix;
        $this->cnt++;
        return null;
    }

    public function copyLastKey($key)
    {
        $this->last = $key;
    }

    /**
     * Close MUST be called after inserting all values.
     * @throws \Exception
     */
    public function Close()
    {
//        error_log(sprintf('cmnpfx:%2.4f // compfr:%2.4f // cplsky:%2.4f // addsfx:%2.4f', $this->stats['cmnpfx'] / $this->cnt, $this->stats['compfr'] / $this->cnt, $this->stats['cplsky'] / $this->cnt, $this->stats['addsfx'] / $this->cnt));
        $err = $this->compileFrom(0);
        if ($err != null) {
            return $err;
        }
        $root = $this->unfinished->popRoot();
        list($rootAddr, $err) = $this->compile($root);
        if ($err != null) {
            return $err;
        }

        return $this->encoder->finish($this->len, $rootAddr);
    }

    public function compileFrom(int $iState)
    {
        $addr = self::NONE_ADDR;

        while ($iState + 1 < count($this->unfinished->stack)) {
            if ($addr == self::NONE_ADDR) {
                $node = $this->unfinished->popEmpty();
            } else {
                $node = $this->unfinished->popFreeze($addr);
            }
            list($addr, $err) = $this->compile($node);
            if ($err != null) {
                return null;
            }
        }
        $this->unfinished->topLastFreeze($addr);
        return null;
    }

    public function compile(builderNode $node)
    {
        if ($node->final && count($node->trans) == 0 &&
            $node->finalOutput == 0) {
            return [0, null];
        }
        list($found, $addr, $entry) = $this->registry->entry($node);
        if ($found) {
            return [$addr, null];
        }
        list($addr, $err) = $this->encoder->encodeState($node, $this->lastAddr);
        if ($err != null) {
            return [0, $err];
        }

        $this->lastAddr = $addr;
        $entry->addr = $addr;
        return [$addr, null];
    }
}