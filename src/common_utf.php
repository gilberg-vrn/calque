<?php

namespace calque;

/**
 * Class common
 *
 * @package calque
 * @author  Dmitrii Emelyanov <gilberg.vrn@gmail.com>
 * @date    10/22/19 8:27 PM
 */
class common_utf
{

    const MAX_COMMON = (1 << 5) - 1;

    public static function encodeCommon($in)
    {
        return isset(self::$commonInputs[$in]) ? self::$commonInputs[$in] : false;
    }

    public static function decodeCommon($in)
    {
        return isset(self::$commonInputsInv[$in]) ? self::$commonInputsInv[$in] : false;
    }

    protected static $commonInputs = [
        'e' => 0,
        't' => 1,
        'a' => 2,
        'o' => 3,
        'i' => 4,
        'n' => 5,
        's' => 6,
        'r' => 7,
        'h' => 8,
        'l' => 9,
        'd' => 10,
        'c' => 11,
        'u' => 12,
        'm' => 13,
        'f' => 14,
        'p' => 15,
        'о' => 16,
        'е' => 17,
        'а' => 18,
        'и' => 19,
        'н' => 20,
        'т' => 21,
        'с' => 22,
        'р' => 23,
        'в' => 24,
        'л' => 25,
        'к' => 26,
        'м' => 27,
        'д' => 28,
        'п' => 29,
        'у' => 30,
        'я' => 31,
    ];

    protected static $commonInputsInv = [
        0 => 'e',
        1 => 't',
        2 => 'a',
        3 => 'o',
        4 => 'i',
        5 => 'n',
        6 => 's',
        7 => 'r',
        8 => 'h',
        9 => 'l',
        10 => 'd',
        11 => 'c',
        12 => 'u',
        13 => 'm',
        14 => 'f',
        15 => 'p',
        16 => 'о',
        17 => 'е',
        18 => 'а',
        19 => 'и',
        20 => 'н',
        21 => 'т',
        22 => 'с',
        23 => 'р',
        24 => 'в',
        25 => 'л',
        26 => 'к',
        27 => 'м',
        28 => 'д',
        29 => 'п',
        30 => 'у',
        31 => 'я',
    ];
}