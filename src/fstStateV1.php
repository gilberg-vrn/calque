<?php

namespace calque;

/**
 * Class fstStateV1
 *
 * @package calque
 * @author  Dmitrii Emelyanov <gilberg.vrn@gmail.com>
 * @date    10/24/19 3:48 PM
 */
class fstStateV1
    implements fstState
{

    /** @var string */
    public $data;
    /** @var int */
    public $top;
    /** @var int */
    public $bottom;
    /** @var int */
    public $numTrans;

    // single trans only
    /** @var int */
    public $singleTransChar;
    /** @var bool */
    public $singleTransNext;
    /** @var int */
    public $singleTransAddr;
    /** @var int */
    public $singleTransOut;

    // shared
    /** @var int */
    public $transSize;
    /** @var int */
    public $outSize;

    // multiple trans only
    /** @var bool */
    public $final;
    /** @var int */
    public $transTop;
    /** @var int */
    public $transBottom;
    /** @var int */
    public $destTop;
    /** @var int */
    public $destBottom;
    /** @var int */
    public $outTop;
    /** @var int */
    public $outBottom;
    /** @var int */
    public $outFinal;

    /** @return  int */
    public function Address()
    {
        return $this->top;
    }

    /** @return  bool */
    public function Final()
    {
        return $this->final;
    }

    /** @return  int */
    public function FinalOutput()
    {
        if ($this->final && $this->outSize > 0) {
            $data = substr($this->data, $this->outFinal, $this->outSize);
            return pack::readPackedUint($data);
        }

        return 0;
    }

    /** @return  int */
    public function NumTransitions()
    {
        return $this->numTrans;
    }

    /**
     * @param int $b
     *
     * @return array
     */
    public function TransitionFor($b)
    {
        if ($this->isEncodedSingle()) {
            if ($this->singleTransChar == $b) {
                return [0, $this->singleTransAddr, $this->singleTransOut];
            }
            return [-1, builder::NONE_ADDR, 0];
        }
        $transitionKeys = substr($this->data, $this->transBottom, $this->transTop - $this->transBottom);
        $pos = strpos($transitionKeys, chr($b));
        if ($pos === false) {
            return [-1, builder::NONE_ADDR, 0];
        }
        $transDests = substr($this->data, $this->destBottom, $this->destTop - $this->destBottom);
        $dest = pack::readPackedUint(substr($transDests, $pos * $this->transSize, $this->transSize));
        if ($dest > 0) {
            // convert delta
            $dest = $this->bottom - $dest;
        }
        $transVals = substr($this->data, $this->outBottom, $this->outTop - $this->outBottom);
        $out = null;
        if ($this->outSize > 0) {
            $out = pack::readPackedUint(substr($transVals, $pos * $this->outSize, $this->outSize));

        }
        return [$this->numTrans - $pos - 1, $dest, $out];
    }

    /**
     * @param int $i
     *
     * @return  int
     */
    public function TransitionAt(int $i)
    {
        if ($this->isEncodedSingle()) {
            return $this->singleTransChar;
        }
        $transitionKeys = substr($this->data, $this->transBottom, $this->transTop - $this->transBottom);
        return ord(substr($transitionKeys, $this->numTrans - $i - 1, 1));
    }

    protected function isEncodedSingle()
    {
        $b = ord(substr($this->data, $this->top, 1));
        if ($b >> 7 > 0) {
            return true;
        }

        return false;
    }

    public function at(&$data, int $addr)
    {
        $this->data = &$data;
        if ($addr == builder::EMPTY_ADDR) {
            return $this->atZero();
        } elseif ($addr == builder::NONE_ADDR) {
            return $this->atNone();
        }
        if ($addr > strlen($data) || $addr < 16) {
            return sprintf("invalid address %d/%d", $addr, strlen($data));
        }
        $this->top = $addr;
        $this->bottom = $addr;
        if ($this->isEncodedSingle()) {
            return $this->atSingle($data, $addr);
        }
        return $this->atMulti($data, $addr);
    }

    private function atZero()
    {
        $this->top = 0;
        $this->bottom = 1;
        $this->numTrans = 0;
        $this->final = true;
        $this->outFinal = 0;

        return null;
    }

    private function atNone()
    {
        $this->top = 0;
        $this->bottom = 1;
        $this->numTrans = 0;
        $this->final = false;
        $this->outFinal = 0;

        return null;
    }

    public function atSingle($data, int $addr)
    {
        // handle single transition case
        $this->numTrans = 1;
        $this->singleTransNext = (ord($data[$this->top]) & encoder_v1::TRANSITION_NEXT) > 0;
        $this->singleTransChar = ord($data[$this->top]) & common::MAX_COMMON;
        if ($this->singleTransChar == 0) {
            $this->bottom--; // extra byte for uncommon
            $this->singleTransChar = ord($data[$this->bottom]);
        } else {
            $this->singleTransChar = common::decodeCommon($this->singleTransChar);
        }

//        $packedSize = ord($data[$this->top]) & common::MAX_COMMON;
//        $this->bottom -= $packedSize;
//        $this->singleTransChar = pack::readPackedUint(substr($data, $this->bottom, $packedSize));

        if ($this->singleTransNext) {
            // now we know the bottom, can compute next addr
            $this->singleTransAddr = $this->bottom - 1;
            $this->singleTransOut = 0;
        } else {
            $this->bottom--; // extra byte with pack sizes
            list($this->transSize, $this->outSize) = pack::decodePackSize($data[$this->bottom]);
            $this->bottom -= $this->transSize; // exactly one trans
            $this->singleTransAddr = pack::readPackedUint(substr($data, $this->bottom, $this->transSize));
            if ($this->outSize > 0) {
                $this->bottom -= $this->outSize; // exactly one out (could be length 0 though)
                $this->singleTransOut = pack::readPackedUint(substr($data, $this->bottom, $this->outSize));
            } else {
                $this->singleTransOut = 0;
            }
            // need to wait till we know bottom
            if ($this->singleTransAddr != 0) {
                $this->singleTransAddr = $this->bottom - $this->singleTransAddr;
            }
        }
        return null;
    }

    public function atMulti($data, int $addr)
    {
        // +===+===+===+===+===+===+===+===+
        // | 7 | 6 | 5 | 4 | 3 | 2 | 1 | 0 |
        // +---+---+---+---+---+---+---+---+
        // | f |                           |
        // | i |                           |
        // | n |     Num trans or 0 if     |
        // | a |        more than 63       |
        // | l |                           |
        // +===+===+===+===+===+===+===+===+
        // | 7 | 6 | 5 | 4 | 3 | 2 | 1 | 0 |
        // +---+---+---+---+---+---+---+---+
        // |    Num trans OR missed        |
        // +===+===+===+===+===+===+===+===+
        // | 7 | 6 | 5 | 4 | 3 | 2 | 1 | 0 |
        // +---+---+---+---+---+---+---+---+
        // | transPackSize |  outPackSize  |
        // +===+===+===+===+===+===+===+===+
        // | 7 | 6 | 5 | 4 | 3 | 2 | 1 | 0 |
        // +---+---+---+---+---+---+---+---+
        // |                               |
        // …       trans char bytes        …
        // |                               |
        // +===+===+===+===+===+===+===+===+
        // | 7 | 6 | 5 | 4 | 3 | 2 | 1 | 0 |
        // +---+---+---+---+---+---+---+---+
        // |                               |
        // …       trans delta bytes       …
        // |                               |
        // +===+===+===+===+===+===+===+===+
        // | 7 | 6 | 5 | 4 | 3 | 2 | 1 | 0 |
        // +---+---+---+---+---+---+---+---+
        // |                               |
        // …         output values         …
        // |           if present          |
        // +===+===+===+===+===+===+===+===+
        // | 7 | 6 | 5 | 4 | 3 | 2 | 1 | 0 |
        // +---+---+---+---+---+---+---+---+
        // |                               |
        // …          final output         …
        // |           if present          |
        // +===+===+===+===+===+===+===+===+
        // handle multiple transitions case
        $this->final = (ord($data[$this->top]) & encoder_v1::STATE_FINAL) > 0;
        $this->numTrans = ord($data[$this->top]) & pack::MAX_NUM_TRANS;
        if ($this->numTrans == 0) {
            $this->bottom--; // extra byte for number of trans
            $this->numTrans = ord($data[$this->bottom]);
            if ($this->numTrans == 1) {
                // can't really be 1 here, this is special case that means 256
                $this->numTrans = 256;
            }
        }
        $this->bottom--; // extra byte with pack sizes
        list($this->transSize, $this->outSize) = pack::decodePackSize($data[$this->bottom]);

        $this->transTop = $this->bottom;
        $this->bottom -= $this->numTrans; // one byte for each transition
        $this->transBottom = $this->bottom;

        $this->destTop = $this->bottom;
        $this->bottom -= $this->numTrans * $this->transSize;
        $this->destBottom = $this->bottom;

        if ($this->outSize > 0) {
            $this->outTop = $this->bottom;
            $this->bottom -= $this->numTrans * $this->outSize;
            $this->outBottom = $this->bottom;
            if ($this->final) {
                $this->bottom -= $this->outSize;
                $this->outFinal = $this->bottom;
            }
        }
        return null;
    }
}