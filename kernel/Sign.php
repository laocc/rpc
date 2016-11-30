<?php
namespace laocc\rpc;


class Sign
{

    public static function create($key, $token, $host, array $arr)
    {
        $arr[$key] = self::make_sign($key, $token, $host, $arr);
        return $arr;
    }

    public static function check($key, $token, $host, array $arr)
    {
        if (!isset($arr[$key])) return false;
        $sign = self::make_sign($key, $token, $host, $arr);
        return hash_equals($sign, $arr[$key]);
    }

    private static function make_sign($key, $token, $host, array $arr)
    {
        ksort($arr);
        $host .= $token;
        foreach ($arr as $k => $v) {
            if ($k !== $key) $host .= "&{$k}=$v";
        }
        return md5($host);
    }


}