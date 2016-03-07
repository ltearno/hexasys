<?php

// helper
function DBFieldDescHasReference( $table, $field, $refs )
{
    foreach( $refs as $ref )
        if( $ref['table'] == $table && $ref['field'] == $field )
            return true;

    return false;
}

class Database
{
    var $host = null;
    var $port = null;
    var $user = null;
    var $password = null;
    var $database = null;

    var $pdo = null;
    var $statement = null;

    var $errorMsg = '';

    var $logger = null;

    public function Init( $options )
    {
        //$this->host		= array_key_exists('host', $options)	? $options['host']		: $this->host;
        $hostAndPort = array_key_exists( 'host', $options ) ? $options['host'] : $this->host;
        $parts = explode( ":", $hostAndPort );
        if( count( $parts ) == 1 )
        {
            $this->host = $hostAndPort;
            $this->port = null;
        }
        else if( count( $parts ) == 2 )
        {
            $this->host = $parts[0];
            $this->port = $parts[1];
        }
        else
        {
            echo "BAD CONFIGURATION FOR PDO, HOST SPEC IS INVALID : $hostAndPort !!!";
            // TODO : should explode at this point, anyway it will in few moments...
        }

        $this->user = array_key_exists( 'user', $options ) ? $options['user'] : $this->user;
        $this->password = array_key_exists( 'password', $options ) ? $options['password'] : $this->password;
        $this->database = array_key_exists( 'database', $options ) ? $options['database'] : $this->database;

        $this->logger = new Logger();
        $this->logger->Init( 'database-pdo-' . ($this->database == null ? "notselected" : $this->database) . '.txt', Logger::LOG_MSG );

        $this->Connect( $this->database );
    }

    // return what should be placed after a = or inside a values update statement in a sql query
    public function Quote( $string )
    {
        if( is_null( $string ) )
            return "NULL"; // needed for QPath but maybe that's what pdo->Quote returns anyway...

        if( is_int( $string ) && $string == 0 )
            return "0";

        // because it would transform an empty string into NULL (to be verified...)
        if( (!is_null( $string )) && ($string == "") )
            return "''";

        return $this->pdo->quote( $string );
    }

    public function IsError()
    {
        // if there is no pdo configured or the configured pdo is in error, return TRUE !
        if( $this->pdo == null || $this->pdo->errorCode() != 0 )
            return true;

        // if the last statement is in error, return TRUE !
        if( $this->statement != null && $this->statement->errorCode() != 0 )
            return true;

        // otherwise, everything seems ok...
        return false;
    }

    function Error( $msg )
    {
        $this->errorMsg = "ERROR " . $msg . " / " . $this->GetErrorInfoText();

        $this->logger->Log( Logger::LOG_ERR, $this->errorMsg );

        //$this->logger->Log( Logger::LOG_ERR, GetDump( debug_backtrace( FALSE ) ) );
    }

    function OK()
    {
        $this->errorMsg = null;
    }

    public function Explain()
    {
        echo $this->GetErrorText();
    }

    public function GetErrorText()
    {
        if( $this->errorMsg == null )
            return "Database : OK";
        else if( $this->pdo == null )
            return "Error Database : no connection established";
        else
            return $this->errorMsg;
    }

    // TODO : this is used only in the installation process, todo = find another way for that !
    public function SelectDatabase( $database )
    {
        return $this->Connect( $database );
    }

    public function QueryDDL( $sql )
    {
        if( $this->pdo == null )
        {
            $this->Error( "TRYING TO SQL WHEN NO DB IS SELECTED, SQL:$sql" );

            return null;
        }

        $this->statement = $this->pdo->prepare( $sql );
        if( $this->statement == null )
        {
            $this->logger->Log( Logger::LOG_ERR, "QUERY_DDL : $sql" );
            $this->Error( "When doing QueryDdl( $sql )" );

            return null;
        }

        $res = $this->statement->execute();
        $this->statement = null;
        if( $res === false )
        {
            $this->logger->Log( Logger::LOG_ERR, 'QUERY_DDL : ' . $sql );
            $this->Error( "When doing QueryDdl( $sql )" );

            return null;
        }

        return true;
    }

    public function Query( $sql )
    {
        if( $this->pdo == null )
        {
            $this->Error( "TRYING TO SQL WHEN NO DB IS SELECTED, SQL:$sql" );

            return null;
        }

        $this->FreeResult();

        // start a time measure
        $m = HLib( "Measure" )->Start();

        $this->statement = $this->pdo->prepare( $sql );
        if( $this->statement == null )
        {
            $this->logger->Log( Logger::LOG_ERR, 'QUERY : ' . $sql );
            $this->Error( "When doing Query( $sql )" );

            return null;
        }

        $res = $this->statement->execute();

        $ms = HLib( "Measure" )->End( $m );
        if( $ms > 1000 )
        {
            $log = new Logger();
            $log->Init( 'database-pdo-long_requests-' . $this->database . '.txt', Logger::LOG_MSG );
            $log->Log( Logger::LOG_MSG, "Time for a request $ms ms. for request : '$sql" );
            $log->Term();
        }

        if( !$res )
        {
            $this->logger->Log( Logger::LOG_ERR, 'QUERY : ' . $sql );
            $this->Error( "When doing Query( $sql )" );

            $this->FreeResult();

            return null;
        }

        return true;
    }

