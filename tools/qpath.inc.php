<?php

class QPathParamQuery
{
	var $QPath;
	
	var $template;
	var $parsed;
	
	public function __construct( QPath $qpath )
	{
		$this->QPath = $qpath;
	}
	
	public function setTemplate( $template )
	{
		$this->template = $template;
		
		$this->parsed = $this->QPath->Parse( $this->template );
	}
	
	private function getQuery( $args )
	{
		$query = $this->parsed;
		
		for( $i=0; $i<count($args); $i++ )
		{
			if( $i == 0 )
				$needle = "{0}";
			else
				$needle = "{".$i."}";
			$query = str_replace( $needle, $args[$i], $query );
		}
		
		return $query;
	}
	
	public function QueryOne()
	{
		// get the list of parameters and replace them inside the template
		$query = $this->getQuery( func_get_args() );
			
		// execute the sql statement
		$res = $this->QPath->QueryOneSql( $query );
		
		// return the result
		return $res;
	}
	
	public function QueryEx()
	{
		// get the list of parameters and replace them inside the template
		$query = $this->getQuery( func_get_args() );
			
		// execute the sql statement
		$res = $this->QPath->QuerySql( $query );
		
		// return the result
		return $res;
	}
}

// $qpath_ignored_fields has been normally defined in config.inc.php
$IGNORED_FIELDS = $qpath_ignored_fields;

// $qpath_tables has been defined normally in config.inc.php
$PLURALIZATIONS = $qpath_tables;

$SINGULARIZATIONS = array_flip( $PLURALIZATIONS );

function Pluralize( $str )
{
	global $PLURALIZATIONS;
	
	if( ! isset( $PLURALIZATIONS[$str] ) )
	{
        return $str . 's';
		//echo "<h1>PLURIAL NOT FOUND FOR $str !!!!</h1>";
		//return "BAD_PLURIAL";
	}
	
	return $PLURALIZATIONS[$str];
}

function Singularize( $str )
{
	global $SINGULARIZATIONS;
	
	if( ! isset( $SINGULARIZATIONS[$str] ) )
	{
        return substr($str, 0, -1);
		//echo "<h1>SINGULAR NOT FOUND FOR $str !!!!</h1>";
		//return "BAD_SINGULAR";
	}
	
	return $SINGULARIZATIONS[$str];
}

function StringToArray( $ids )
{
	return StringToArrayEx( ',', $ids );
}

function StringToArrayEx( $sep, $ids )
{
	if( $ids == "" )
		return array();
	return explode( $sep, $ids );
}

function ArrayToString( $ids )
{
	return implode( ',', $ids );
}



include( 'qwalk.inc.php' );
include( 'qpex.inc.php' );
include( 'qpathresult.inc.php' );






class QPath
{
	var $db = null;
	var $logger = null;
	
	var $alwaysLog = false;
	
	function Dump( &$var )
	{
		echo "<pre>";
		var_dump( $var );
		echo "</pre>";
	}
	
	function Init( $db )
	{
		$this->db = $db;
		
		$this->logger = new Logger();
		$this->logger->Init( 'qpath.txt', Logger::LOG_MSG );
	}
	
	function NewParsedQuery( $template )
	{
		$obj = new QPathParamQuery( $this );
		$obj->setTemplate( $template );
		return $obj;
	}

    function NewQWalk( $table, $id, $idField = 'id', $rawRecord = null )
    {
        $w = QWalk::newQWalk( $this->db, $table, $idField, $id );

        return $w;
    }
	
	function QPex()
	{
		return new QPex( $this->db, $this );
	}
	
	public function Quote( $string )
	{
		return $this->db->Quote( $string );
	}
	
	function Query( $expression )
	{
		$this->db->Query( $this->Parse( $expression ) );
		$res = $this->db->LoadAllResultAssoc();
		
		if( $this->alwaysLog || $this->db->IsError() )
			$this->logger->Log( Logger::LOG_MSG, 'QUERY : ' . $expression );
        
        return $res;
	}

