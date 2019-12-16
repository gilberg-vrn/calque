<?php

namespace calque;

/**
 * Class fst
 *
 * @package calque
 * @author  Dmitrii Emelyanov <gilberg.vrn@gmail.com>
 * @date    10/24/19 3:18 PM
 */
class fst_v1
{

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
        list($state, $err) = $this->decoder->stateAt($curr, $prealloc);
        if ($err != null) {
            return [0, false, $err];
        }
        $inputLen = strlen($input);

        for ($i = 0; $i < $inputLen; $i++) {
            $c = ord(substr($input, $i, 1));
            list(, $curr, $output) = $state->TransitionFor($c);
            if ($curr == builder::NONE_ADDR) {
                return [0, false, null];
            }

            list($state, $err) = $this->decoder->stateAt($curr, $state);
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
}