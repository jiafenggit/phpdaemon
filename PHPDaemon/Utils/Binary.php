<?php
namespace PHPDaemon\Utils;

use PHPDaemon\Core\Daemon;
use PHPDaemon\Core\Debug;

/**
 * Binary
 * @package PHPDaemon\Utils
 * @author  Vasily Zorin <maintainer@daemon.io>
 */
class Binary
{
    use \PHPDaemon\Traits\ClassWatchdog;
    use \PHPDaemon\Traits\StaticObjectWatchdog;

    /**
     * Build structure of labels
     * @param  string $q Dot-separated labels list
     * @return string
     */
    public static function labels($q)
    {
        $e = explode('.', $q);
        $r = '';
        for ($i = 0, $s = sizeof($e); $i < $s; ++$i) {
            $r .= chr(mb_orig_strlen($e[$i])) . $e[$i];
        }
        if (mb_orig_substr($r, -1) !== "\x00") {
            $r .= "\x00";
        }
        return $r;
    }

    /**
     * Parse structure of labels
     * @param  string &$data Binary data
     * @param  string $orig Original packet
     * @return string        Dot-separated labels list
     */
    public static function parseLabels(&$data, $orig = null)
    {
        $str = '';
        while (mb_orig_strlen($data) > 0) {
            $l = ord($data[0]);

            if ($l >= 192) {
                $pos = Binary::bytes2int(chr($l - 192) . mb_orig_substr($data, 1, 1));
                $data = mb_orig_substr($data, 2);
                $ref = mb_orig_substr($orig, $pos);
                return $str . Binary::parseLabels($ref, $orig);
            }

            $p = mb_orig_substr($data, 1, $l);
            $str .= $p . (($l !== 0) ? '.' : '');
            $data = mb_orig_substr($data, $l + 1);
            if ($l === 0) {
                break;
            }
        }
        return $str;
    }

    /**
     * Convert bytes into integer
     * @param  string $str Bytes
     * @param  boolean $l Little endian? Default is false
     * @return integer
     */
    public static function bytes2int($str, $l = false)
    {
        if ($l) {
            $str = strrev($str);
        }
        $dec = 0;
        $len = mb_orig_strlen($str);
        for ($i = 0; $i < $len; ++$i) {
            $dec += ord(mb_orig_substr($str, $i, 1)) * pow(0x100, $len - $i - 1);
        }
        return $dec;
    }

    /**
     * Build nul-terminated string, with 2-byte of length
     * @param string $str Data
     * @return string
     */
    public static function LVnull($str)
    {
        return static::LV($str . "\x00", 2, true);
    }

    /**
     * Build length-value binary snippet
     * @param string $str Data
     * @param integer $len Number of bytes to encode length. Default is 1
     * @param boolean $lrev Reverse?
     * @return string
     */
    public static function LV($str, $len = 1, $lrev = false)
    {
        $l = static::i2b($len, mb_orig_strlen($str));
        if ($lrev) {
            $l = strrev($l);
        }
        return $l . $str;
    }

    /**
     * Converts integer to binary string
     * @alias Binary::int2bytes
     * @param  integer $len Length
     * @param  integer $int Integer
     * @param  boolean $l Optional. Little endian. Default value - false
     * @return string       Resulting binary string
     */
    public static function i2b($len, $int = 0, $l = false)
    {
        return static::int2bytes($len, $int, $l);
    }

    /**
     * Converts integer to binary string
     * @param  integer $len Length
     * @param  integer $int Integer
     * @param  boolean $l Optional. Little endian. Default value - false
     * @return string       Resulting binary string
     */
    public static function int2bytes($len, $int = 0, $l = false)
    {
        $hexstr = dechex($int);

        if ($len === null) {
            if (mb_orig_strlen($hexstr) % 2) {
                $hexstr = "0" . $hexstr;
            }
        } else {
            $hexstr = str_repeat('0', $len * 2 - mb_orig_strlen($hexstr)) . $hexstr;
        }

        $bytes = mb_orig_strlen($hexstr) / 2;
        $bin = '';

        for ($i = 0; $i < $bytes; ++$i) {
            $bin .= chr(hexdec(substr($hexstr, $i * 2, 2)));
        }

        return $l ? strrev($bin) : $bin;
    }

    /**
     * Build byte
     * @param  integer $int Byte number
     * @return string
     */
    public static function byte($int)
    {
        return chr($int);
    }

    /**
     * Build word (2 bytes) little-endian
     * @param  integer $int Integer
     * @return string
     */
    public static function wordl($int)
    {
        return strrev(static::word($int));
    }

    /**
     * Build word (2 bytes) big-endian
     * @param  integer $int Integer
     * @return string
     */
    public static function word($int)
    {
        return static::i2b(2, $int);
    }

    /**
     * Build double word (4 bytes) little endian
     * @param  integer $int Integer
     * @return string
     */
    public static function dwordl($int)
    {
        return strrev(static::dword($int));
    }

    /**
     * Build double word (4 bytes) big-endian
     * @param  integer $int Integer
     * @return string
     */
    public static function dword($int)
    {
        return static::i2b(4, $int);
    }

    /**
     * Build quadro word (8 bytes) little endian
     * @param  integer $int Integer
     * @return string
     */
    public static function qwordl($int)
    {
        return strrev(static::qword($int));
    }

    /**
     * Build quadro word (8 bytes) big endian
     * @param  integer $int Integer
     * @return string
     */
    public static function qword($int)
    {
        return static::i2b(8, $int);
    }

