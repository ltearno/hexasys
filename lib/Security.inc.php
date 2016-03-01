<?php

interface IPermissionManager
{
	// Tests for a permission to be granted.
	// Returns true or false depending on the permission granting
	public function TestPermission( $id, $params=null );
	
	// return an array of array containing the following fields :
	//    * id		: the identifier of the permission, a String.
	//    * name	: a human readable name of the permission
	//    * comment	: a comment destined to be read by a human, describing te purpose of the permission
	public function GetPermissionList();
	
	// returns an array with fields name and comment
	public function GetPermission( $id );
}

interface IUserPasswordLogin
{
	public function TryLoginUser( $login, $passwordMd5 );
}

interface IUserMngt
{
	public function AnalyseLoggedUser();
	public function GetLoggedUser();
	public function GetLoggedUserId();
	public function LogOut();
}

class SecurityException extends Exception
{
}

class Security extends HexaComponentImpl
{
	/** @var IPermissionManager */
	var $mng = null;
	
	var $iUserPasswordLogin = null;

	/** @var IUserMngt */
	var $iUserMngt = null;
	
	public function SetUserMngt( IUserMngt $iUserMngt )
	{
		$this->iUserMngt = $iUserMngt;
	}
	
	public function AnalyseLoggedUser()
	{
		if( $this->iUserMngt != null )
			$this->iUserMngt->AnalyseLoggedUser();
	}
	
	public function GetLoggedUser()
	{
		if( $this->iUserMngt != null )
			return $this->iUserMngt->GetLoggedUser();
		
		return null;
	}
	
	public function GetLoggedUserId()
	{
		if( $this->iUserMngt != null )
			return $this->iUserMngt->GetLoggedUserId();
		
		return -1;
	}
	
	public function LogOut()
	{
		if( $this->iUserMngt != null )
			$this->iUserMngt->LogOut();
	}
	
	public function SetUserPasswordLogin( IUserPasswordLogin $iUserPasswordLogin )
	{
		$this->iUserPasswordLogin = $iUserPasswordLogin;
	}
	
	public function GetUserPasswordLogin()
	{
		return $this->iUserPasswordLogin;
	}
	
	
	public function SetPermissionManager( IPermissionManager $mng )
	{
		$this->mng = $mng;
	}
	
	public function RequestPermission( $id, $params=null )
	{
		$res = $this->mng->TestPermission( $id, $params );
		
		if( ! $res )
			throw new SecurityException( 'Permission ' . $id . ' not granted !' );
	}
	
	public function TestPermission( $id, $params=null )
	{
		return $this->mng->TestPermission( $id, $params );
	}
	
	public function ProcessException( SecurityException $e )
	{
		HLibServerState()->SetLevel( SERVERSTATE_LEVEL_ERROR );
		HLibServerState()->AddMessage( "Security exception : " . $e->GetMessage() );
	}
	
	public function GetPermissionList()
	{
		return $this->mng->GetPermissionList();
	}
	
	public function GetPermission( $id )
	{
		return $this->mng->GetPermission( $id );
	}
}

?>