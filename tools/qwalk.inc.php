<?php

$qwalk_history = array();
class QWalk
{
    /* @var $db Database */
    var $db = null;

    /* @var $rawRecord array */
    var $table = '';
    var $idField = null;
    var $id = -1;
    var $rawRecord = null;

    var $cacheChildren = array();

    static $cachedTables = array();

    // @return QWalk
    public static function newQWalk( Database $db, $table, $idField, $id, $rawRecord = null )
    {
		// Caching is a good idea, but sometimes since it is never invalidated it gives bad data...
        //if( isset( QWalk::$cachedTables[$table.'*'.$idField][$id] ) )
        //    return QWalk::$cachedTables[$table.'*'.$idField][$id];
		
		//if( ! isset( QWalk::$cachedTables[$table.'*'.$idField] ) )
        //    QWalk::$cachedTables[$table.'*'.$idField] = array();

        $w = new QWalk( $db, $table, $idField, $id, $rawRecord );
		if( $w->rawRecord == null )
			return null;

        //QWalk::$cachedTables[$table.'*'.$idField][$id] = $w;
        
        return $w;
    }

    private function __construct( Database $db, $table, $idField, $id, $rawRecord = null )
    {
        $this->db = $db;

        $this->table = $table;
        $this->idField = $idField;
        $this->id = $id;
        if( $rawRecord == null )
        {
            $this->query( "SELECT * FROM $table WHERE $idField=$id" );
            $this->rawRecord = $this->db->LoadResultAssoc();
        }
        else
        {
            $this->rawRecord = $rawRecord;
        }
    }

    public function raw()
    {
        return $this->rawRecord;
    }

    public function rawNorm()
    {
        $res = array();
        foreach( $this->rawRecord as $field => $value )
            $res[$this->table.'.'.$field] = $value;
        return $res;
    }

    private function query( $sql )
    {
        global $qwalk_history;
        $qwalk_history[] = $sql;
        $this->db->Query( $sql );
    }

    public function  __call( $name,  $arguments )
    {
        $extTable = null;
        $extIdField = null;
        if( isset( $arguments[0] ) )
            $extTable = $arguments[0];
        if( isset( $arguments[1] ) )
            $extIdField = $arguments[1];

        return $this->getFather( $name, $extTable, $extIdField );
    }

    public function  __get($name)
    {
        return $this->get( $name );
    }

    public function get( $field, $externalTable=null, $externalIdField = null )
    {
        return $this->rawRecord[$field];
        /*
        if( substr( $field, -4 ) == "_ids" )
        {
            $value = $this->rawRecord[$field];
            if( strlen( $value ) == 0 )
                return array();
            return explode( ',', $value );
        }
        else
        {
            return $this->rawRecord[$field];
        }
        */
    }

    public function getFather( $field, $externalTable=null, $externalIdField = null )
    {
        if( $externalTable == null )
            $externalTable = Pluralize( substr( $field, 0, -3 ) );
        if( $externalIdField == null )
            $externalIdField = "id";

        $father = QWalk::newQWalk( $this->db, $externalTable, $externalIdField, $this->rawRecord[$field] );

        return $father;
    }

    public function c( $externalTable, $externalIdField = null, $externalRefIdField = null )
    {
        return $this->getChildren( $externalTable, $externalIdField, $externalRefIdField );
    }

    public function getChildren( $externalTable, $externalIdField = null, $externalRefIdField = null )
    {
        if( $externalIdField == null )
            $externalIdField = 'id';
        if( $externalRefIdField == null )
            $externalRefIdField = Singularize( $this->table ) . "_id";

        if( isset( $this->cacheChildren[$externalTable.'*'.$externalRefIdField] ) )
            return $this->cacheChildren[$externalTable.'*'.$externalRefIdField];

        // search all entries pointing to us and return an array of QWalk
        $this->query( "SELECT * FROM $externalTable WHERE $externalRefIdField=".$this->id );
        $childs = $this->db->LoadAllResultAssoc();
        $res = array();
        foreach( $childs as $childDB )
        {
            $child = QWalk::newQWalk( $this->db, $externalTable, $externalIdField, $childDB[$externalIdField], $childDB );

            $res[$childDB[$externalIdField]] = $child;
        }

        // cache the chilren and remember we have the full table now...
        $this->cacheChildren[$externalTable.'*'.$externalRefIdField] = $res;

        return $res;
    }
}



?>