    /* @return QPathResult */
	function QueryEx( $expression )
	{
		// start a time measure
		$m = HLib("Measure")->Start();
		
		$sql = $this->Parse( $expression );
		$this->db->Query( $sql );

        /* @var $res QPathResult */
		$res = new QPathResult();
		$res->Set( array_flip( $this->db->GetFieldsNames() ), $this->db->LoadAllResultArray() );
		
		$ms = HLib("Measure")->End( $m );
		if( $ms > 2000 )
		{
			$log = new Logger();
			$log->Init( 'qpath-long_requests.txt', Logger::LOG_MSG );
			$log->Log( Logger::LOG_MSG, "Time for a request $ms ms. for request : '$expression'" );
			$log->Term();
		}
		
		if( $this->alwaysLog || $this->db->IsError() )
			$this->logger->Log( Logger::LOG_MSG, 'QUERY_EX : ' . $expression . ' ( '. $res->GetNbRows() .' rows )' );

		return $res;
	}
	
	function QuerySql( $sql )
	{
		$this->db->Query( $sql );

        /* @var $res QPathResult */
		$res = new QPathResult();
		$res->Set( array_flip( $this->db->GetFieldsNames() ), $this->db->LoadAllResultArray() );
		
		if( $this->alwaysLog || $this->db->IsError() )
			$this->logger->Log( Logger::LOG_MSG, 'QUERY_EX : ' . $sql . ' ( '. $res->GetNbRows() .' rows )' );

		return $res;
	}
	
	function QueryOneSql( $sql )
	{
		$this->db->Query( $sql );

        if( $this->alwaysLog || $this->db->IsError() )
			$this->logger->Log( Logger::LOG_MSG, 'QUERY_ONE_SQL : ' . $sql );
		
		$res = $this->db->LoadAllResultAssoc();
		if( count( $res ) < 1 )
			return null;
		return $res[0];
	}
	
	/* @return QPathResult */
	function QueryExLimit( $expression, $limit )
	{
		$sql = $this->Parse( $expression );
		$this->db->Query( $sql . " LIMIT $limit" );

        /* @var $res QPathResult */
		$res = new QPathResult();
		$res->Set( array_flip( $this->db->GetFieldsNames() ), $this->db->LoadAllResultArray() );
		
		if( $this->alwaysLog || $this->db->IsError() )
			$this->logger->Log( Logger::LOG_MSG, 'QUERY_EX : ' . $expression . ' ( '. $res->GetNbRows() .' rows )' );

		return $res;
	}
	
	/* @return QPathResult */
	function QueryExWhere( $expression, $where )
	{
		$sql = $this->Parse( $expression, $where );
		$this->db->Query( $sql );
		
		/* @var $res QPathResult */
		$res = new QPathResult();
		$res->Set( array_flip( $this->db->GetFieldsNames() ), $this->db->LoadAllResultArray() );
		
		if( $this->alwaysLog || $this->db->IsError() )
			$this->logger->Log( Logger::LOG_MSG, 'QUERY_EX_WHERE : ' . $expression . ' ( '. $res->GetNbRows() .' rows )' );

		return $res;
	}
	
	function QueryExWhereLimit( $expression, $where, $start, $end )
	{
		$sql = $this->Parse( $expression, $where );
		$this->db->Query( $sql . " LIMIT $start, $end" );
		
		/* @var $res QPathResult */
		$res = new QPathResult();
		$res->Set( array_flip( $this->db->GetFieldsNames() ), $this->db->LoadAllResultArray() );
		
		if( $this->alwaysLog || $this->db->IsError() )
			$this->logger->Log( Logger::LOG_MSG, 'QUERY_EX_WHERE : ' . $expression . ' ( '. $res->GetNbRows() .' rows )' );

		return $res;
	}
	
	function QueryList( $expression, $keyField, $valueField )
	{
		$out = array();
		$res = $this->Query( $expression );
		foreach( $res as $row )
			$out[$row[$keyField]] = $row[$valueField];
		return $out;
	}

    function QueryValueList( $expression, $valueField )
	{
		$out = array();
		$res = $this->Query( $expression );
		foreach( $res as $row )
			$out[] = $row[$valueField];
		return $out;
	}
	
