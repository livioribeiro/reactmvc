<?php

namespace ReactMVC\Utils;

/**
 * Description of Strings
 *
 * @author livio
 */
class String
{
    public static function startsWith($haystack, $needle)
    {
        return (substr($haystack, 0, strlen($needle)) === $needle);
    }
    
    public static function endsWith($haystack, $needle)
    {
        $length = strlen($needle);
        if ($length == 0) {
            return true;
        }

        return (substr($haystack, -$length) === $needle);
    }
}
