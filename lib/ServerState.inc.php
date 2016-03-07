<?php

define( "SERVERSTATE_LEVEL_DEBUG", 0 );
define( "SERVERSTATE_LEVEL_ERROR", 1 );
define( "SERVERSTATE_LEVEL_MESSAGE", 2 );

class ServerState extends HexaComponentImpl
{
    var $msgLevel = 0;
    var $msg = array();

    public function Reset()
    {
        $this->msgLevel = 0;
        $this->msg = array();
    }

    public function GetMessage()
    {
        return implode( '<br/>', $this->msg );
    }

    public function GetLevel()
    {
        return $this->msgLevel;
    }

    public function AddMessage( $msg )
    {
        $this->msg[] = $msg;
    }

    public function SetLevel( $msgLevel )
    {
        if( $msgLevel <= $this->msgLevel )
            return;

        $this->msgLevel = $msgLevel;
    }
}

?>