	function QueryOne( $expression )
	{
		$this->db->Query( $this->Parse( $expression ) . " LIMIT 1" );

		if( $this->alwaysLog || $this->db->IsError() )
			$this->logger->Log( Logger::LOG_MSG, 'QUERY_ONE : ' . $expression );
        
		$res = $this->db->LoadAllResultAssoc();
		if( count( $res ) < 1 )
			return null;
		return $res[0];
	}
	
	function Insert( $table, $fields = null )
	{
		// protect from bad character and eventually code injection
		if( $fields != null )
		{
			foreach( $fields as $fieldIdx => $field )
				$fields[$fieldIdx] = $this->db->Quote( $field );
		}
		
		if( $fields == null )
		{
			$sql = "INSERT INTO " . $table . " () VALUES ()";
		}
		else
		{
            $s = "";
            $values = array_values($fields);
            for( $i = 0; $i < count($values); $i++ )
            {
                $s .= $values[$i];
				
                if( $i < count($values) - 1 )
                    $s .= ',';
            }
			$sql = "INSERT INTO " . $table . " (`" . implode( "`,`", array_keys($fields) ) . "`) VALUES(" . $s . ")";
        }
		$this->db->Query( $sql );
		
		$insertedId = $this->db->InsertedId();
		
		$loggedUserId = HLib("Security")->GetLoggedUserId();
		
		$this->logger->Log( Logger::LOG_MSG, "user:$loggedUserId INSERT ($insertedId) : $sql" );
		
		return $insertedId;
	}
	
	function Delete( $table, $cond )
	{
		$loggedUserId = HLib("Security")->GetLoggedUserId();
		
		$this->logger->Log( Logger::LOG_MSG, "user:$loggedUserId DELETE : " . $table . ' CONDITION : ' . $cond );
		
		$sql = "DELETE FROM " . $table . " WHERE " . $cond;
		$res = $this->db->Query( $sql );

		if( $res == null )
			return -1;
        return 0;
	}
	
	function Update( $table, $cond, $data )
	{
		if( $data==null || (!is_array($data)) || (count($data)==0) )
			return 0;
			
		$a = array();
		foreach( $data as $name => $value )
		{
			if( is_numeric( $value ) )
				$a[] = "`$name`='$value'";
			else if( $value===null || $value=="NULL" )
				$a[] = "`" . $name . "`" . "=NULL";
			else
				$a[] = "`" . $name . "`=" . $this->db->Quote( $value );
		}
		$sql = "UPDATE $table SET " . implode(", ",$a) . " WHERE $cond";
		
		$loggedUserId = HLib("Security")->GetLoggedUserId();
		
		$this->logger->Log( Logger::LOG_MSG, "user:$loggedUserId UPDATE : $sql" );
		
		//echo $sql . "<br/>";
		$this->db->Query( $sql );
	}
	
	function UpdateRaw( $table, $cond, $data )
	{
		$a = array();
		foreach( $data as $name => $value )
			$a[] = "`" . $name . "`" . "=" . $value;
		$sql = "UPDATE " . $table . " SET " . implode(", ",$a) . " WHERE " . $cond;
		
		$loggedUserId = HLib("Security")->GetLoggedUserId();
		
		$this->logger->Log( Logger::LOG_MSG, "user:$loggedUserId UPDATERAW : $sql" );
		
		//echo $sql . "<br/>";
		$this->db->Query( $sql );
	}
	
	function HasField( $table, $field )
	{
		// process and put table fields in cache
		$this->_EnsureCachedTableFields( $table );
		
		return in_array( $field, $this->cacheFieldsExhaustive[$table] );
	}
	
	function HasTrigger( $triggerName, $tableName = null )
	{
		if( $tableName == null )
			$this->db->Query( "SHOW TRIGGERS FROM ".DATABASE_NAME );
		else
			$this->db->Query( "SHOW TRIGGERS FROM ".DATABASE_NAME." LIKE '$tableName'" );
		$rows = $this->db->LoadAllResultArray();
		foreach( $rows as $row )
		{
			if( $row[0] == $triggerName )
			{
				return true;
			}
		}
		return false;
	}
	
