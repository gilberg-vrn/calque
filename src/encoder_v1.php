<?php

namespace calque;

/**
 * Class encoder_v1
 *
 * @package calque
 * @author  Dmitrii Emelyanov <gilberg.vrn@gmail.com>
 * @date    10/22/19 5:47 PM
 */
class encoder_v1
{
    const VERSION_V1      = 1;
    const ONE_TRANSITION  = 1 << 7;
    const TRANSITION_NEXT = 1 << 6;
    const STATE_FINAL     = 1 << 6;
    const FOOTER_SIZE_V1  = 16;

    /** @var writer */
    public $bw;

    /**
     * encoder_v1 constructor.
     *
     * @param writer $bw
     */
    public function __construct(writer $bw)
    {
        $this->bw = $bw;
    }

    public function start()
    {
        $header = pack('P', self::VERSION_V1);
        $header = str_pad($header, encoding::HEADER_SIZE, "\0");
        $n = $this->bw->write($header);

        if ($n != encoding::HEADER_SIZE) {
            throw new \Exception(sprintf("short write of header %d/%d", $n, encoding::HEADER_SIZE));
        }
    }

    public function finish(int $count, $rootAddr)
    {
        $footer = pack('P', $count);
        $footer = str_pad($footer, 8, "\0");
        $footer .= pack('P', $rootAddr);
        $footer = str_pad($footer, self::FOOTER_SIZE_V1, "\0");

        $n = $this->bw->write($footer);

        if ($n != self::FOOTER_SIZE_V1) {
            throw new \Exception(sprintf("short write of footer %d/%d", $n, self::FOOTER_SIZE_V1));
        }

        $this->bw->flush();

        return null;
    }

    public function encodeState(builderNode $s, int $lastAddr)
    {
        if (count($s->trans) == 0 && $s->final && $s->finalOutput == 0) {
            return [0, null];
        } elseif (count($s->trans) != 1 || $s->final) {
            return $this->encodeStateMany($s);
        } elseif (!$s->final && $s->trans[0]->out == 0 && $s->trans[0]->addr == $lastAddr) {
            return $this->encodeStateOneFinish($s, self::TRANSITION_NEXT);
        }
        return $this->encodeStateOne($s);
    }

    public function encodeStateOne(builderNode $s)
    {
        $start = $this->bw->counter;
        $outPackSize = 0;
        if ($s->trans[0]->out != 0) {
            $outPackSize = $this->bw->packedSize($s->trans[0]->out);
            $err = $this->bw->WritePackedUintIn($s->trans[0]->out, $outPackSize);
            if ($err != null) {
                return [0, $err];
            }
        }
        $delta = pack::deltaAddr($start, $s->trans[0]->addr);
        $transPackSize = $this->bw->packedSize($delta);
        $err = $this->bw->WritePackedUintIn($delta, $transPackSize);
        if ($err != null) {
            return [0, $err];
        }

        $packSize = pack::encodePackSize($transPackSize, $outPackSize);
        $err = $this->bw->WriteByte($packSize);
        if ($err != null) {
            return [0, $err];
        }

        return $this->encodeStateOneFinish($s, 0);
    }

    public function encodeStateOneFinish(builderNode $s, $next)
    {
        $enc = common::encodeCommon($s->trans[0]->in);

        // not a common input
        if ($enc == 0) {
            $err = $this->bw->WriteByte(ord($s->trans[0]->in));
            if ($err != null) {
                return [0, $err];
            }
        }

//        $codepoint = \IntlChar::ord($s->trans[0]->in);
//        $enc = $this->bw->packedSize($codepoint);
//        $this->bw->WritePackedUintIn($codepoint, $enc);

        $err = $this->bw->WriteByte(self::ONE_TRANSITION | $next | $enc);
        if ($err != null) {
            return [0, $err];
        }

        return [$this->bw->counter - 1, null];
    }

    public function encodeStateMany(builderNode $s)
    {
        $start = $this->bw->counter;
        $transPackSize = 0;
        $outPackSize = $this->bw->packedSize($s->finalOutput);
        $anyOutputs = $s->finalOutput != 0;
        foreach ($s->trans as $i => $v) {
            $delta = pack::deltaAddr($start, $s->trans[$i]->addr);
            $tsize = $this->bw->packedSize($delta);
            if ($tsize > $transPackSize) {
                $transPackSize = $tsize;
            }
            $osize = $this->bw->packedSize($s->trans[$i]->out);
            if ($osize > $outPackSize) {
                $outPackSize = $osize;
            }
            $anyOutputs = $anyOutputs || $s->trans[$i]->out != 0;
        }
        if (!$anyOutputs) {
            $outPackSize = 0;
        }

        if ($anyOutputs) {
            // output final value
            if ($s->final) {
                $err = $this->bw->WritePackedUintIn($s->finalOutput, $outPackSize);
                if ($err != null) {
                    return [0, $err];
                }
            }
            // output transition values (in reverse)
            for ($j = count($s->trans) - 1; $j >= 0; $j--) {
                $err = $this->bw->WritePackedUintIn($s->trans[$j]->out, $outPackSize);
                if ($err != null) {
                    return [0, $err];
                }
            }
        }

        // output transition dests (in reverse)
        for ($j = count($s->trans) - 1; $j >= 0; $j--) {
            $delta = pack::deltaAddr($start, $s->trans[$j]->addr);
            $err = $this->bw->WritePackedUintIn($delta, $transPackSize);
            if ($err != null) {
                return [0, $err];
            }
        }

        // output transition keys (in reverse)
        for ($j = count($s->trans) - 1; $j >= 0; $j--) {
            $err = $this->bw->WriteByte(ord($s->trans[$j]->in));
            if ($err != null) {
                return [0, $err];
            }
        }

        $packSize = pack::encodePackSize($transPackSize, $outPackSize);
        $err = $this->bw->WriteByte($packSize);
        if ($err != null) {
            return [0, $err];
        }

        $numTrans = pack::encodeNumTrans(count($s->trans));

        // if number of transitions wont fit in edge header byte
        // write out separately
        if ($numTrans == 0) {
            if (count($s->trans) == 256) {
                // this wouldn't fit in single byte, but reuse value 1
                // which would have always fit in the edge header instead
                $err = $this->bw->WriteByte(1);
                if ($err != null) {
                    return [0, $err];
                }
            } else {
                $err = $this->bw->WriteByte(count($s->trans));
                if ($err != null) {
                    return [0, $err];
                }
            }
        }

        // finally write edge header
        if ($s->final) {
            $numTrans |= self::STATE_FINAL;
        }
        $err = $this->bw->WriteByte($numTrans);
        if ($err != null) {
            return [0, $err];
        }

        return [$this->bw->counter - 1, null];
    }
}