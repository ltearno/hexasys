<?php

define( "ENDL", "\r\n" );
define( 'SPACE', "    " );

// Removes BOM from UTF8 file_get_contents (if BOM present)
function file_get_contents_utf8($fn)
{
	 $content = file_get_contents($fn);
	 if( ! strncmp( $content, "\xef\xbb\xbf", 3 ) )
		$content = substr( $content, 3 );
	 return $content;
} 

// dump a variable beautifully
function Dump( $var )
{
	echo GetDump( $var );
}

function GetDump( $var )
{
	return "<pre>".DumpCode( $var )."</pre>";
}

function getSpace( $n )
{
	$s = "";
	for( $i=0; $i<$n; $i++ )
		$s .= "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
	return $s;
}

// outputs a value as the PHP code to generate it
function DumpCode( $val )
{
	return DumpCodeRec( $val, 0 );
}

function DumpCodeRec( $val, $lvl )
{
	$lvlSpace = "";
	for( $l=0; $l<$lvl; $l++ )
		$lvlSpace .= SPACE;
		
	if( is_array( $val ) )
	{
		$nb = count( $val );
		if( $nb == 0 )
		{
			return "array()";
		}
		
		$s = "array( // $nb elements" . ENDL;
		$i = 0;
		foreach( $val as $key => $value )
		{
			$addComa = $i < $nb - 1;
			if( is_int( $key ) && $key == $i )
				$keyDump = "";
			else
				$keyDump = DumpCodeRec( $key, $lvl+1 ) . " => ";
			$s .= $lvlSpace . SPACE . $keyDump . DumpCodeRec( $value, $lvl+1 ) . ($addComa?",":"") . ENDL;
			$i++;
		}
		$s .= $lvlSpace . ")";
		
		return $s;
	}
	
	if( is_null( $val ) )
		return "null";
		
	if( is_string( $val ) )
		return "\"$val\"";
	
	if( is_numeric( $val ) )
		return "$val";
		
	if( is_bool( $val ) )
		return $val ? "true" : "false";
	
	return "CANNOT DUMP, it's an object !!!!!";
}





function DumpCodeCompact( $val )
{
	if( is_array( $val ) )
	{
		$nb = count( $val );
		if( $nb == 0 )
		{
			return "array()";
		}

		$s = "array(";
		$i = 0;
		foreach( $val as $key => $value )
		{
			$addComa = $i < $nb - 1;
			if( is_int( $key ) && $key == $i )
				$keyDump = "";
			else
				$keyDump = DumpCodeCompact( $key ) . " => ";
			$s .= $keyDump . DumpCodeCompact( $value ) . ($addComa?",":"");
			$i++;
		}
		$s .= ")";

		return $s;
	}

	if( is_null( $val ) )
		return "null";

	if( is_string( $val ) )
		return "\"$val\"";

	if( is_numeric( $val ) )
		return "$val";

	if( is_bool( $val ) )
		return $val ? "true" : "false";
	
	if( is_object( $val ) )
		return "Object()";

	return "CANNOT DUMP !!!!! $val";
}

function DumpRecord( $record )
{
	echo "<table border=1>";
	echo TableHeader( array_keys( $record ) );
	echo TableCells( $record );
	echo "</table>";
}


// returns the needed html to get a button
function getButton( Page $page, $title, $postParams, $getParams )
{
	$pageUrl = $page->locationUrl;
	
	return getButtonPageUrl( $pageUrl, $title, $postParams, $getParams );
}

function getButtonPageUrl( $pageUrl, $title, $postParams, $getParams )
{
	$urlParams = "";
	if( $getParams != null )
	{
		foreach( $getParams as $name => $value )
			$urlParams .= "&$name=" . urlencode( $value );
	}
	
	$html = "<form action='$pageUrl$urlParams' method='post'>";
	if( $postParams != null )
	{
		foreach( $postParams as $name => $value )
			$html .= "<input type='hidden' name='$name' value='$value'/>";
	}
	$html .= "<input type='submit' value='$title'></input>";
	$html .= "</form>";
	
	return $html;
}


// returns the needed html to get a link
function getHref( Page $page, $title, $params )
{
	$pageUrl = $page->locationUrl;
	
	return getHrefPageUrl( $pageUrl, $title, $params );
}

function getHrefPageUrl( $pageUrl, $title, $params )
{
	$urlParams = "";
	if( $params != null )
	{
		foreach( $params as $name => $value )
			$urlParams .= "&$name=" . urlencode( $value );
	}
	
	return "<a href='$pageUrl$urlParams'>$title</a>";
}


// gets text for a table header row
function TableHeader( $cells )
{
	return "<tr><th>" . implode( "</th><th>", $cells ) . "</th></tr>";
}

// gets text for a table row
function TableCells( $cells )
{
	return "<tr><td>" . implode( "</td><td>", $cells ) . "</td></tr>";
}



function DumpTable( $titles, $results, $extraFields = null )
{
	$out = "<table border=1>";
	
	if( $titles != null )
		$out .= TableHeader( array_merge( $titles, ( $extraFields==null ? array() : array_keys($extraFields) ) ) );
		
	foreach( $results as $row )
	{
		$cells = $row;
		
		if( $extraFields != null )
		{
			foreach( $extraFields as $extraField )
				$cells[] = $extraField->RowValue( $row );
		}
		
		$out .= TableCells( $cells );
	}
	
	$out .= "</table>";
	
	return $out;
}

function DumpTableVertical( $titles, $results, $extraFields = null )
{
	$out = "<table border=1>";
	
	foreach( $titles as $title )
	{
		$row = array( $title );
		foreach( $results as $result )
		{
			$val = $result[$title];
			if( is_array( $val ) )
				$row[] = "<pre>" . DumpCode( $val ) . "</pre>";
			else
				$row[] = $val;
		}
		
		$out .= TableCells( $row );
	}
	
	if( $extraFields != null )
	{
		foreach( $extraFields as $name => $extraField )
		{
			$row = array( $name );
			foreach( $results as $result )
				$row[] = $extraField->RowValue( $result );
			
			$out .= TableCells( $row );
		}
	}
	
	$out .= "</table>";
	
	return $out;
}

function QDumpTableVertical( $qPathResult, $extraFields = null )
{
	$fieldNames = $qPathResult->GetFieldsNames();
	
	return DumpTableVertical( $fieldNames, $qPathResult );
}


function QDumpTable( $qPathResult, $extraFields = null )
{
	if( $extraFields == null )
		$extraFields = array();
	
	$out = "<table border=1>";
	$fieldNames = $qPathResult->GetFieldsNames();
	for( $i=0; $i<count($fieldNames); $i++ )
		$fieldNames[$i] = str_replace( ".", "<br/>", $fieldNames[$i] );
	$out .= TableHeader( array_merge( $fieldNames, array_keys($extraFields) ) );
	$nbRows = $qPathResult->GetNbRows();
	if( $nbRows == 0 )
	{
		$out .= TableCells( array( "No record." ) );
	}
	else
	{
		for( $i=0; $i<$nbRows; $i++ )
		{
			$values = array();
			foreach( $extraFields as $extraField )
				$values[] = $extraField->RowValue( $qPathResult, $i );
			
			$out .= TableCells( array_merge( $qPathResult->GetRow( $i ), $values ) );
		}
		$out .= TableCells( array( "$nbRows records." ) );
	}
	$out .= "</table>";
	return $out;
}


function echoHiddenFields( $hidden )
{
	foreach( $hidden as $name => $value )
		echo "<input type='hidden' name='$name' value='$value'/>";
}


?>