	function GetTableFields( $tableName )
	{
		$fields = array();
			
		$this->db->Query( "DESCRIBE " . $tableName );
		$rows = $this->db->LoadAllResultArray();
		foreach( $rows as $row )
			$fields[] = $row[0];
		
		return $fields;
	}
	
	function GetTables()
	{
		$tables = array();
			
		$this->db->Query( "SHOW TABLES" );
		$rows = $this->db->LoadAllResultArray();
		foreach( $rows as $row )
			$tables[] = $row[0];
		
		return $tables;
	}
	
	function Direct( $query )
	{
		return $this->db->Query( $query );
	}

    function StartTransaction()
    {
		$this->logger->Log( Logger::LOG_MSG, 'START TRANSACTION' );
        $this->db->BeginTransaction();
    }

    function Commit()
    {
		$this->logger->Log( Logger::LOG_MSG, 'COMMIT' );
        $this->db->Commit();
    }

    function Rollback()
    {
		$this->logger->Log( Logger::LOG_MSG, 'ROLLBACK' );
        $this->db->Rollback();
    }
	
	function Parse( $expression, $whereStatement=null )
	{
		$tree = $this->_Parse( $expression );
		
		//Dump( $tree );
		
		$travInfo = array();
		$this->_Traverse( $tree, $travInfo );
		
		$sql = "SELECT " . $travInfo['sql_fields'] . " FROM " . $travInfo['sql_from'];
		
		
		if( $whereStatement == null )
			$whereStatement = $travInfo['sql_where'];
		if( strlen($whereStatement) > 0 )
			$sql .= " WHERE " . $whereStatement;
		if( isset( $travInfo["sql_group_by"] ) )
			$sql .= " GROUP BY " . $travInfo["sql_group_by"];
			
		if( isset( $travInfo['sql_order_by'] ) )
			$sql .= " ORDER BY " . $travInfo['sql_order_by'] . " ASC";
		
		return $sql;
	}
	
	
	function _Parse( $toeval )
	{
		$pos = 0;
		$tokens = array();
        $toEvalLen = strlen($toeval);
		while( $token = $this->_NextToken( $toeval, $toEvalLen, $pos ) )
		{
			$tokens = array_merge( $tokens, $token );
		}
		
		$stack = array();
		while( 1 )
		{
			//echo "parser $pos stack-layout:'";
			//for($i=0;$i<count($stack);$i++)
			//	echo $stack[$i]['t_type'];
			//echo "'<br/>";
			$token = array_shift( $tokens );
			if( $token == null )
			{
				//echo "no more token...<br/>";
				break;
			}
			
			$nextToken = null;
			if( count($tokens) > 0 )
				$nextToken = &$tokens[0];
			
			array_unshift( $stack, $token );
			
			while( $this->_TryReduce( $stack, $nextToken ) > 0 )
				;
			
			//$this->Dump( $token );
			//echo "<br/>";
		}
		
		if( count($stack)==1 && $stack[0]['t_type']=='e' )
		{
			//echo "parse successful!!!<br/>";
			return $stack[0];
		}
		
		echo "QPath parse error with expression : <b>$toeval</b><br/>Stack content is :";
		Dump( $stack );
		
		return null;
	}
	
	function _IsReducable( &$stack, $test, $testLen )
	{
		if( count($stack) < $testLen )
			return false;

		for( $i=0; $i<$testLen; $i++ )
		{
			if( $test[$i] != $stack[$testLen-$i-1]['t_type'] )
				return false;
		}

		return true;
	}
	
