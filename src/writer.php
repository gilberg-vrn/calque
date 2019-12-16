<?php

namespace calque;

/**
 * Class writer
 *
 * @package calque
 * @author  Dmitrii Emelyanov <gilberg.vrn@gmail.com>
 * @date    10/22/19 5:46 PM
 */
class writer
{
    const BUFFER_LIMIT = 4096;

    /** @var resource */
    public $w;

    /** @var int */
    public $counter;

    public $buffer = '';
    public $bufferSize = 0;

    /**
     * writer constructor.
     *
     * @param $w
     * @param $counter
     */
    public function __construct($w, $counter = 0)
    {
        $this->w = $w;
        $this->counter = $counter;
    }

    public function write(string $data)
    {
        $n = strlen($data);
        $this->buffer .= $data;
        $this->bufferSize += $n;
        if ($this->bufferSize < self::BUFFER_LIMIT) {
            $this->counter += $n;
            return $n;
        }
        $r = fwrite($this->w, $this->buffer);
        if ($r === false) {
            return false;
        }
        $this->counter += $n;
        $this->buffer = '';
        $this->bufferSize = 0;

        return $n;
    }

    private function internalWrite(string $data)
    {
//        error_log('write ' . strlen($data) .  ': ' . str_pad(decbin(ord($data)), 8, '0', STR_PAD_LEFT) . ' at ' . $this->counter);
        $this->buffer .= $data;
        $this->bufferSize++;
        if ($this->bufferSize < self::BUFFER_LIMIT) {
            $this->counter++;
            return 1;
        }
        $r = fwrite($this->w, $this->buffer);
        if ($r === false) {
            return false;
        }
        $this->counter++;
        $this->buffer = '';
        $this->bufferSize = 0;

        return 1;
    }

    public function flush()
    {
        if ($this->counter) {
            fwrite($this->w, $this->buffer);
            $this->buffer = '';
            $this->bufferSize = 0;
        }
        fflush($this->w);
    }

    public function packedSize(int $n)
    {
        if ($n < (256)) { // 1 << 8 === 256
            return 1;
        } elseif ($n < (65536)) { // 1 << 16 === 65536
            return 2;
        } elseif ($n < (16777216)) { // 1 << 24 === 16777216
            return 3;
        } elseif ($n < (4294967296)) { // 1 << 32 === 4294967296
            return 4;
        } elseif ($n < (1099511627776)) { // 1 << 40 === 1099511627776
            return 5;
        } elseif ($n < (281474976710656)) { // 1 << 48 === 281474976710656
            return 6;
        } elseif ($n < (72057594037927936)) { // 1 << 56 === 72057594037927936
            return 7;
        }
        return 8;
    }


    public function WriteByte($c)
    {
        $n = $this->internalWrite(chr($c));
//        $byte = pack('C', $c);
//        $n = $this->write($byte);
        if ($n === false) {
            return 1;
        }
        return null;
    }

    public function WriteUtf($utfChar)
    {
//        $utfCharLen = strlen($utfChar);
//        for ($i = 0; $i < $utfCharLen; $i++) {
        $i = 0;
        while (isset($utfChar[$i])) {
            $n = $this->internalWrite($utfChar[$i++]);
//            $byte = pack('C', ord($utfChar[$i++]));
//            $n = $this->write($byte);
            if ($n === false) {
                return 1;
            }
        }
        return null;
    }

    public function WritePackedUintIn($v, $n)
    {
        for ($shift = 0; $shift < $n * 8; $shift += 8) {
            $err = $this->WriteByte(($v >> $shift) & 0xFF);
            if ($err != null) {
                return $err;
            }
        }

        return null;
    }

    public function WritePackedUint($v)
    {
        $n = $this->packedSize($v);

        return $this->WritePackedUintIn($v, $n);
    }
}