<?php

/*
	Utility class used to store variables in the file system, to provide persitent storage accross script calls.
	
	The $domainUID parameter must uniquely identify a variable consumer in the application.
	Then, the variable consumer is free to name its variables as he wants
	
	It is advised that the $domainUID to be an enum, or constants globally accessible in the application,
	For example, the application configuration file is a good place to declare all the domain UIDs of the application.
	
	All domainUID names beginning by "SYS_" are reserved for the HexaSys framework, but are stored in
	the application directory (to allow to use the same HexaSys installation for multiple applications.
*/

define( "SYS_JOBS", "SYS_JOBS" );
define( "HANG_OUTS", "HANG_OUTS" );

class StoredVariables extends HexaComponentImpl
{
    private function getVariableFileName( $domainUID, $variableName )
    {
        $variableDirectory = APP_DIR . "sys/vars/" . $domainUID . "/";
        ensureDirectoryExists( $variableDirectory );

        return $variableDirectory . $variableName;
    }

    public function Read( $domainUID, $variableName )
    {
        $fileName = $this->getVariableFileName( $domainUID, $variableName );
        $json = @file_get_contents( $fileName );
        if( $json == false )
            return null;

        return string2json( $json );
    }

    public function Store( $domainUID, $variableName, $variableValue )
    {
        $fileName = $this->getVariableFileName( $domainUID, $variableName );

        $file = fopen( $fileName, "w" );
        fwrite( $file, json2string( $variableValue ) );
        fclose( $file );
    }

    public function ReadBinary( $domainUID, $variableName )
    {
        $fileName = $this->getVariableFileName( $domainUID, $variableName );

        $content = @file_get_contents( $fileName );
        if( $content === false )
            return null;

        return unserialize( $content );
    }

    public function StoreBinary( $domainUID, $variableName, $variableValue )
    {
        $fileName = $this->getVariableFileName( $domainUID, $variableName );

        $content = serialize( $variableValue );
        @file_put_contents( $fileName, $content );
    }

    public function Remove( $domainUID, $variableName )
    {
        $fileName = $this->getVariableFileName( $domainUID, $variableName );
        unlink( $fileName );

        // TODO : if the containing directory is empty, destroy it, recursively
    }
}

?>