	function _TryReduce( &$stack, &$nextToken )
	{
		if( $this->_IsReducable($stack,'s',1) )
		{
			$reduced = array_shift( $stack );
			array_unshift( $stack, array( 't_type'=>'e', 'type'=>'v', 'value'=>$reduced['t_val'] ) );
			return 1;
		}
		
		if( $this->_IsReducable($stack,'?e',2) )
		{
			$reduced = array_shift( $stack );
			array_shift( $stack );
			$reduced['muteFields'] = "true";
			array_unshift( $stack, $reduced );
			return 1;
		}
		
		// if next token will be '[' we should not reduce this one...
		if( $this->_IsReducable($stack,'eoe',3) && (($nextToken==null) || (($nextToken['t_type']!='[') && ($nextToken['t_type']!='G') && ($nextToken['t_type']!='A'))) )
		{
			$opRight = array_shift( $stack );
			$op = array_shift( $stack );
			$opLeft = array_shift( $stack );
			
			$reduced = array( 't_type'=>'e', 'type'=>$op['t_val'], 'left'=>$opLeft, 'right'=>$opRight );
			if( isset( $op['leftField'] ) )
				$reduced = array_merge( $reduced, array( 'leftField'=>$op['leftField'] ) );
			if( isset( $op['rightField'] ) )
				$reduced = array_merge( $reduced, array( 'rightField'=>$op['rightField'] ) );
			array_unshift( $stack, $reduced );
			return 1;
		}
		
		if( $this->_IsReducable($stack,'(e)',3) )
		{
			array_shift( $stack );
			$reduced = array_shift( $stack );
			array_shift( $stack );
			array_unshift( $stack, $reduced );
			return 1;
		}
		
		if( $this->_IsReducable($stack,'e[e]',4) )
		{
			array_shift( $stack );
			$where = array_shift( $stack );
			array_shift( $stack );
			$reduced = array_shift( $stack );
            if( ! isset( $reduced['where'] ) )
                $reduced['where'] = array();
			$reduced['where'][] = $where['value'];
			array_unshift( $stack, $reduced );
			return 1;
		}
		
		if( $this->_IsReducable($stack,'eG[e]',5) )
		{
			array_shift( $stack );
			$field = array_shift( $stack );
			array_shift( $stack );
			array_shift( $stack );			
			$reduced = array_shift( $stack );
			
			if( ! isset( $reduced['groupby'] ) )
                $reduced['groupby'] = array();
			$reduced['groupby'][] = $field['value'];
			
			array_unshift( $stack, $reduced );			
			return 1;
		}
		
		if( $this->_IsReducable($stack,'eA[e]',5) )
		{
			array_shift( $stack );
			$realTableName = array_shift( $stack );
			array_shift( $stack );
			array_shift( $stack );			
			$reduced = array_shift( $stack );
			
			$reduced['tableAlias'] = $realTableName['value'];
			
			array_unshift( $stack, $reduced );			
			return 1;
		}
		
		if( $this->_IsReducable($stack,'{e}o',4) )
		{
			$op = array_shift( $stack );
			array_shift( $stack );
			$leftField = array_shift( $stack );
			array_shift( $stack );
			
			$op['leftField'] = $leftField['value'];
			array_unshift( $stack, $op );
			return 1;
		}
		
		if( $this->_IsReducable($stack,'o{e}',4) )
		{
			array_shift( $stack );
			$rightField = array_shift( $stack );
			array_shift( $stack );
			$op = array_shift( $stack );
			
			$op['rightField'] = $rightField['value'];
			array_unshift( $stack, $op );
			return 1;
		}
		
		if( $this->_IsReducable($stack,'F[e]',4) )
		{
			array_shift( $stack );
			$field = array_shift( $stack );
			array_shift( $stack );
			array_shift( $stack );			
			array_unshift( $stack, array( 't_type'=>'f', 'val'=>$field ) );
			return 1;
		}
		
		if( $this->_IsReducable($stack,'fe',2) )
		{
			$expr = array_shift( $stack );
			$field = array_shift( $stack );
            if( ! isset( $expr['add_field'] ) )
                $expr['add_field'] = array();
			$expr['add_field'][] = $field['val'];
			array_unshift( $stack, $expr );
			return 1;
		}
		
		if( $this->_IsReducable($stack,'O[e]',4) )
		{
			array_shift( $stack );
			$field = array_shift( $stack );
			array_shift( $stack );
			array_shift( $stack );			
			array_unshift( $stack, array( 't_type'=>'t', 'val'=>$field ) );
			return 1;
		}
		
		if( $this->_IsReducable($stack,'te',2) )
		{
			$expr = array_shift( $stack );
			$field = array_shift( $stack );
			$expr['sort_field'] = $field['val'];
			array_unshift( $stack, $expr );
			return 1;
		}
		
		if( $this->_IsReducable($stack,'[e]',3) )
		{
			array_shift( $stack );
			$add_where = array_shift( $stack );
			array_shift( $stack );
			
            array_unshift( $stack, array( 't_type'=>'w', 'val'=>$add_where ) );
			return 1;
		}
		
		if( $this->_IsReducable($stack,'we',2) )
		{
			$expr = array_shift( $stack );
			$add_where = array_shift( $stack );
			
            if( ! isset( $expr['add_where'] ) )
                $expr['add_where'] = array();
			$expr['add_where'][] = $add_where['val'];
			array_unshift( $stack, $expr );
			return 1;
		}
		
		return 0;
	}
	
