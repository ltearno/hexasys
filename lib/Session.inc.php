<?php

define( "SESSION_ENTRY", APP_NAME . "_DATA" );

class Session extends HexaComponentImpl
{
    // returns the data curently attached to connection's session
    public function GetSessionData( $entryName )
    {
        if( !isset($_SESSION[SESSION_ENTRY]) )
            return null;

        $sessionData = $_SESSION[SESSION_ENTRY];
        if( !is_array( $sessionData ) )
            return null;

        if( !isset($sessionData[$entryName]) )
            return null;

        return $sessionData[$entryName];
    }

    // sets the data attached to the connection's session.
    // if no session is attached, a new one is created
    public function SetSessionData( $entryName, $data )
    {
        if( $data == null )
            $this->CleanSessionData( $entryName );
        else
            $_SESSION[SESSION_ENTRY][$entryName] = $data;
    }

    // clears all data related to the connection's session
    // and destroys the session
    public function CleanSessionData( $entryName )
    {
        if( !isset($_SESSION[SESSION_ENTRY]) )
            return;

        if( isset($_SESSION[SESSION_ENTRY][$entryName]) )
            unset($_SESSION[SESSION_ENTRY][$entryName]);
    }

    // clears all data related to the connection's session
    // and destroys the session
    public function CleanSession()
    {
        unset($_SESSION[SESSION_ENTRY]);
    }
}

?>