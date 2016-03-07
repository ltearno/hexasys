<?php

class page_update extends PageMVCSecure
{
    public function Run( $params, $posts, &$view_data, &$message )
    {
        $params = array_merge( $params, $posts );

        echo "<h1>Database update</h1>";

        $currentDbDesc = $this->DB->GetDatabaseDescription();

        // new schema, read it from the php file containing the description
        $targetDbFile = "targetDatabase.inc.php";
        if( !file_exists( $targetDbFile ) )
        {
            echo "The file describing the target DB ($targetDbFile) doesn't exist, aborting.<br/>";

            return;
        }
        $targetDbDesc = eval(file_get_contents( $targetDbFile ) . ' return $dbDesc;');

        // get the sql statements to be executed
        $noDelete = isset($params['delete']) && $params['delete'] == "no";
        $sqls = HLibInstallation()->GetSqlForUpdateDb( $currentDbDesc, $targetDbDesc, $this->DB, !$noDelete );
        echo "<br/>";

        if( count( $sqls ) == 0 )
        {
            echo "Database is already up to date<br/>";
            echo getHref( $this, "refresh", null );

            return;
        }

        // execute required actions
        if( isset($params['execute']) && $params['execute'] == "yes" )
        {
            echo "Execution of the SQL statements<br/>";
            echo "<table>";
            echo TableHeader( array( "SQL statement", "Result" ) );
            foreach( $sqls as $sql )
            {
                $this->DB->Query( $sql );
                $fError = $this->DB->IsError();
                if( $fError )
                    $res = $this->DB->GetErrorText();
                else
                    $res = "Ok";

                echo TableCells( array( "<pre>$sql</pre>", $res ) );

                if( $fError )
                {
                    echo TableCells( array( "ABORTED BECAUSE OF PREVIOUS ERRORS", "" ) );
                    break;
                }
            }
            echo "</table>";

            echo "<br/>All statements have been executed, your database is up to date<br/>";
            echo getHref( $this, "refresh", null );
        }
        else
        {
            echo "The following SQL statements need to be executed, please review them.<br/>";
            echo "To execute them, click on the button " . getButton( $this, "update database", array( "execute" => "yes" ), null ) . " " . getButton( $this, "update database (no delete)", array( "execute" => "yes", "delete" => "no" ), null );
            echo "<br/>";
            foreach( $sqls as $sql )
                echo "$sql<br/>";
        }
    }
}

?>