	// returns the next token and increments the position
	// returns null when no more token to come
    var $tokens = array( '(', ')', '[', ']', '{', '}', '?', 'G', 'F', 'O' );
    function _IsToken( $c )
    {
        switch( $c )
        {
            case '(':
            case ')':
            case '[':
            case ']':
            case '{':
            case '}':
            case '?':
            case 'G':
			case 'A':
            case 'F':
            case 'O':
                return true;
        }
        return false;
    }

	function _NextToken( &$text, $toEvalLen, &$pos )
	{
		$len = $toEvalLen;
		
		// skip whitespaces
		while( ($pos < $len) && ($text[$pos] == ' ') )
			$pos++;
		
		// end of text...
		if( $pos >= $len )
			return null;
		
		$token = null;
		
		$tokens = $this->tokens;
		
		// test one char tokens
		$tokenSize = 1;
		//if( in_array( $text[$pos], $tokens ) )
        if( $this->_IsToken( $text[$pos] ) )
		{
			$token = array( 't_type' => $text[$pos] );
			
			// if token is [ then produce the string going until next ]
			if( $text[$pos] == '[' )
			{
				$pos++; // pass the [
				$i = 0;
				while( ($pos+$i) < $len )
				{
					if( $text[$pos+$i] == ']' )
						break;
					$i++;
				}
				$tokString = array( 't_type'=>'s', 't_val'=>rtrim(substr($text,$pos,$i)) );
				$pos += $i;
				$tokenClose = array( 't_type' => ']' );
				$pos++;
				
				return array( $token, $tokString, $tokenClose );
			}
		}
		
		// test two chars tokens
		if( ($token==null) && ($pos+1<$len) )
		{
			$tokenSize = 2;
			if( $text[$pos]=='<' && $text[$pos+1]=='-' ) $token = array( 't_type'=>'o', 't_val'=>'<-' );
			else if( $text[$pos]=='-' && $text[$pos+1]=='>' ) $token = array( 't_type'=>'o', 't_val'=>'->' );
		}
		
		// test for a token string
		if( $token == null )
		{
			$i = 0;
			while( ($pos+$i) < $len )
			{
				$c = $text[$pos+$i];
				//if( in_array( $c, $tokens ) )
                if( $this->_IsToken( $c ) )
					break;
				if( ($c=='-') && ($pos+$i+1<$len) && ($text[$pos+$i+1]==">") )
						break;
				if( ($c=='<') && ($pos+$i+1<$len) && ($text[$pos+$i+1]=="-") )
						break;
				$i++;
			}
			
			if( $i > 0 )
			{
				$tokenSize = $i;
				$token = array( 't_type'=>'s', 't_val'=>rtrim(substr($text,$pos,$i)) );
			}
		}
			
		if( $token == null )
			return null;
		$pos += $tokenSize;
		return array( $token );
	}
	
	function _FilterClause( $clause, $aliasTable )
	{
		if( substr( $clause, 0, 1 ) == "!" )
			return substr( $clause, 1 );
		
		return "$aliasTable.$clause";
	}
	
