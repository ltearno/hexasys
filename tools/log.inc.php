<?php

date_default_timezone_set( "UTC" );

class Logger
{
    const LOG_MSG = 0;
    const LOG_WRN = 1;
    const LOG_ERR = 2;
    const LOG_CRITIC = 3;
    const LOG_FATAL = 4;

    var $logFile = null;
    var $logLevel = 0;
    var $logId = 0;

    public function Init( $fileName, $logLevel )
    {
        $logDir = APP_DATA_DIR . 'logs/' . date( 'Y-m-d', strtotime( 'now' ) );
        ensureDirectoryExists( $logDir );

        $this->logFile = fopen( $logDir . '/' . $fileName, 'a' );
        $this->logLevel = $logLevel;
        $this->logId = uniqid();
    }

    public function Term()
    {
        fclose( $this->logFile );
        $this->logFile = null;
    }

    public function LogRaw( $msg )
    {
        fwrite( $this->logFile, $msg . "\r\n" );
    }

    public function Log( $logLevel, $msg = null )
    {
        if( $msg == null )
        {
            $msg = $logLevel;
            $logLevel = Logger::LOG_MSG;
        }

        if( $logLevel < $this->logLevel )
            return;

        fwrite( $this->logFile, $logLevel . ' ' . Date( 'Y-m-d h:i:s' ) . ' ' . $this->logId . ' ' . $msg . "\r\n" );
    }
}

?>