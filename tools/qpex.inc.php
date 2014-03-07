<?php

$QPexLogger = new Logger();
$QPexLogger->Init( 'qpex.txt', Logger::LOG_MSG );


class QPex
{
	public function __construct( Database $db, QPath $qpath )
	{
		$this->db = $db;
		$this->QPath = $qpath;
	}
	
	var $db = null;
	var $QPath = null;
	var $alwaysLog = false;
	
	var $tree = array();
	var $where = array();
	var $fields = array();
	var $group_by = array();
	var $order_by = array();
	
	var $tables = array();
	
	var $W_is_for = null;
	var $next_assoc = '->';
	
	public function F( $field )
	{
		$this->fields[] = $field;
		
		return $this;
	}
	
	public function O( $field )
	{
		$this->order_by[] = $field;
		
		return $this;
	}
	
	public function G( $field )
	{
		if( $this->W_is_for == null )
			$this->group_by[] = $field;
		else
			$this->group_by[] = $this->W_is_for . '.' . $field;
		
		return $this;
	}
	
	public function W( $condition )
	{
		if( $this->W_is_for == null )
			$this->where[] = $condition;
		else
			$this->where[] = $this->W_is_for . '.' . $condition;
		
		return $this;
	}
	
	public function M( $table = null )
	{
		if( $table == null )
			$this->tables[$this->W_is_for] = 0;
		else if( $table == "*" )
			$this->tables = array();
		else
			$this->tables[$table] = 0;
		
		return $this;
	}
	
	public function  __call( $name, $arguments )
	{
		//echo "CALLED $name<br/>";
		if( $name == "F" )
			return $this->F( $arguments[0] );
		if( $name == "O" )
			return $this->O( $arguments[0] );
		
		return $this;
	}
	
	public function  __get( $name )
	{
		if( $name == "C" )
		{
			$this->next_assoc = '<-';
		}
		else
		{
			//echo "GET $name<br/>";
			$this->assocTable( $name );
		}
		
		return $this;
	}
	
	function assocTable( $table_name, $left_field=null, $right_field=null )
	{
		if( empty( $this->tree ) )
		{
			$this->tree['t'] = $table_name;
		}
		else
		{
			$this->tree = array( 'left'=>$this->tree, 'op'=>$this->next_assoc, 'right'=>array( 't'=>$table_name ) );
			
			if( $left_field != null )
				$this->tree['left_field'] = $left_field;
			if( $right_field != null )
				$this->tree['right_field'] = $right_field;
		}
		
		// register the table as one for which to generate field
		$this->tables[$table_name] = 1;
		
		$this->W_is_for = $table_name;
		$this->next_assoc = '->';
		
		return $this;
	}
	
	function assocQPex( $q, $left_field=null, $right_field=null )
	{
		//echo "ASSOC QPEX<br/>";
		if( empty( $this->tree ) )
		{
			$this->tree = $q->tree;
		}
		else
		{
			$this->tree = array( 'left'=>$this->tree, 'op'=>$this->next_assoc, 'right'=>$q->tree );
			
			if( $left_field != null )
				$this->tree['left_field'] = $left_field;
			if( $right_field != null )
				$this->tree['right_field'] = $right_field;
		}
		
		$this->where = array_merge( $this->where, $q->where );
		$this->fields = array_merge( $this->fields, $q->fields );
		$this->group_by = array_merge( $this->group_by, $q->group_by );
		$this->order_by = array_merge( $this->order_by, $q->order_by );
		$this->tables = array_merge( $this->tables, $q->tables );
		
		$this->W_is_for = $q->W_is_for;
		$this->next_assoc = '->';
		
		return $this;
	}
	
	
	/* @return QPathResult */
	public function RUN()
	{
		global $QPexLogger;
		
		$sql = $this->SQL();
		$this->db->Query( $sql );

        /* @var $res QPathResult */
		$res = new QPathResult();
		$res->Set( array_flip( $this->db->GetFieldsNames() ), $this->db->LoadAllResultArray() );
		
		if( $this->alwaysLog || $this->db->IsError() )
			$QPexLogger->Log( Logger::LOG_MSG, 'RUN: ' . $sql );

		return $res;
	}
	
	public function ONE()
	{
		global $QPexLogger;
		
		$sql = $this->SQL();
		
		$this->db->Query( $sql );
		
		if( $this->alwaysLog || $this->db->IsError() )
			$this->logger->Log( Logger::LOG_MSG, 'ONE: ' . $sql );

        $res = $this->db->LoadAllResultAssoc();
		if( count( $res ) < 1 )
			return null;
		return $res[0];
	}
	
	public function SQL()
	{
		$sql = $this->sql_get( $this->tree );
		
		$fields = array();
		foreach( $this->tables as $table => $visible )
			if( $visible > 0 )
				$fields[] = $this->_GetFields( $table );
			
		$res = "SELECT " . implode( ',', array_merge( $fields, $this->fields ) ) . " FROM " . $sql['from'];
		
		if( count( $this->where ) > 0 )
		{
			$res .= " WHERE (" . implode( ") AND (", $this->where ) . ")";
		}
		
		if( count( $this->group_by ) > 0 )
		{
			$res .= " GROUP BY " . implode( ',', $this->group_by );
		}
		
		if( count( $this->order_by ) > 0 )
		{
			$res .= " ORDER BY " . implode( ',', $this->order_by ) . " ASC";
		}
		
		return $res;
	}
	
	function sql_get( $tree )
	{
		if( isset( $tree['t'] ) )
		{
			return array( 'table'=>$tree['t'], 'from'=>$tree['t'] );
		}
		
		return $this->sql_op( $tree );
	}
	
	function sql_op( $tree )
	{
		$left = $this->sql_get( $tree['left'] );
		$left_table = $left['table'];
		$left_from = $left['from'];
		
		$right = $this->sql_get( $tree['right'] );
		$right_table = $right['table'];
		$right_from = $right['from'];
		
		if( $tree['op'] == '->' )
		{
			$leftField = $left_table . '.' . Singularize($right_table).'_id';
			$rightField = $right_table . '.id';
		}
		else if( $tree['op'] == '<-' )
		{
			$leftField = $left_table . '.id';
			$rightField = $right_table . '.' . Singularize($left_table).'_id';
		}
		
		// custom fields
		if( isset( $tree['left_field'] ) )
			$leftField = $left_table . '.' . $tree['left_field'];
		if( isset( $tree['right_field'] ) )
			$rightField = $right_table . '.' . $tree['right_field'];
		
		$table = $left_table;
		$from = "($left_from LEFT JOIN $right_from ON $leftField=$rightField)";
		
		return array( 'table'=>$table, 'from'=>$from );
	}
	
	var $cacheFields = array();
	
	function _GetFields( $tableName )
	{
		if( ! isset( $this->cacheFields[$tableName] ) )
		{
			$fields = array();
			
			$this->db->Query( "DESCRIBE " . $tableName );
			$rows = $this->db->LoadAllResultArray();
			foreach( $rows as $row )
				$fields[] = $row[0];
			
			$this->cacheFields[$tableName] = $fields;
		}
		
		$fields = array();
		foreach( $this->cacheFields[$tableName] as $field )
			$fields[] = "$tableName.$field AS \"$tableName.$field\"";
		
		return implode( ", ", $fields );
	}
}




?>