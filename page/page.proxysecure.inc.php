<?php

abstract class PageProxySecure extends PageImpl
{
	abstract function ProcessQuery( $params );

	public function Execute( $params, $posts )
	{
		HLib("Security")->AnalyseLoggedUser();

		$loggedUser = HLib("Security")->GetLoggedUser();
		if( $loggedUser == null )
		{
			echo "Not logged in<br/>";
			return;
		}

		$params = array_merge( $params, $posts );

		$this->ProcessQuery( $params );
	}
}

?>