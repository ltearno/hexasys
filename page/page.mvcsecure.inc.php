<?php

abstract class PageMVCSecure extends PageMVC
{
	var $ctx;

	function Execute( $params, $posts )
	{
		// Initialize the PageState that will enable us to manage our url, create buttons and so on
		$this->ctx = new PageState();
		$this->ctx->initState( $this, array() );
		$this->ctx->loadStateFromPageParams( $params, $posts );

		$message = "";

		// user wants to log out
		if( $this->ctx->getItem( "logout" ) )
		{
			HLib("Security")->LogOut();
				
			$this->ctx->updateState( array( "logout" => null ) );
				
			$message .= "Bye bye !<br/>";
				
			$this->ctx->updateState( array( "login"=>null, "pass"=>null, "pass_md5"=>null ) );
		}

		$loggedUser = HLib("Security")->GetLoggedUser();
		if( $loggedUser == null )
		{
			// we should find a way to log the user in
				
			// get an app provided logging facility, if any
			$iUserPasswordLogin = HLib("Security")->GetUserPasswordLogin();
			if( $iUserPasswordLogin != null )
			{
				// test if a login information has been provided
				$login = $this->ctx->getItem( "login" );
				$password = $this->ctx->getItem( "pass" );
				$passwordMd5 = $password != null ? md5( $password ) : $this->ctx->getItem( "pass_md5" );

				if( $login!=null && $passwordMd5!=null )
				{
					$iUserPasswordLogin->TryLoginUser( $login, $passwordMd5 );
						
					$loggedUser = HLib("Security")->GetLoggedUser();
					if( $loggedUser == null )
					{
						$message .= "Wrong login information provided, please try to log in again";
					}
					else
					{
						$message .= "Welcome on " . $loggedUser["users.first"] . " " . $loggedUser["users.last"];
					}
				}

				if( $loggedUser == null )
				{
					// otherwise, provide a login form to enter credentials
						
					$this->generateHeaderPart();

					echo "<div style='margin:10px;border:1px solid grey;border-radius:5px;padding:10px;'>";
					echo "<form method='post' action='".$this->ctx->getUrl($this->ctx->getState())."'>";
					echo "<table>";
					echo "<tr><td colspan=2>Please provide your login and password</td></tr>";
					echo "<tr><td>login</td><td><input type='text' name='login'/></td></tr>";
					echo "<tr><td>password</td><td><input type='password' name='pass'/></td></tr>";
					echo "<tr><td></td><td><input type='submit' value='log in'/></td></tr>";
					echo "</table>";
					echo "</form>";
					echo "<span style='color:red;font-weight:bold;'>$message</span>";
					echo "</div>";

					$this->generateFooterPart();

					return;
				}
			}
		}
		else
		{
			$message .= "Logged as " . $loggedUser["users.first"] . " " . $loggedUser["users.last"];
		}

		// refresh the logged user data
		$loggedUser = HLib("Security")->GetLoggedUser();

		// should not happen !
		if( $loggedUser == null )
		{
			$this->generateHeaderPart();
				
			echo "<div style='margin:10px;border:1px solid grey;border-radius:5px;padding:10px;'>";
			echo "general error : no way to log you in, sorry !";
			echo "</div>";
				
			$this->generateFooterPart();
				
			return;
		}

		// if the user has not the right permission, leave
		if( ! HLib("Security")->TestPermission( 'AdminPages' ) )
		{
			$this->generateHeaderPart();
				
			echo "You don't have right to use these pages. If you think you should, contact your admin at <a href='mailto:" . ADMINISTRATOR_EMAIL . "'>".ADMINISTRATOR_EMAIL."</a><br/>";
			echo $this->ctx->getHref("logout", array("logout"=>true)) . "<br/>";
				
			$this->generateFooterPart();

			return;
		}

		echo "<div style='margin:0 10px;margin-bottom:5px;border:1px solid grey;border-bottom-left-radius:5px;border-bottom-right-radius:5px;padding:3px;'>";
		echo $message . " - you are on ". SYNCHRO_SITE_NAME ." - " . $this->ctx->getHref("logout", array("logout"=>true)) . " - " . $this->ctx->getHref("refresh",$this->ctx->getState()) . "<br/>";
		echo "</div>";

		try
		{
			parent::Execute( $params, $posts );
		}
		catch( SecurityException $e )
		{
			echo "You don't have the right permission do take this action, please contact your system adminstrator if you feel that you should be granted access to this functionality <br/>";
		}
	}
}

?>