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
	
	var $nbNotFlushed = 0;
	
	public function Init( $fileName, $logLevel )
	{
		$logDir = APP_DIR . 'logs/' . date( 'Y-m-d', strtotime( 'now' ) );
		ensureDirectoryExists( $logDir );
			
		$this->logFile = fopen( $logDir . '/' . $fileName, 'a' );
		$this->logLevel = $logLevel;
	}
	
	public function Term()
	{
		fclose( $this->logFile );
		$this->logFile = null;
	}
	
	public function Log( $logLevel, $msg = null )
	{
		if( $msg == null )
		{
			$msg = $logLevel;
			$logLevel  = Logger::LOG_MSG;
		}
		
		if( $logLevel < $this->logLevel )
			return;
		
		// allows to use variable arguments
		//$args = func_get_args();
		//array_shift( $args ); // skip the $logLevel arg
		//$msg = call_user_func_array( 'sprintf', $args );
		
		fwrite( $this->logFile, $logLevel . ' ' . Date( 'Y-m-d h:i:s' ) . ' ' . $msg . "\r\n" );
		
		/*$this->nbNotFlushed++;
		if( $this->nbNotFlushed > 20 )
		{
			fflush( $this->logFile );
			$this->nbNotFlushed = 0;
		}*/
	}
}

?>