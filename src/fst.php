<?php

namespace calque;

/**
 * Class fst
 *
 * @package calque
 * @author  Dmitrii Emelyanov <gilberg.vrn@gmail.com>
 * @date    10/24/19 3:18 PM
 */
class fst
{

    const DOT_HEADER = '
digraph automaton {
    labelloc="l";
    labeljust="l";
    rankdir="LR";
';
    const DOT_FOOTER = '}
';

    /** @var resource */
    public $f;
    /** @var int */
    public $ver;
    /** @var int */
    public $len;
    /** @var int */
    public $typ;
    /** @var string */
    public $data;
    /** @var decoder_v1 */
    public $decoder;

    public function __construct($data, $closer = null)
    {
        $this->data = $data;
        $this->f = $closer;

        list($this->ver, $this->typ, $err) = encoding::decodeHeader($data);
        if ($err != null) {
            return [null, $err];
        }

        $this->decoder = encoding::loadDecoder($this->ver, $data);

        $this->len = $this->decoder->getLen();
    }

    public function Get($input)
    {
        return $this->getInternal($input, null);
    }

    private function getInternal($input, fstState $prealloc = null)
    {
        $total = 0;
        $curr = $this->decoder->getRoot();
        /** @var fstState $state */
        [$state, $err] = $this->decoder->stateAt($curr, $prealloc);
        if ($err != null) {
            return [0, false, $err];
        }

        if (is_array($input)) {
            $inputLen = count($input);
            $chars = $input;
        } else {
            $inputLen = mb_strlen($input);
            $chars = preg_split('//u', $input, -1, PREG_SPLIT_NO_EMPTY);
        }

        for ($i = 0; $i < $inputLen; $i++) {
            [ , $curr, $output ] = $state->TransitionFor($chars[$i]);
            if ($curr == builder::NONE_ADDR) {
                return [0, false, null];
            }

            [$state, $err] = $this->decoder->stateAt($curr, $state);
            if ($err != null) {
                return [0, false, $err];
            }

            $total += $output;
        }

        if ($state->Final()) {
            $total += $state->FinalOutput();
            return [$total, true, null];
        }
        return [0, false, null];
    }

    public function Debug(callable $func)
    {
        $addr = $this->decoder->getRoot();
        $set = []; // max $addr
        $stack = [$addr];

        $stateNumber = 0;
        $addr = array_pop($stack);
        while ($addr != builder::NONE_ADDR) {
            if (isset($set[$addr])) {
                $addr = array_pop($stack);
                if ($addr === null) {
                    $addr = builder::NONE_ADDR;
                }
                continue;
            }
            $set[$addr] = true;
            /** @var fstState_utf $state */
            list($state, $err) = $this->decoder->stateAt($addr, null);
            if ($err != null) {
                return $err;
            }
            $err = $func($stateNumber, $state);
            if ($err != null) {
                return $err;
            }

            for ($i = 0; $i < $state->NumTransitions(); $i++) {
                $tChar = $state->TransitionAt($i);
                list(, $dest,) = $state->TransitionFor($tChar);
                $stack[] = $dest;
            }
            $stateNumber++;
            $addr = array_pop($stack);
            if ($addr === null) {
                $addr = builder::NONE_ADDR;
            }
        }

        return null;
    }

    public function dotToWriter()
    {
        echo self::DOT_HEADER;

        $this->Debug(function(int $n, fstState $state) {
            echo sprintf("%s", $state->DotString($n));
        });

        echo self::DOT_FOOTER;
    }
}