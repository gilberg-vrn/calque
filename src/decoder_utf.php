<?php

namespace calque;

/**
 * Class decoder
 *
 * @package calque
 * @author  Dmitrii Emelyanov <gilberg.vrn@gmail.com>
 * @date    10/24/19 3:26 PM
 */
class decoder_utf
{
    public $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function getRoot()
    {
        if (strlen($this->data) < encoder_v1::FOOTER_SIZE_V1) {
            return 0;
        }
        $footer = substr($this->data, strlen($this->data) - encoder_v1::FOOTER_SIZE_V1);
        $unpacked = unpack('Plength/Proot', $footer);

        return $unpacked['root'];
    }

    public function getLen()
    {
        if (strlen($this->data) < encoder_v1::FOOTER_SIZE_V1) {
            return 0;
        }
        $footer = substr($this->data, strlen($this->data) - encoder_v1::FOOTER_SIZE_V1);
        $unpacked = unpack('Plength', $footer);

        return $unpacked['length'];
    }

    public function stateAt(int $addr, fstState $prealloc = null)
    {
        $state = new fstState_utf();

        $err = $state->at($this->data, $addr);
        if ($err != null) {
            return [null, $err];
        }
        return [$state, null];
    }
}