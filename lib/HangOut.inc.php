<?php

class HangOutException extends Exception
{
	var $name;
	var $type;
	var $title;
	var $description;
	
	public function __construct( $name, $type, $title, $description )
	{
		$this->name = $name;
		$this->type = $type;
		$this->title = $title;
		$this->description = $description;
	}
}

class HangOut extends HexaComponentImpl
{
	var $replyData = null;
	
	public function SetValue( $replyData )
	{
		$this->replyData = $replyData;
	}
	
	public function GetValue( $name, $type, $title, $description )
	{
		if( ! is_null( $this->replyData ) )
		{
			if( isset( $this->replyData[$name] ) )
				return $this->replyData[$name];
			echo "Reply data not null but not containing $name<br/>";
			Dump( $this->replyData );
		}
		
		// if value not found in current dictionary, throw the exception
		throw new HangOutException( $name, $type, $title, $description );
	}
	
	public function ProcessException( HangOutException $e, $method, $parameters )
	{
		// store service, method and parameters for future calls
		$hangouts = HLibStoredVariables()->Read( HANG_OUTS, "hang_outs" );
		if( is_null( $hangouts ) )
			$hangouts = array();
		$hangoutId = count( $hangouts );
		$hangouts["HO_".$hangoutId] = array( "method"=>$method, "parameters"=>$parameters );
		HLibStoredVariables()->Store( HANG_OUTS, "hang_outs", $hangouts );
		
		// return the data that will be passed back to the client as the HangOut data
		$res = array( $hangoutId, array($e->name,$e->type,$e->title,$e->description), $this->replyData );
		
		return $res;
	}
	
	public function ProcessReply( &$method, &$parameters )
	{
		$hangouts = HLibStoredVariables()->Read( HANG_OUTS, "hang_outs" );
		
		$hang_out = isset( $hangouts["HO_".$parameters[0]] ) ? $hangouts["HO_".$parameters[0]] : null;
		unset( $hangouts["HO_".$parameters[0]] );
		HLibStoredVariables()->Store( HANG_OUTS, "hang_outs", $hangouts );
		
		if( $hang_out == null )
		{
			HLibServerState()->AddMessage( "Hang out call reply cannot be processed because of hang_out ".$parameters[0]." record not found" );
			
			return false;
		}
		else
		{
			HLibHangout()->SetValue( $parameters[1] );
			
			$method = $hang_out['method'];
			$parameters = ( $hang_out['parameters'] );
			
			return true;
		}
	}
}

?>