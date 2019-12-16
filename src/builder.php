<?php

namespace calque;

/**
 * Class builder
 *
 * @author Dmitrii Emelyanov <gilberg.vrn@gmail.com>
 * @date   10/22/19 3:06 PM
 */
class builder
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

        $keyChars = preg_split('//u', $key, -1, PREG_SPLIT_NO_EMPTY);
        $keyCharsCount = count($keyChars);

        list($prefixLen, $out) = $this->unfinished->findCommonPrefixAndSetOutput($keyChars, $keyCharsCount, $val);
        $this->len++;
        $err = $this->compileFrom($prefixLen);
        if ($err != null) {
            return $err;
        }

        $this->copyLastKey($key);
        $this->unfinished->addSuffix($keyChars, $prefixLen, $keyCharsCount, $out);

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

        while ($iState + 1 < $this->unfinished->stackAddr) {
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
        if ($node->final
            && empty($node->trans)
            && $node->finalOutput == 0) {
            return [0, null];
        }
        /** @var registryCell $entry */
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