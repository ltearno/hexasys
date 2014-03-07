<?php

class Measure extends HexaComponentImpl
{
	// construct
	var $logger = null;
	
	private function checkLogger()
	{
		if( $this->logger != null )
			return;
			
		$this->logger = new Logger();
		$this->logger->Init( 'Measures.txt', Logger::LOG_MSG );
	}
		
	public function Start()
	{
		return microtime( true );
	}
	
	public function End( $timer, $text=null )
	{
		$this->checkLogger();
		
		$startTime = $timer;
		$endTime = microtime( true );
		
		$ms = (1000*($endTime-$startTime));
		
		if( $text != null )
			$this->logger->Log( Logger::LOG_MSG, $text . ": " . $ms . "ms" );
		
		return $ms;
	}
}

?>