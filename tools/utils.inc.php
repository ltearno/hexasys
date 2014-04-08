<?php

function ensureDirectoryExists( $directory )
{
	$parts = explode( "/", $directory );
	$place = "";
	
	while( count( $parts ) > 0 )
	{
		if( $place . $parts[0] != "" && ! file_exists( $place . $parts[0] ) )
			mkdir( $place . $parts[0] );
		$place .= array_shift( $parts ) . "/";
	}
}

function string2Json( $string )
{
	$json = json_decode( $string, true );
	return $json;
}

function json2string( $json )
{
	return json_encode( $json );
}

function getArrayField( $json, $path )
{
	$current = $json;
	$parts = explode( ".", $path );

	while( count( $parts ) > 1 )
	{
		// maybe info does not exist
		if( ! isset( $current[$parts[0]] ) )
			return null;
		
		$current = $current[$parts[0]];
		array_shift( $parts );
	}
	
	if( isset( $current[$parts[0]] ) )
		return $current[$parts[0]];
	
	return null;
}

function setArrayField( &$json, $path, $value )
{
	$current = & $json;
	$parts = explode( ".", $path );

	while( count( $parts ) > 1 )
	{
		// maybe info does not exist
		if( ! ( isset( $current[$parts[0]] ) && is_array( $current[$parts[0]] ) ) )
			$current[$parts[0]] = array();
		
		$current = & $current[$parts[0]];
		array_shift( $parts );
	}
	
	$current[$parts[0]] = $value;
}

function array2string( $array )
{
	$s = "";
	$addComa = false;
	foreach( $array as $k => $e )
	{
		if( $addComa )
			$s .= ", ";
		$addComa = true;
		
		if( ! is_numeric( $k ) )
			$s .= $k . " => ";
		
		if( is_array( $e ) )
			$s .= "{ " . array2string( $e ) . " }";
		else
			$s .= $e;
	}
	
	return $s;
}

function generatePassword()
{
	$tpass=array();
	$id=0;
	$taille=100;

	// r�cup�ration des chiffres et lettre
	for($i=48;$i<58;$i++) $tpass[$id++]=chr($i);
	for($i=65;$i<91;$i++) $tpass[$id++]=chr($i);
	for($i=97;$i<123;$i++) $tpass[$id++]=chr($i);

	$passwd="";
	for($i=0;$i<$taille;$i++)
		$passwd.=$tpass[rand(0,$id-1)];

	//$passwd.="!_.".substr(time(),0,10);
	$passwd .= substr(time(),0,10);
	return substr( str_shuffle($passwd),0,12 );
}

/**
 * Function to check that an array posseses the needed keys
 * @param array $array Array being checked
 * @param array $keys Array containing the keys to be possessed by {@link $array}
 * @return integer
 */
function CheckArrayContains( $array, $keys )
{
	foreach( $keys as $key )
	{
		if( ! array_key_exists( $key, $array ) )
		{
			//echo 'ARRAY DOESNOT CONTAIN KEY ' . $key . '<br/>';
			//Dump( $array );
			return false;
		}
	}
	
	return true;
}

function arrays_eq( $a, $b )
{
	if( gettype($a) != gettype($b) )
	{
		//echo "not same type,";
		return false;
	}
	
	if( is_array( $a ) )
	{
		if( count($a) != count($b) )
		{
			//echo "not same size";
			return false;
		}
		
		$bKeys = array_flip( array_keys( $b ) );
		foreach( $a as $ak => $av )
		{
			if( ! array_key_exists( $ak, $b ) )
				return false;
			
			$eq = arrays_eq( $av, $b[$ak] );
			if( ! $eq )
				return false;
			
			unset( $bKeys[$ak] );
		}
		
		if( count( $bKeys ) > 0 )
			return false;
		
		return true;
	}
	
	if( $a != $b )
		return false;
	
	return true;
}

function ArrayMatch( $a1, $a2 )
{
    foreach( $a1 as $e1 )
	    foreach( $a2 as $e2 )
    		if( $e1 == $e2 )
    			return true;
    return false;
}

function sign( $n )
{
	if( $n == 0 )
		return 0;
	if( $n > 0 )
		return 1;
	return -1;
}





function CreateThumbnail( $type, $srcFile, $destFile, $width, $height )
{
	// hack for ie
	if( $type == 'pjpeg' )
		$type = 'jpeg';
	if( $type == 'jpg' )
		$type = 'jpeg';
	
	if( ($type!='jpeg') && ($type!='png') )
		return null; // not supported
		
	if( $type == 'jpeg' )
		$srcImg = imagecreatefromjpeg( $srcFile );
	else if( $type == 'png' )
		$srcImg = imagecreatefrompng( $srcFile );

	$srcImgW = imageSX( $srcImg );
	$srcImgH = imageSY( $srcImg );
	
	$size = array( $srcImgW, $srcImgH );
	
	if( $srcImgW > $srcImgH )
	{
		$dstImgW = $width;
		//$dstImgH = $srcImgH * ( $height / $srcImgW );
		$dstImgH = ( $srcImgH * $dstImgW ) / $srcImgW;
	}
	//if( $srcImgW < $srcImgH )
	else
	{
		$dstImgH = $height;
		$dstImgW = ( $srcImgW * $dstImgH ) / $srcImgH;
	}
	
	$dstImg = ImageCreateTrueColor( $dstImgW, $dstImgH );
	imagecopyresampled( $dstImg, $srcImg, 0, 0, 0, 0, $dstImgW, $dstImgH, $srcImgW, $srcImgH );
	
	if( $type == 'jpeg' )
		imagejpeg( $dstImg, $destFile );
	else if( $type == 'png' )
		imagepng( $dstImg, $destFile );
	
	// optionally prevent the original picture to be too big...
/*	if( ($srcImgW>640) || ($srcImgH>480) )
	{
		// need to also resize the original...
		$dX = 640;
		$dY = 480;
		if( $srcImgW > $srcImgH )
			$dY = $srcImgH * ( $dX / $srcImgW );
		else
			$dX = $srcImgW * ( $dY / $srcImgH );
		
		$replaceImg = ImageCreateTrueColor( $dX, $dY );
		imagecopyresampled( $replaceImg, $srcImg, 0, 0, 0, 0, $dX, $dY, $srcImgW, $srcImgH );
		
		if( $type == 'jpeg' )
			imagejpeg( $replaceImg, $srcFile );
		else if( $type == 'png' )
			imagepng( $replaceImg, $srcFile );
		
		imagedestroy( $replaceImg ); 
	}*/
	
	imagedestroy( $srcImg ); 
	imagedestroy( $dstImg ); 
	
	return $size;
}

function getStackTrace( $pass = 2 )
{
	$stack = debug_backtrace( 0 );

	$out = array();

	foreach( $stack as $call )
	{
		if( $pass-- > 0 )
		{
			continue;
		}

		if( isset( $call['file'] ) )
			$location = "{$call['file']}:{$call['line']}";
		else
			$location = "eval()";

		if( isset( $call['class'] ) )
			$spec = "{$call['class']}{$call['type']}{$call['function']}";
		else
			$spec = $call['function'];

		$args = array();
		foreach( $call['args'] as $arg )
			$args[] = DumpCodeCompact( $arg );
		$args = implode( ",", $args );

		$out[] = "$spec($args)\t$location";
	}

	return implode( "\r\n", $out );
}
	
?>