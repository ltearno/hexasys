<?php

class Installation extends HexaComponentImpl
{
    // returns the database object with the database ready and selected
    // it creates the database if needed
    public function EnsureDbExists( $host, $user, $password, $database )
    {
        $db = new Database();
        $db->Init( array( "host" => $host, "user" => $user, "password" => $password ) );

        $db->Query( "SHOW DATABASES LIKE '$database'" );
        $dbs = $db->LoadAllResultAssoc();
        if( count( $dbs ) == 0 )
        {
            $db->Query( "CREATE DATABASE `$database` DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci" );
            if( $db->IsError() )
            {
                echo "Cannot create database $database<br/>";
                $db->Explain();

                return null;
            }
        }

        $db->SelectDatabase( $database );
        if( $db->IsError() )
        {
            echo "Cannot select database $database<br/>";
            $db->Explain();

            return null;
        }

        return $db;
    }

    // returns the array of sql statements to be executed to update the db
    // currentDB is a kind of a hack : for the deletion of a constraint in the target database, we need to query the db for all the constraint names to delete them
    public function GetSqlForUpdateDb( $currentDbDesc, $targetDbDesc, $currentDB, $doDelete = true )
    {
        // differences :
        $curTables = array_keys( $currentDbDesc );
        $tgtTables = array_keys( $targetDbDesc );

        $newTables = array_diff( $tgtTables, $curTables );
        $removedTables = array_diff( $curTables, $tgtTables );
        $maybeModifiedTables = array_diff( $tgtTables, $newTables );

        $sqls = array();
        $sqlsRefs = array(); // sql statements related to constraints, need to be executed at the end

        // new tables
        foreach( $newTables as $newTable )
        {
            $desc = $targetDbDesc[$newTable];

            $sql = "CREATE TABLE `$newTable` ( ";
            $coma = false;
            $keys = array();
            $primaryKeys = array();
            foreach( $desc["fields"] as $fieldName => $fieldDesc )
            {
                if( $coma )
                    $sql .= ", ";
                $coma = true;
                $sql .= $this->GetColumnSql( $fieldName, $fieldDesc, $currentDB );

                if( array_key_exists( "primary_key", $fieldDesc ) )
                    $primaryKeys[] = "`$fieldName`";
                else if( array_key_exists( "unique_key", $fieldDesc ) )
                    $keys[] = "UNIQUE KEY `$fieldName` (`$fieldName`)";
                else if( array_key_exists( "multiple_index", $fieldDesc ) )
                    $keys[] = "KEY `$fieldName` (`$fieldName`)";
            }
            if( count( $primaryKeys ) > 0 )
                $keys[] = "PRIMARY KEY (" . implode( ",", $primaryKeys ) . ")";
            if( count( $keys ) > 0 )
                $sql .= ", " . implode( ", ", $keys );
            $sql .= " ) ENGINE=InnoDB  DEFAULT CHARSET=utf8";

            $sqls[] = $sql;

            foreach( $desc["fields"] as $fieldName => $fieldDesc )
                $this->CheckReferences( $newTable, $fieldName, null, $fieldDesc, $sqlsRefs, $currentDB );

            $curIndices = isset($currentDbDesc[$newTable]) ? (isset($currentDbDesc[$newTable]['indices']) ? $currentDbDesc[$newTable]['indices'] : null) : null;
            $this->CheckIndices( $newTable, $curIndices, isset($desc["indices"]) ? $desc["indices"] : null, $sqlsRefs );
        }


        // removed tables
        foreach( $removedTables as $removedTable )
        {
            if( $doDelete )
            {
                //echo "Deleted table $removedTable<br/>";
                $sqls[] = "DROP TABLE `$removedTable`";
            }
            else
            {
                //echo "IGNORED - Deleted table $removedTable<br/>";
            }
        }

        // modified tables
        foreach( $maybeModifiedTables as $table )
        {
            $tgt = $targetDbDesc[$table];
            $cur = $currentDbDesc[$table];
            if( arrays_eq( $tgt, $cur ) )
                continue;

            // check fields
            $tgtFields = $tgt["fields"];
            $curFields = $cur['fields'];
            if( !arrays_eq( $tgtFields, $curFields ) )
            {
                $curNames = array_keys( $curFields );
                $tgtNames = array_keys( $tgtFields );

                $newFields = array_diff( $tgtNames, $curNames );
                $removedFields = array_diff( $curNames, $tgtNames );
                $maybeModifiedFields = array_diff( $tgtNames, $newFields );

                foreach( $newFields as $newField )
                {
                    $sqls[] = "ALTER TABLE `$table` ADD " . $this->GetColumnSql( $newField, $tgtFields[$newField], $currentDB );
                }

                foreach( $removedFields as $removedField )
                {
                    if( $doDelete )
                    {
                        $sqls[] = "ALTER TABLE `$table` DROP `$removedField` ";
                    }
                }

                foreach( $maybeModifiedFields as $field )
                {
                    // modified references
                    $this->CheckReferences( $table, $field, $curFields[$field], $tgtFields[$field], $sqlsRefs, $currentDB );

                    // modified comment
                    $curComment = isset($curFields[$field]['comment']) ? $curFields[$field]['comment'] : "";
                    $tgtComment = isset($tgtFields[$field]['comment']) ? $tgtFields[$field]['comment'] : "";
                    if( $tgtComment != $curComment )
                        $sqls[] = "ALTER TABLE `$table` CHANGE `$field` " . $this->GetColumnSql( $field, $tgtFields[$field], $currentDB );
                }
            }

            // modified indices
            $curIndices = isset($cur['indices']) ? $cur['indices'] : null;
            $this->CheckIndices( $table, $curIndices, isset($tgt["indices"]) ? $tgt["indices"] : null, $sqlsRefs );
        }

        return array_merge( $sqls, $sqlsRefs );
    }

