<?php

namespace calque;

/**
 * Class encoding
 *
 * @package calque
 * @author  Dmitrii Emelyanov <gilberg.vrn@gmail.com>
 * @date    10/22/19 4:47 PM
 */
class encoding
{

    const HEADER_SIZE = 16;

    /**
     * @param int $ver
     * @param     $w
     *
     * @return encoder_utf
     */
    public static function loadEncoder(int $ver, $w)
    {
        return new encoder_utf(new writer($w));
    }

    public static function decodeHeader($header)
    {
        if (strlen($header) < self::HEADER_SIZE) {
            $err = sprintf("invalid header < 16 bytes");

            return [null, null, $err];
        }
        $unpacked = unpack('Pver/Ptyp', $header);
        $ver = $unpacked['ver'];
        $typ = $unpacked['typ'];

        return [$ver, $typ, null];
    }

    public static function loadDecoder(int $ver, string $data)
    {
        return new decoder_utf($data);
    }
}