	function _Traverse( $tree, &$travInfo )
	{
		switch( $tree['type'] )
		{
			case '->':
			case '<-':
				$leftTravInfo = array();
				$left = $this->_Traverse( $tree['left'], $leftTravInfo );
				
				$rightTravInfo = array();
				$right = $this->_Traverse( $tree['right'], $rightTravInfo );
				
				$leftTableAlias = $leftTravInfo['table'];
				if( isset( $leftTravInfo['tableAlias'] ) )
					$leftTableAlias = $leftTravInfo['tableAlias'];
					
				$rightTableAlias = $rightTravInfo['table'];
				if( isset( $rightTravInfo['tableAlias'] ) )
					$rightTableAlias = $rightTravInfo['tableAlias'];
				
				if( $tree['type'] == '->' )
				{
					$leftField = $leftTableAlias . '.' . Singularize($rightTravInfo['table']).'_id';
					$rightField = $rightTableAlias . '.id';
				}
				else if( $tree['type'] == '<-' )
				{
					$leftField = $leftTableAlias . '.id';
					$rightField = $rightTableAlias . '.' . Singularize($leftTravInfo['table']).'_id';
				}
				
				// custom fields
				if( isset( $tree['leftField'] ) )
					$leftField = $leftTableAlias . '.' . $tree['leftField'];
				if( isset( $tree['rightField'] ) )
					$rightField = $rightTableAlias . '.' . $tree['rightField'];
				
				$params = "";
				if( isset( $tree['params'] ) )
					$params = $tree['params'];
				
				$travInfo["table"] = $leftTravInfo['table'];
				if( isset( $leftTravInfo['tableAlias'] ) )
					$travInfo["tableAlias"] = $leftTravInfo['tableAlias'];
					
				$travInfo["sql_from"] = ' ( '. $leftTravInfo['sql_from'] . ' LEFT JOIN ' . $rightTravInfo['sql_from'] . ' ON ' . $leftField . '=' . $rightField . ' ) ';
				$travInfo["sql_where"] = ' ('. $leftTravInfo["sql_where"] . ') AND (' . $rightTravInfo["sql_where"] . ') ';
				if( isset( $tree['where'] ) )
                {
                    foreach( $tree['where'] as $clause )
                        $travInfo["sql_where"] .= ' AND (' . $this->_FilterClause( $clause, $leftTableAlias ) . ')';
                }
				if( isset( $tree['add_where'] ) )
				{
                    foreach( $tree['add_where'] as $clause )
                        $travInfo["sql_where"] .= ' AND (' . $clause['value'] . ')';
				}

				// group by
				$groupby = array();
				if( isset( $leftTravInfo['sql_group_by'] ) )
					$groupby[] = $leftTravInfo['sql_group_by'];
				if( isset( $rightTravInfo['sql_group_by'] ) )
					$groupby[] = $rightTravInfo['sql_group_by'];
				$gb = implode( ',', $groupby );
				if( strlen( $gb ) > 0 )
					$travInfo["sql_group_by"] = $gb;
				
				// fields
				$travInfo["sql_fields"] = "";
				if( $leftTravInfo["sql_fields"] != null )
				{
					if( $rightTravInfo["sql_fields"] != null )
						$travInfo["sql_fields"] = $leftTravInfo["sql_fields"] . ', ' . $rightTravInfo["sql_fields"];
					else
						$travInfo["sql_fields"] = $leftTravInfo["sql_fields"];
				}
				else
				{
					if( $rightTravInfo["sql_fields"] != null )
						$travInfo["sql_fields"] = $rightTravInfo["sql_fields"];
				}
				
				// added fields
				if( isset( $tree['add_field'] ) )
				{
                    $fields = array();
                    foreach( $tree['add_field'] as $f )
                        $fields[] = $f['value'];
                    $fields = implode( ',', $fields );
					if( $travInfo["sql_fields"] != "" )
						$travInfo["sql_fields"] = implode( ', ', array( $travInfo["sql_fields"], $fields ) );
					else
						$travInfo["sql_fields"] = $fields;
				}
				
				// sort fields
				$sortby = array();
				if( isset( $leftTravInfo['sql_order_by'] ) )
					$sortby[] = $leftTravInfo['sql_order_by'];
				if( isset( $rightTravInfo['sql_order_by'] ) )
					$sortby[] = $rightTravInfo['sql_order_by'];
				if( isset( $tree['sort_field'] ) )
					$sortby[] = $tree['sort_field']['value'];
				if( count($sortby) > 0 )
					$travInfo['sql_order_by'] = implode( ', ', $sortby );
				
				break;
				
			case 'v':
				$travInfo = array();
				
				$travInfo["table"] = $tree['value'];
				$realTable = $tree['value'];
				$aliasTable = $tree['value'];
				if( isset( $tree['tableAlias'] ) )
				{
					$aliasTable = $tree['tableAlias'];
					$travInfo["tableAlias"] = $tree['tableAlias'];
					$travInfo["sql_from"] = $realTable . " AS " . $tree['tableAlias'];
				}
				else
				{
					$travInfo["sql_from"] = $realTable;
				}
				if( isset( $tree['where'] ) )
                {
                    $clauses = array();
                    foreach( $tree['where'] as $clause )
                        $clauses[] = "( " . $this->_FilterClause( $clause, $aliasTable ) . " )";
                    $travInfo["sql_where"] = implode( ' AND ', $clauses );
                }
				else
                {
					$travInfo["sql_where"] = "1=1";
                }
				if( isset( $tree['add_where'] ) )
				{
                    foreach( $tree['add_where'] as $clause )
                        $travInfo["sql_where"] .= ' AND (' . $clause['value'] . ')';
				}

				if( isset( $tree['groupby'] ) )
				{
					$groupBy = array();
					foreach( $tree['groupby'] as $field )
						$groupBy[] = $this->_FilterClause( $field, $aliasTable );
					$travInfo["sql_group_by"] = implode( ', ', $groupBy );
				}
				
				// maybe fields are to be muted...
				if( ! isset( $tree['muteFields'] ) )
					$travInfo["sql_fields"] = $this->_GetFields( $realTable, $aliasTable );//$travInfo["table"] );
				else
					$travInfo["sql_fields"] = null;
				
				if( isset( $tree['add_field'] ) )
				{
                    $fields = array();
                    foreach( $tree['add_field'] as $f )
                        $fields[] = $f['value'];
                    $fields = implode( ',', $fields );
					if( $travInfo["sql_fields"] != null )
						$travInfo["sql_fields"] = implode( ',', array( $fields, $travInfo["sql_fields"] ) );
					else
						$travInfo["sql_fields"] = $fields;
				}
				
				// order by
				if( isset( $tree['sort_field'] ) )
					$travInfo['sql_order_by'] = $tree['sort_field']['value'];
				
				break;
				
			default:
				return "ERROR";
		}
	}
	
	var $cacheFields = array();
	var $cacheFieldsExhaustive = array();
	
	function _GetFields( $tableName, $aliasName )
	{
		$this->_EnsureCachedTableFields( $tableName );
		
		$fields = array();
		foreach( $this->cacheFields[$tableName] as $field )
			$fields[] = $aliasName . "." . $field . ' AS "' . $aliasName . "." . $field . '"';
		
		return implode( ", ", $fields );
	}
	
	private function _EnsureCachedTableFields( $tableName )
	{
		if( isset( $this->cacheFields[$tableName] ) )
			return;
		
		global $IGNORED_FIELDS;
			
		$this->cacheFields[$tableName] = array();
		$this->cacheFieldsExhaustive[$tableName] = array();
			
		$res = $this->db->Query( "DESCRIBE " . $tableName );
		if( ! $res )
			return "*TABLE $tableName DOES NOT EXIST!!!*";
				
		$rows = $this->db->LoadAllResultArray();
		foreach( $rows as $row )
		{
			$this->cacheFieldsExhaustive[$tableName][] = $row[0];
			
			if( ! in_array( $row[0], $IGNORED_FIELDS ) )
				$this->cacheFields[$tableName][] = $row[0];
		}
	}
}

?>