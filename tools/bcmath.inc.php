<?php

/**
 * Emulation of the BCMath library, if not available
 */

function bcadd( $a, $b )
{
    return round( ($a * 100 + $b * 100) / 100, 2 );
}

function bcsub( $a, $b )
{
    return round( ($a * 100 - $b * 100) / 100, 2 );
}

function bcmul( $a, $b )
{
    return round( $a * $b, 2 );
}

function bccomp( $a, $b )
{
    return $a > $b ? 1 : ($a == $b ? 0 : -1);
}

?>