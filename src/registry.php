<?php

namespace calque;

/**
 * Class registry
 *
 * @package calque
 * @author  Dmitrii Emelyanov <gilberg.vrn@gmail.com>
 * @date    10/22/19 3:30 PM
 */
class registry
{

    /** @var builderNodePool */
    public $builderNodePool;
    /** @var registryCell[] */
    public $table;
    /** @var int */
    public $tableSize;
    /** @var int */
    public $mruSize;
    /** @var int */
    protected $nSize;

    public function __construct($builderNodePool, $table, $tableSize, $mruSize)
    {
        $this->builderNodePool = $builderNodePool;
        $this->table = $table;
        $this->tableSize = $tableSize;
        $this->mruSize = $mruSize;
        $this->nSize = $tableSize * $mruSize;
    }

    public static function newRegistry(builderNodePool $p, int $tableSize, int $mruSize)
    {
        return new self($p, [], $tableSize, $mruSize);
    }

    public function entry(builderNode $node)
    {
        /** @var registryCache[] $tableCache */
        static $tableCache = [];

        if ($this->nSize == 0) {
            return [false, 0, null];
        }

        $bucket = $this->hash($node);
        $start = $this->mruSize * $bucket;
        $end = $start + $this->mruSize;
        $key = $start . '.' . $end;

        if (!isset($tableCache[$key])) {
            $tableCache[$key] = new registryCache($this->table, $start, $end - $start);
        }

        return $tableCache[$key]->entry($node, $this->builderNodePool);
    }

//    const PRIME = '1099511628211';
//    const BASE = '14695981039346656037';

    public function hash(builderNode $b): int
    {
        return crc32(serialize($b)) % $this->tableSize;
//        static $prime, $mask;
//        if ($prime === null) {
//            $prime = gmp_init('1099511628211', 10);
//            $mask = gmp_init('FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFF', 16);
//        }
//        if ($b->final) {
//            $final = 1;
//        } else {
//            $final = 0;
//        }
//
//        $h = gmp_init('14695981039346656037', 10);
//        $h = gmp_mul(gmp_pow($h, $final), $prime);
//        $h = gmp_mul(gmp_pow($h, $b->finalOutput), $prime);
//        $h = gmp_and($h, $mask);
//
//        foreach ($b->trans as $t) {
//            $h = gmp_mul(gmp_pow($h, \IntlChar::ord($t->in)), $prime);
//            $h = gmp_and($h, $mask);
//            $h = gmp_mul(gmp_pow($h, \IntlChar::ord($t->out)), $prime);
//            $h = gmp_and($h, $mask);
//            $h = gmp_mul(gmp_pow($h, $t->addr), $prime);
//            $h = gmp_and($h, $mask);
//        }
//
//        return gmp_intval(gmp_mod($h, $this->tableSize));
    }
}