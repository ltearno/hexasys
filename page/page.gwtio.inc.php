<?php

abstract class PageGWTIO extends PageImpl
{
	abstract function GetPermissionManager();
	abstract function GetServiceInstance( $serviceName );
	abstract function LogMethod( $methodName );
	
	var $serviceInstances = null;
	
	function getServiceInfo( $service, $interfaceChecksum )
	{
		if( $this->serviceInstances == null )
			$this->serviceInstances = array();
		
		if( ! isset( $this->serviceInstances[$service] ) )
		{
			$svcInfo = $this->GetServiceInstance( $service );
			
			$this->serviceInstances[$service] = $svcInfo;
		}
		
		if( $interfaceChecksum != $this->serviceInstances[$service]["checksum"] )
		{
			echo "ERROR : Interface version is different between client and server for service $service, please contact the System administrator.<br/>";
			return null;
		}
		
		return $this->serviceInstances[$service];
	}
	
	function Execute( $params, $posts )
	{
		// especially for Internet Explorer browser, we say no cache. This one (unlike the others) stores XHR requests !
		header( "Pragma: no-cache" );
		header( "Cache-Control: no-cache" );
		
		if( isset( $params['locale'] ) )
			HLibLocaleInfo()->SetLocale( $params['locale'] );

		$logger = new Logger();
		$logger->Init( 'gwt-interop-direct.txt', Logger::LOG_MSG );

		//$logger->Log( Logger::LOG_MSG, 'QUERY URL: ' . $_SERVER['QUERY_STRING'] );

		$result = array();
		
		$payload = isset( $posts['payload'] ) ? $posts['payload'] : ( isset( $params['payload'] ) ? $params['payload'] : null );
		if( $payload == null )
		{
			echo "Calls spec not defined, ciao !<br/>";
			Dump( $params );
			Dump( $posts );
			return;
		}

		$payload = string2Json( $payload );
		if( $payload == null )
		{
			echo "Calls spec not in legal JSON format, ciao !<br/>";
			echo "MagicQuotes : " . get_magic_quotes_gpc() . " (the value should be 0 or Off in the php.ini file, otherwise you get problems such as this one !)<br/>";
			echo $posts['payload'];
			return;
		}
		
		$servicesUsed = $payload[0];
		$calls = $payload[1];
		
		// services used in this call session
		$services = array();
		foreach( $servicesUsed as $service )
		{
			$svc = $this->getServiceInfo( $service[0], $service[1] );
			if( $svc == null )
			{
				echo "CANNOT FIND SERVICE $service<br/>";
				return;
			}
			
			$services[] = $svc;
		}
		
		// Process each call
		foreach( $calls as $call )
		{
			HLibServerState()->Reset();
			
			$method = $call[0];
			$parameters = array_values( $call[1] );
			$serviceIdx = $call[2];
			
			$svc = $services[$serviceIdx];
			$serviceInstance = $svc["instance"];
			$serviceMethods = $svc['methods'];

			if( is_numeric( $method ) )
				$method = $serviceMethods[$method];
			
			$hangOutCode = null;
			$res = null;
			HLibHangout()->SetValue( null );
			
			$transactionId = $this->QPath->StartTransaction();
			
			try
			{
				$doCall = true;
				if( $method == "_hang_out_reply_" )
					$doCall = HLibHangout()->ProcessReply( $method, $parameters );
				
				if( $doCall )
				{
					$loggedUserId = HLibSecurity()->GetLoggedUserId();
					
					$m = HLibMeasure()->Start();
					$res = call_user_func_array( array($serviceInstance,$method), $parameters );
					$ms = HLibMeasure()->End( $m );
					
					$logMethod = $this->logMethod( $method );
					
					if( $logMethod )
						$logger->Log( Logger::LOG_MSG, "(".round($ms,2)." ms) user:$loggedUserId " . $method . '( ' . array2string( $parameters ) . ' )' );
					
					if( $ms > 2000 )
					{
						$log = new Logger();
						$log->Init( 'gwtio-long_requests.txt', Logger::LOG_MSG );
						$log->Log( Logger::LOG_MSG, "Time for a request $ms ms. for request : $method( " . array2string( $parameters ) . " )" );
						$log->Term();
					}
				}
			}
			catch( SecurityException $e )
			{
				$logger->Log( Logger::LOG_ERR, "RAISES SECURITY EXCEPTION" );
				HLibSecurity()->ProcessException( $e );
				
				$res = null;
				
				$this->QPath->AbortTransaction();
			}
			catch( HangOutException $e )
			{
				$logger->Log( Logger::LOG_MSG, "RAISES HANGOUT EXCEPTION" );
				$hangOutCode = HLibHangout()->ProcessException( $e, $method, $parameters );
				
				$this->QPath->AbortTransaction();
			}
			catch( Exception $e )
			{
				$this->QPath->AbortTransaction();
				$this->QPath->CloseTransaction( $transactionId );
				
				// relaunch the exception
				echo "Exception catched and rethown : " . $e->getMessage() . "<br/>";
				throw $e;
			}
			
			$this->QPath->CloseTransaction( $transactionId );
			
			$result[] = array( HLibServerState()->GetLevel(), HLibServerState()->GetMessage(), $hangOutCode, $res );
		}
		
		$json = json_encode( $result );
		echo $json;
	}
}

?>