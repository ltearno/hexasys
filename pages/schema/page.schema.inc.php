<?php





class page_schema extends PageMVCSecure
{
	public function Run( $params, $posts, &$view_data, &$message )
	{
		// remove cached database information file
		@unlink( "currentDatabase.inc.php" );
		
		$dbDesc = $this->DB->GetDatabaseDescription();
		
		$phpFile = fopen( "currentDatabase.inc.php", 'w' );
		fwrite( $phpFile, '$dbDesc = ' . DumpCode( $dbDesc ) . ';' );
		fclose( $phpFile );
		echo "DB state written to 'currentDatabase.inc.php'<br/>";
		
		echo "<br/>DB state :<br/>";
		echo "<pre>".DumpCode($dbDesc)."</pre>";
	}
}

?>