    public function InsertedId()
    {
        if( $this->pdo == null )
            return -1;

        return $this->pdo->lastInsertId();
    }

    public function GetNumRows()
    {
        if( $this->statement == null )
            return 0;

        return $this->statement->rowCount();
    }

    public function GetFieldsNames()
    {
        $ret = array();
        $numFields = $this->statement->columnCount();
        for( $i = 0; $i < $numFields; $i++ )
        {
            $meta = $this->statement->getColumnMeta( $i );
            $ret[] = $meta["name"];
        }

        return $ret;
    }

    public function GetFields()
    {
        $ret = array();
        $numFields = $this->statement->columnCount();
        for( $i = 0; $i < $numFields; $i++ )
        {
            $meta = $this->statement->getColumnMeta( $i );
            $ret[$meta["name"]] = $i;
        }

        return $ret;
    }

    public function LoadResultArray()
    {
        return $this->statement->fetch( PDO::FETCH_NUM );
    }

    public function LoadAllResultArray( $numinarray = 0 )
    {
        return $this->statement->fetchAll( PDO::FETCH_NUM );
    }

    public function LoadResultAssoc()
    {
        return $this->statement->fetch( PDO::FETCH_ASSOC );
    }

    public function LoadAllResultAssoc()
    {
        return $this->statement->fetchAll( PDO::FETCH_ASSOC );
    }

    public function FreeResult()
    {
        if( $this->statement != null )
        {
            $this->statement->closeCursor();
            $this->statement = null;
        }
    }

    //
    // Transactions
    //

    var $transactionStack = array();

    public function StartTransaction()
    {
        $this->FreeResult();

        if( count( $this->transactionStack ) == 0 )
        {
            // main tx level, start a transaction
            $transactionId = "main";
            $this->pdo->exec( "START TRANSACTION" );
        }
        else
        {
            // nested transaction : use SAVEPOINTs, this requires InnoDB engine
            $transactionId = "tx_" . count( $this->transactionStack );
            $this->pdo->exec( "SAVEPOINT $transactionId" );
        }

        $this->transactionStack[] = array( "tx_id" => $transactionId, "status" => true );

        return $transactionId;
    }

    public function AbortTransaction()
    {
        $this->FreeResult();

        if( count( $this->transactionStack ) == 0 )
            throw new Exception( "Called AbortTransaction while no transaction was started !" );

        // save the current transaction's status
        $this->transactionStack[count( $this->transactionStack ) - 1]["status"] = false;

        return true;
    }

    public function CloseTransaction( $transactionId )
    {
        $this->FreeResult();

        if( count( $this->transactionStack ) == 0 )
            throw new Exception( "Called CloseTransaction while a transaction was not started !" );

        $currentTransactionInfo = array_pop( $this->transactionStack );
        $currentTransactionId = $currentTransactionInfo["tx_id"];
        $currentTransactionStatus = $currentTransactionInfo["status"];

        if( $currentTransactionId != $transactionId )
            throw new Exception( "Called CloseTransaction on tx_id '$transactionId' but the current tx_id is '$currentTransactionId'" );

        if( $currentTransactionId == "main" )
        {
            if( $currentTransactionStatus )
                $this->pdo->exec( "COMMIT" );
            else
                $this->pdo->exec( "ROLLBACK" );
        }
        else
        {
            if( $currentTransactionStatus )
                $this->pdo->exec( "RELEASE SAVEPOINT $currentTransactionId" );
            else
                $this->pdo->exec( "ROLLBACK TO SAVEPOINT $currentTransactionId" );
        }
    }

    //
    // Internals
    //

    private function Connect( $database )
    {
        $this->database = $database;

        $dsn = "mysql:dbname=$database;host=" . $this->host;

        if( !empty($this->port) )
            $dsn .= ";port=" . $this->port;

        if( $this->pdo != null )
            $this->logger->Log( Logger::LOG_MSG, "Reconnecting to DSN:$dsn USER:" . $this->user );

        try
        {
            $this->pdo = null;
            $this->pdo = new PDO( $dsn, $this->user, $this->password );//, array("PDO::MYSQL_ATTR_INIT_COMMAND"=>"SET NAMES UTF8") );

            $this->pdo->exec( 'SET NAMES utf8;' );
            $this->pdo->exec( "SET @@session.sql_mode= 'NO_ENGINE_SUBSTITUTION';" );
            $this->pdo->exec( "SET time_zone = '+0:00';" );

            //$this->pdo->exec( "SET autocommit=0" );

            return true;
        } catch( PDOException $e )
        {
            $this->pdo = null;
            $this->Error( 'Cannot connect to mysql server' );
            $this->logger->Log( Logger::LOG_ERR, "Cannot connect to mysql server. DSN:$dsn USER:" . $this->user . " PASSWORD:xxxxxx MESSAGE:" . $e->getMessage() );

            return false;
        }
    }

