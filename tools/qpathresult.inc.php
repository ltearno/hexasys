<?php

class QPathResultRowArrayAccess implements ArrayAccess
{
	var $qpathresultiterator = null;
	
	public function __construct( QPathResultIterator $qpathresultiterator )
	{
		$this->qpathresultiterator = $qpathresultiterator;
	}
	
	public function offsetExists( $offset )
	{
	}
	
	public function offsetGet( $offset )
	{
		return $this->qpathresultiterator->qpathresult->GetVal( $this->qpathresultiterator->position, $offset );
	}
	
	public function offsetSet( $offset, $value )
	{
	}
	
	public function offsetUnset( $offset )
	{
	}
}

class QPathResultIterator implements Iterator
{
	var $qpathresult = null;
	var $position = 0;
	
	public function __construct( QPathResult $qpathresult )
	{
		$this->qpathresult = $qpathresult;
		$this->position = 0;
	}
	
	public function current()
	{
		return new QPathResultRowArrayAccess( $this );
	}
	
	public function key()
	{
		return $this->position;
	}
	
	public function next()
	{
		$this->position++;
	}
	
	public function rewind()
	{
		$this->position = 0;
	}
	
	public function valid()
	{
		if( $this->position < $this->qpathresult->GetNbRows() )
			return true;
		return false;
	}
}

class QPathResultUseInformation
{
	static $datas = array();
	
	var $id;
	
	public static function GetReports()
	{
		if( !defined('TRACE_UNUSED_QPATHRESULT_FIELDS') || !TRACE_UNUSED_QPATHRESULT_FIELDS )
			return null;
		return self::$datas;
	}
	
	public static function ReportUsageInformation()
	{
		if( !defined('TRACE_UNUSED_QPATHRESULT_FIELDS') || !TRACE_UNUSED_QPATHRESULT_FIELDS )
			return;
			
		$dir = APP_DIR . 'logs/qpath_stacktraces';
		if( ! is_dir( $dir ) )
			mkdir( $dir );
		
		foreach( self::$datas as $traceId => $report )
		{
			if( $report['usedAllInOne'] || count($report['nonUsedFields'])==0 || $report['nbRows']==0 )
				continue;
			
			$stackFileName = $dir . '/' . $traceId . '.txt';
			
			if( file_exists( $stackFileName ) )
				continue;
			
			$stackFile = fopen( $stackFileName, 'w' );
			if( $stackFile == null )
				continue;
			
			$fetched = array_keys( $report['fetchedFields'] );
			$unused = array_keys( $report['nonUsedFields'] );
			$used = array_diff( $fetched, $unused );
			
			fwrite( $stackFile, "TRACE_ID: $traceId\r\n\r\n" );
			fwrite( $stackFile, "CALL STACK:\r\n{$report['stackTrace']}\r\n\r\n" );
			fwrite( $stackFile, "NUMBER OF RESULT ROWS: " . $report['nbRows'] . "\r\n\r\n" );
			fwrite( $stackFile, "FETCHED FIELDS:\r\n" . DumpCode( $fetched ) . "\r\n\r\n" );
			fwrite( $stackFile, "USED ALL IN ONE: " . ($report['usedAllInOne']?"YES":"NO") . "\r\n\r\n" );
			fwrite( $stackFile, "USED FIELDS:\r\n" . DumpCode( $used ) . "\r\n\r\n" );
			fwrite( $stackFile, "UNUSED FIELDS:\r\n" . DumpCode( $unused ) );
			
			fclose( $stackFile );
		}
	}
	
	function init( $stackTrace, $fields, $nbRows )
	{
		$this->id = md5( $stackTrace );
		
		if( ! isset( self::$datas[$this->id] ) )
			self::$datas[$this->id] = array( "stackTrace"=>$stackTrace, "fetchedFields"=>$fields, "nonUsedFields"=>$fields, "usedAllInOne"=>false, "nbRows"=>$nbRows );
	}
	
	function useField( $name )
	{
		unset( self::$datas[$this->id]['nonUsedFields'][$name] );
	}
	
	function useAllFields()
	{
		self::$datas[$this->id]['usedAllInOne'] = true;
	}
}

class QPathResult implements IteratorAggregate
{
	var $fields = null;
	var $rows = null;
	
	var $indexes = null;
	
	var $useInfo = null;
	
	public function getIterator()
	{
        return new QPathResultIterator( $this );
    }
	
