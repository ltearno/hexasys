<?php

/*
 * When included, this file examines the $_POST and $_GET global variables
 * and then finds a Page interface implementation to load based on the request's
 * parameters action, container, class, page
 * 
 * When it has found a Page implementation to create, it will initialize
 * it with the global DB and QPath
 * 
 * To find the page implementation file, the 'container' query parameter will be inspected.
 * hexasys's own pages can be accessed through the "sys." prefix,
 * otherwise, the application defined page implementations will be searched
 * in the APP_DIR . "/pages/" directory
 */

// Search name of page to be displayed
$container = false;
if( isset($_GET['container']) )
    $container = $_GET['container'];
else if( isset($_POST['container']) )
    $container = $_POST['container'];
else if( isset($defaultPage) )
    $container = $defaultPage;
else
    $container = "home";

// store for putting that in the page
$location = $container;

// the character '.' is used as a directory separator, to allow to organize containers in sub folders
$parts = explode( ".", $container );

$pageDirectory = APP_DIR . "pages/";
if( $parts[0] == "sys" )
{
    $pageDirectory = HEXA_DIR . "pages/";
    array_shift( $parts );
}

// the container name is the last part of the string
$container = array_pop( $parts );
$containerDirectory = implode( "/", $parts );
if( $containerDirectory != "" )
    $containerDirectory .= "/";

// include page file
$pageFile = $pageDirectory . $containerDirectory . 'page.' . $container . '.inc.php';
$page = $container;
if( isset($_GET['class']) )
    $page = $_GET['class'];
else if( isset($_POST['class']) )
    $page = $_POST['class'];

if( !file_exists( $pageFile ) )
{
    $pageFile = "pages/page.notFound.inc.php";
    $page = "notFound";
}

include_once($pageFile);

// load the page code
$classToCall = 'page_' . $page;
/** @var Page $pageObject */
$pageObject = new $classToCall();
$pageObject->Init( $GLOBAL_QPATH, $GLOBAL_DATABASE, $location, $page == $container ? null : $page );

// gather page parameters
$params = array();
$incomingParams = $_GET;
foreach( $incomingParams as $param_name => $param_value )
{
    switch( $param_name )
    {
        case 'container' :
        case 'class' :
        case 'page' :
            break;
        default :
            $params[$param_name] = $param_value;
    }
}

$pageObject->Execute( $params, $_POST );

?>