    public function GetCountryCodesSQL()
    {
        $location = "install/country_codes.sql";
        $command = file_get_contents( $location );

        // Filter INSERT INTO command
        $lines = explode( "\n", $command );
        for( $i = 0; $i < count( $lines ); $i++ )
        {
            if( preg_match( "/^INSERT INTO/", $lines[$i] ) )
            {
                break;
            }
            else
            {
                // Skip line
                unset($lines[$i]);
            }
        }
        $command = join( " ", $lines );

        //echo $command;
        return $command;

    }

    private function HasSimilarIndex( $indices, $columns )
    {
        $nbColumns = count( $columns );
        foreach( $indices as $cs )
        {
            if( count( $cs ) != $nbColumns )
                continue;
            $not = false;
            for( $i = 0; $i < $nbColumns; $i++ )
                if( $cs[$i] != $columns[$i] )
                {
                    $not = true;
                    break;
                }
            if( $not )
                continue;

            return true;
        }

        return false;
    }

    private function CheckIndices( $tableName, $curIndices, $tgtIndices, &$sqls )
    {
        $curIndices = $curIndices == null ? array() : $curIndices;
        $tgtIndices = $tgtIndices == null ? array() : $tgtIndices;

        foreach( $curIndices as $indexName => $columns )
        {
            if( $this->HasSimilarIndex( $tgtIndices, $columns ) )
                continue;

            echo "To be deleted index in table $tableName : '$indexName' on columns " . implode( ', ', $columns ) . "<br/>";

            $sqls[] = "DROP INDEX $indexName ON $tableName";
        }

        foreach( $tgtIndices as $indexName => $columns )
        {
            if( $this->HasSimilarIndex( $curIndices, $columns ) )
                continue;

            echo "To be created index in table $tableName : '$indexName' on columns " . implode( ', ', $columns ) . "<br/>";

            $columns = array_map( function( $e )
            {
                return "`$e`";
            }, $columns );
            $sqls[] = "CREATE INDEX $indexName ON $tableName (" . implode( ', ', $columns ) . ")";
        }
    }

    private function CheckReferences( $tableName, $fieldName, $curField, $tgtField, &$sqls, Database $currentDB )
    {
        $curRefs = $curField == null ? array() : (isset($curField["references"]) ? $curField["references"] : array());
        $tgtRefs = isset($tgtField["references"]) ? $tgtField["references"] : array();

        foreach( $curRefs as $ref )
        {
            // if a similar ref is not found in $tgtRefs, that is this reference must be deleted
            if( !DBFieldDescHasReference( $ref['table'], $ref['field'], $tgtRefs ) )
            {
                echo "Deleted reference, field $fieldName references " . $ref['table'] . "." . $ref['field'] . "<br/>";

                // The statement DROP FOREIGN KEY doesnt seem to work with a variable as constraint name parameter, halas...
                //$sqls[] = "SELECT (@v:=CONSTRAINT_NAME) FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$tableName' AND COLUMN_NAME='$fieldName' AND REFERENCED_TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME='".$ref['table']."' AND REFERENCED_COLUMN_NAME='".$ref['field']."'";
                //$sqls[] = "ALTER TABLE `$tableName` DROP FOREIGN KEY `@v`";

                $currentDB->Query( "SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$tableName' AND COLUMN_NAME='$fieldName' AND REFERENCED_TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME='" . $ref['table'] . "' AND REFERENCED_COLUMN_NAME='" . $ref['field'] . "'" );
                $constraints = $currentDB->LoadAllResultAssoc();
                foreach( $constraints as $constraint )
                {
                    $constraintName = $constraint['CONSTRAINT_NAME'];
                    $sqls[] = "ALTER TABLE `$tableName` DROP FOREIGN KEY `$constraintName`";
                }
            }
        }

        foreach( $tgtRefs as $ref )
        {
            // if a similar ref is not found in $curRefs, that is this reference is a new to be created
            if( !DBFieldDescHasReference( $ref['table'], $ref['field'], $curRefs ) )
            {
                echo "New reference, field $fieldName references " . $ref['table'] . "." . $ref['field'] . "<br/>";

                $sqls[] = "ALTER TABLE `$tableName` ADD FOREIGN KEY (`$fieldName`) REFERENCES `" . $ref['table'] . "`(`" . $ref['field'] . "`)";
            }
        }
    }

    /* SQL formatting helpers */
    private function GetColumnSql( $fieldName, $fieldDesc, Database $currentDB )
    {
        $default = "";
        if( !is_null( $fieldDesc['default'] ) )
        {
            $default = $fieldDesc['default'];

            if( strcasecmp( $default, "CURRENT_TIMESTAMP" ) == 0 )
                $default = "DEFAULT $default";
            else if( strcasecmp( $default, "NULL" ) == 0 )
                $default = "DEFAULT NULL";
            else
                $default = "DEFAULT '$default'";
        }

        $comment = "";
        if( array_key_exists( "comment", $fieldDesc ) )
        {
            $comment = "COMMENT " . $currentDB->quote( $fieldDesc["comment"] );
        }

        return "`$fieldName` " . $fieldDesc['type'] . " " . ($fieldDesc['null'] == "NO" ? "NOT NULL" : "") . " " . $default . " " . $fieldDesc['extra'] . " " . $comment;
    }
}

