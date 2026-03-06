<?php

declare(strict_types=1);
function array_by_ref($array)
{
    $ret = [];
    foreach ($array as $k => &$V) {
        $ret[$k] = &$V;
    }
    return $ret;
}