    private function GetErrorInfoText()
    {
        if( $this->statement != null )
        {
            $msg = "TYPE:STMT";
            $errorInfo = $this->statement->errorInfo();
        }
        else if( $this->pdo != null )
        {
            $msg = "TYPE:PDO";
            $errorInfo = $this->pdo->errorInfo();
        }
        else
        {
            return "NOT_INITIALIZED:No error information";
        }

        if( $errorInfo == null )
            return "No error information";

        return "$msg CODE:" . $errorInfo[0] . " DRVCODE:" . $errorInfo[1] . " MSG:" . $errorInfo[2];
    }


    //
    // Public methods that could be located in another class, since they utilize Database class only...
    //


    public function GetCachedDatabaseDescription()
    {
        // use cached data if possible...
        $dbFile = "currentDatabase.inc.php";
        if( file_exists( $dbFile ) )
        {
            $dbDesc = eval(file_get_contents( $dbFile ) . ' return $dbDesc;');

            return $dbDesc;
        }

        return null;
    }

    public function GetDatabaseName()
    {
        return $this->database;
    }

    // returns an array containing the description of the database schema
    public function GetDatabaseDescription( $dbName = null )
    {
        if( $dbName == null )
            $dbName = $this->database;

        $dbDesc = array();

        $this->Query( "SHOW TABLES FROM " . $dbName );
        $rows = $this->LoadAllResultArray();
        foreach( $rows as $row )
        {
            $table = $row[0];

            if( 0 == strncmp( $table, "z_sscs_", 7 ) )
                continue;
            if( 0 == strncmp( $table, "y_sscs_", 7 ) )
                continue;
            if( 0 == strncmp( $table, "synchro_server_client_states_", 29 ) )
                continue;

            $tableDesc = array();
            $tableDesc['fields'] = array();

            $this->Query( "DESCRIBE " . $table );
            $fields = $this->LoadAllResultAssoc();
            foreach( $fields as $field )
            {
                $fieldName = $field['Field'];

                if( 0 == strncmp( $fieldName, "synchro_server", 14 ) )
                    continue;

                $tableDesc['fields'][$fieldName] = array( "type" => $field['Type'], "null" => $field['Null'], "default" => $field['Default'], "extra" => $field['Extra'] );

                if( $field['Key'] == "PRI" )
                    $tableDesc['fields'][$fieldName]["primary_key"] = true;
                else if( $field['Key'] == "UNI" )
                    $tableDesc['fields'][$fieldName]["unique_key"] = true;
                else if( $field['Key'] == "MUL" )
                    $tableDesc['fields'][$fieldName]["multiple_index"] = true;

                $this->Query( "SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='$dbName' AND TABLE_NAME='$table' AND COLUMN_NAME='$fieldName'" );
                $info = $this->LoadAllResultAssoc();
                if( count( $info ) != 1 )
                {
                    echo "BIG PROBLEM, column has no or multiple definitions !!!";

                    return;
                }
                $info = $info[0];

                if( $info['COLUMN_COMMENT'] != null )
                    $tableDesc['fields'][$fieldName]['comment'] = $info['COLUMN_COMMENT'];
            }

            $dbDesc[$table] = $tableDesc;
        }

        // Show constraints
        $this->Query( "SELECT * FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA='" . $dbName . "'" );
        $constraints = $this->LoadAllResultAssoc();

        foreach( $constraints as $constraint )
        {
            $table = $constraint['TABLE_NAME'];

            if( !isset($dbDesc[$table]) )
                continue;

            $fieldName = $constraint['COLUMN_NAME'];
            if( $constraint['CONSTRAINT_NAME'] == "PRIMARY" )
            {
                $dbDesc[$table]['fields'][$fieldName]["primary_key"] = true;
            }
            else
            {
                $refTable = $constraint['REFERENCED_TABLE_NAME'];
                $refField = $constraint['REFERENCED_COLUMN_NAME'];

                if( $refTable == null || $refField == null )
                    continue; // what is that ??

                if( !isset($dbDesc[$table]['fields'][$fieldName]["references"]) )
                    $dbDesc[$table]['fields'][$fieldName]["references"] = array();

                if( DBFieldDescHasReference( $refTable, $refField, $dbDesc[$table]['fields'][$fieldName]["references"] ) )
                    continue; // ignore duplicated references

                $dbDesc[$table]['fields'][$fieldName]["references"][] = array( "table" => $refTable, "field" => $refField );
            }
        }

        return $dbDesc;
    }
}

?>