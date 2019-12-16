<?php

namespace calque;

/**
 * Class pack
 *
 * @package calque
 * @author  Dmitrii Emelyanov <gilberg.vrn@gmail.com>
 * @date    10/22/19 8:21 PM
 */
class pack
{
    public static function deltaAddr($base, $trans)
    {
        // transition dest of 0 is special case
        if ($trans == 0) {
            return 0;
        }
        return $base - $trans;
    }

    const PACK_OUT_MASK = (1 << 4) - 1;

    public static function encodePackSize($transSize, int $outSize)
    {
        $rv = $transSize << 4;
        $rv |= $outSize;
        return $rv;
    }

    public static function decodePackSize($pack)
    {
        $pack = ord($pack);
        $transSize = $pack >> 4;
        $packSize = $pack & self::PACK_OUT_MASK;
        return [$transSize, $packSize];
    }

    const MAX_NUM_TRANS = (1 << 6) - 1;

    public static function encodeNumTrans(int $n)
    {
        if ($n <= self::MAX_NUM_TRANS) {
            return $n;
        }
        return 0;
    }

    public static function readPackedUint($data)
    {
        $rv = 0;
        $dataLen = strlen($data);
        for ($i = 0; $i < $dataLen; $i++) {
            $unpacked = unpack('Cbyte', $data[$i]);
            $shifted = $unpacked['byte'] << $i * 8;
            $rv |= $shifted;
        }

        return $rv;
    }
}