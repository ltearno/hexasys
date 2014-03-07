<?php

/**
 * Class to handle the task of maintaining a $db and $qpath
 */
class HexaComponentImpl implements HexaComponent
{
	/* @var $QPath QPath */
    var		$QPath = null;
	var		$db = null;
	
	/**
	 * Initialisation method, used to store $db and $qpath instances
	 * @param mixed $db The Database instance
	 * @param mixed $qpath The QPath instance
	 */
	public function Init( $db, $qpath )
	{
		if( isset( $this->db ) )
			return 0;
		
		$this->db = $db;
		$this->QPath = $qpath;
		
		return 0;
	}
}

?>