	// initialize the object
	// $fields must be an array in the form fieldName=>columnIdx
	// $rows must be an array of arrays in the form row[n] = array( field0, field1, ... )
	function Set( $fields, $rows )
	{
		$this->fields = $fields;
		$this->rows = $rows;
		$this->indexes = array();
		
		if( defined('TRACE_UNUSED_QPATHRESULT_FIELDS') && TRACE_UNUSED_QPATHRESULT_FIELDS )
		{
			$this->useInfo = new QPathResultUseInformation();
			$this->useInfo->init( getStackTrace(), $fields, count($rows) );
		}
	}
	
	// return the result content in the form of an associative array
	function GetArray()
	{
		if( $this->useInfo != null )
			$this->useInfo->useAllFields();
		
		$array = array();
		foreach( $this->rows as $row )
		{
			$newRow = array();
			foreach( $this->fields as $fieldName => $colIdx )
				$newRow[$fieldName] = $row[$colIdx];
			$array[] = $newRow;
		}
		
		return $array;
	}
	
	// return the result content in the form of an optimized array for gwt io serialization
	function GetArrayOptimized()
	{
		if( $this->useInfo != null )
			$this->useInfo->useAllFields();
		
		$fields = array();
		foreach( $this->fields as $fieldName => $colIdx )
			$fields[$colIdx] = $fieldName;
		
		$array = array(
			"magic" => "ar2ra",
			"fields" => $fields,
			"rows" => $this->rows
		);
		
		return $array;
	}
	
	function GetOptArray()
	{
		$array = array(
			"fields" => $this->fields,
			"rows" => $this->rows
			);
		
		return $array;
	}

    function GetSerializedArray( $serializer )
    {
    	if( $this->useInfo != null )
			$this->useInfo->useAllFields();
    	
        $fieldOrder = array();
        foreach( $serializer->GetFieldOrder() as $field )
            $fieldOrder[] = $this->fields[$field];

        $array = array();
        foreach( $this->rows as $row )
        {
            $newRow = array();
            foreach( $fieldOrder as $fieldNum )
                $newRow[] = $row[$fieldNum];
            $array[] = $newRow;
        }

        return $array;
    }
	
	// return the result content in the form of an associative array
	function GetRowAsAssocArray( $row )
	{
		if( $this->useInfo != null )
			$this->useInfo->useAllFields();
		
		$array = array();
		
		foreach( $this->fields as $fieldName => $colIdx )
			$array[$fieldName] = $this->rows[$row][$colIdx];
		
		return $array;
	}
	
	// return the value of a field in a row.
	// $row is the index of the row
	// $field is the name of the required field
	function GetVal( $row, $field )
	{
		if( $this->useInfo != null )
			$this->useInfo->useField( $field );
		
		return $this->rows[$row][$this->fields[$field]];
	}
	
	// retrieve a row number based on an indexed field
	function GetRowIndexed( $indexField, $indexedFieldValue )
	{
		if( $this->useInfo != null )
			$this->useInfo->useField( $indexField );
		
		// construct the index if it does not exist already
		if( ! isset( $this->indexes[$indexField] ) )
			$this->indexes[$indexField] = $this->_ConstructIndex( $indexField );
		
		if( ! isset( $this->indexes[$indexField][$indexedFieldValue] ) )
			return -1;
		
		return $this->indexes[$indexField][$indexedFieldValue];
	}
	
	// retrieve a value based on an indexed field
	function GetValIndexed( $indexField, $indexedFieldValue, $field )
	{
		if( $this->useInfo != null )
			$this->useInfo->useField( $indexField );
		
		return $this->rows[ $this->GetRowIndexed( $indexField, $indexedFieldValue ) ][$this->fields[$field]];
	}
	
	function GetRow( $row )
	{
		if( $this->useInfo != null )
			$this->useInfo->useAllFields();
		
		return $this->rows[$row];
	}
	
	// return the field names
	function GetFieldsNames()
	{
		return array_keys( $this->fields );
	}
	
	// return the number of rows
	function GetNbRows()
	{
		return count( $this->rows );
	}
	
	function GetFields()
	{
		return $this->fields;
	}
	
	// return the rows
	function GetRows()
	{
		if( $this->useInfo != null )
			$this->useInfo->useAllFields();
		
		return $this->rows;
	}
	
	// return a list of all the values of the specified field in the form 0=>value0, 1=>value1, ...
	function GetList( $field )
	{
		if( $this->useInfo != null )
			$this->useInfo->useField( $field );
		
		$res = array();
		foreach( $this->rows as $row )
			$res[] = $row[$this->fields[$field]];
		return $res;
	}
	
	// construct an index based on the value of a certain field
	// note that this works only for unique fields, otherwise index keys will be overwritten if multiple rows have the same value
	function _ConstructIndex( $field )
	{
		$res = array();
		foreach( $this->rows as $idx => $row )
			$res[$row[$this->fields[$field]]] = $idx;
		return $res;
	}
}


?>