    /**
     * Parse byte, and remove it
     * @param  string &$p Data
     * @return integer
     */
    public static function getByte(&$p)
    {
        $r = static::bytes2int($p{0});
        $p = mb_orig_substr($p, 1);
        return (int)$r;
    }

    /**
     * Get single-byte character
     * @param  string &$p Data
     * @return string
     */
    public static function getChar(&$p)
    {
        $r = $p{0};
        $p = mb_orig_substr($p, 1);
        return $r;
    }

    /**
     * Parse word (2 bytes)
     * @param  string &$p Data
     * @param  boolean $l Little endian?
     * @return integer
     */
    public static function getWord(&$p, $l = false)
    {
        $r = static::bytes2int(mb_orig_substr($p, 0, 2), !!$l);
        $p = mb_orig_substr($p, 2);
        return (int)$r;
    }

    /**
     * Get word (2 bytes)
     * @param  string &$p Data
     * @param  boolean $l Little endian?
     * @return string
     */
    public static function getStrWord(&$p, $l = false)
    {
        $r = mb_orig_substr($p, 0, 2);
        $p = mb_orig_substr($p, 2);
        if ($l) {
            $r = strrev($r);
        }
        return $r;
    }

    /**
     * Get double word (4 bytes)
     * @param  string &$p Data
     * @param  boolean $l Little endian?
     * @return integer
     */
    public static function getDWord(&$p, $l = false)
    {
        $r = static::bytes2int(mb_orig_substr($p, 0, 4), !!$l);
        $p = mb_orig_substr($p, 4);
        return (int)$r;
    }

    /**
     * Parse quadro word (8 bytes)
     * @param  string &$p Data
     * @param  boolean $l Little endian?
     * @return integer
     */
    public static function getQword(&$p, $l = false)
    {
        $r = static::bytes2int(mb_orig_substr($p, 0, 8), !!$l);
        $p = mb_orig_substr($p, 8);
        return (int)$r;
    }

    /**
     * Get quadro word (8 bytes)
     * @param  string &$p Data
     * @param  boolean $l Little endian?
     * @return string
     */
    public static function getStrQWord(&$p, $l = false)
    {
        $r = mb_orig_substr($p, 0, 8);
        if ($l) {
            $r = strrev($r);
        }
        $p = mb_orig_substr($p, 8);
        return $r;
    }

    /**
     * Parse nul-terminated string
     * @param  string &$str Data
     * @return string
     */
    public static function getString(&$str)
    {
        $p = mb_orig_strpos($str, "\x00");
        if ($p === false) {
            return '';
        }
        $r = mb_orig_substr($str, 0, $p);
        $str = mb_orig_substr($str, $p + 1);
        return $r;
    }

    /**
     * Parse length-value structure
     * @param  string &$p Data
     * @param  integer $l Number of length bytes
     * @param  boolean $nul Nul-terminated? Default is false
     * @param  boolean $lrev Length is little endian?
     * @return string
     */
    public static function getLV(&$p, $l = 1, $nul = false, $lrev = false)
    {
        $s = static::b2i(mb_orig_substr($p, 0, $l), !!$lrev);
        $p = mb_orig_substr($p, $l);
        if ($s === 0) {
            return '';
        }
        $r = '';
        if (mb_orig_strlen($p) < $s) {
            Daemon::log('getLV error: buf length (' . mb_orig_strlen($p) . '): ' . Debug::exportBytes($p) . ', must be >= string length (' . $s . ")\n");
        } elseif ($nul) {
            $lastByte = mb_orig_substr($p, -1);
            if ($lastByte !== "\x00") {
                Daemon:
                log('getLV error: Wrong end of NUL-string (' . Debug::exportBytes($lastByte) . '), len ' . $s . "\n");
            } else {
                $d = $s - 1;
                if ($d < 0) {
                    $d = 0;
                }
                $r = mb_orig_substr($p, 0, $d);
                $p = mb_orig_substr($p, $s);
            }
        } else {
            $r = mb_orig_substr($p, 0, $s);
            $p = mb_orig_substr($p, $s);
        }
        return $r;
    }

    /**
     * Convert bytes into integer
     * @alias Binary::bytes2int
     * @param  string $str Bytes
     * @param  boolean $l Little endian? Default is false
     * @return integer
     */
    public static function b2i($str = 0, $l = false)
    {
        return static::bytes2int($str, $l);
    }

    /**
     * Convert array of flags into bit array
     * @param  array $flags Flags
     * @param  integer $len Length. Default is 4
     * @return string
     */
    public static function flags2bitarray($flags, $len = 4)
    {
        $ret = 0;
        foreach ($flags as $v) {
            $ret |= $v;
        }
        return static::i2b($len, $ret);
    }

    /**
     * Convert bitmap into bytes
     * @param  string $bitmap Bitmap
     * @param  integer $check_len Check length?
     * @return string|false
     */
    public static function bitmap2bytes($bitmap, $check_len = 0)
    {
        $r = '';
        $bitmap = str_pad($bitmap, ceil(mb_orig_strlen($bitmap) / 8) * 8, '0', STR_PAD_LEFT);
        for ($i = 0, $n = mb_orig_strlen($bitmap) / 8; $i < $n; ++$i) {
            $r .= chr((int)bindec(mb_orig_substr($bitmap, $i * 8, 8)));
        }
        if ($check_len && (mb_orig_strlen($r) !== $check_len)) {
            return false;
        }
        return $r;
    }

    /**
     * Get bitmap
     * @param  integer $byte Byte
     * @return string
     */
    public static function getbitmap($byte)
    {
        return sprintf('%08b', $byte);
    }
}
