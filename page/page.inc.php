<?php

/*
 * A Page processes http request and outputs something in the std out
 * 
 * It has a method Execute to handle http requests.
 * 
 * The PageConnector pre-processes the http request in order to find
 * which page to load and to initialize it with the global db and qpath
 */

interface Page
{
	function Init( $qpath, $db, $container, $page );
	function Execute( $params, $posts );
}

abstract class PageImpl implements Page
{
	// QPath object
    /* @var $QPath QPath */
	var $QPath = null;

    /* @var $DB Database */
    var $DB = null;
	
	/* the url that must be used in a link to be called back on the same page */
	var $locationContainer = null;
	var $locationPage = null;
	var $locationUrl = null;
	
	// Initialisation of the page objects, called by the framework
	function Init( $qpath, $db, $container, $page )
	{
		$this->QPath = $qpath;
        $this->DB = $db;
		$this->locationContainer = $container;
		$this->locationPage = $page;
		$this->locationUrl = "?container=$container" . ( $page != null ? "&page=$page" : "" );
	}
}

include( "pageutils.inc.php" );

/*
 * Include different standard types of pages
 */

include( "page.gwtio.inc.php" );
include( "page.mvc.inc.php" );
include( "page.mvcsecure.inc.php" );
include( "page.proxysecure.inc.php" );

?>