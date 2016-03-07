<?php

/*
 * Hexasys Framework main file
 * 
 * Include this file to enable the Hexasys framework to your application.
 * 
 * You must have those variables defined before including this file
 * if you want the corresponding features to be enabled :
 * 
 * define APP_DIR : the path to the directory containing an application folder that hexasys has access to (used to store your logs and application state's files)
 * variable $database_options : an array for DB and QPath init
 * variable $qpath_tables : an array for QPath specifying specific plural forms between table and foregin key names
 * variable $qpath_ignored_fields : an array containing a list of fields that are commonly not retreived by normal QPath queries
 */

define( "HEXA_DIR", dirName( __FILE__ ) . '/' );

ob_start( "ob_gzhandler" );
header( 'Content-type: text/html; charset=UTF-8' );

session_start();

/*
 * Defintion of the BCMath default precision. 
 */

if( function_exists( 'bcscale' ) )
    bcscale( 2 );

/*
 * An HexaComponent can be initialized to receive the database and qpath global instances
 */

interface HexaComponent
{
    function Init( $db, $qpath );
}

/*
 * Framework includes
 */

include_once('tools/utils.inc.php');
include_once('tools/log.inc.php');
include_once('tools/databasepdo.inc.php');
include_once('tools/qpath.inc.php');
include_once('tools/helpers.inc.php');
include_once('tools/calendar.inc.php');
include_once('tools/HexaComponentImpl.inc.php');

/*
 * Database initialisation
*/

$GLOBAL_DATABASE = null;
if( isset($database_options) && count( $database_options ) > 0 )
{
    $GLOBAL_DATABASE = new Database();
    $GLOBAL_DATABASE->Init( $database_options );
    if( $GLOBAL_DATABASE->IsError() )
    {
        $GLOBAL_DATABASE->Explain();
    }
}

/*
 * QPath object initialisation
*/

$GLOBAL_QPATH = null;
if( $GLOBAL_DATABASE != null )
{
    $GLOBAL_QPATH = new QPath();
    $GLOBAL_QPATH->Init( $GLOBAL_DATABASE );
}

/*
 * Manages access to HexaComponent instances, loading the corresponding file if needed
 * 
 * $libraryName can have '/' to search in sub diretories
 * $pool should be an array and will be used by the function to maintain its state
 * $libraryPath is the path where to search the component's included file
 */

function HComponentFromPool( $libraryName, &$pool, $libraryPath )
{
    global $GLOBAL_DATABASE;
    global $GLOBAL_QPATH;

    if( $libraryName == null )
        return null;

    if( !isset($pool[$libraryName]) )
    {
        // include the file
        include_once($libraryPath . $libraryName . ".inc.php");

        $separatorPos = strrpos( $libraryName, "/" );
        if( $separatorPos === false )
            $className = $libraryName;
        else
            $className = substr( $libraryName, $separatorPos + 1 );

        $pool[$libraryName] = new $className();

        $pool[$libraryName]->Init( $GLOBAL_DATABASE, $GLOBAL_QPATH );
    }

    return $pool[$libraryName];
}

/*
 * Hexasys components pool management
 */

$HEXA_COMPONENTS = array();

function HLib( $libraryName )
{
    global $HEXA_COMPONENTS;

    return HComponentFromPool( $libraryName, $HEXA_COMPONENTS, "lib/" );
}

function HLibInclude( $libraryName )
{
    // include the file
    include_once("lib/" . $libraryName . '.inc.php');
}

// PHPDoc typed wrappers to HLib() calls
/** @return Security */
function HLibSecurity()
{
    return HLib( "Security" );
}

/** @return Measure */
function HLibMeasure()
{
    return HLib( "Measure" );
}

/** @return ServerState */
function HLibServerState()
{
    return HLib( "ServerState" );
}

/** @return StoredVariables */
function HLibStoredVariables()
{
    return HLib( "StoredVariables" );
}

/** @return HangOut */
function HLibHangout()
{
    return HLib( "HangOut" );
}

/** @return LocaleInfo */
function HLibLocaleInfo()
{
    return HLib( "LocaleInfo" );
}

/** @return BkgndJobs */
function HLibBkgndJobs()
{
    return HLib( "BkgndJobs" );
}

/** @return Installation */
function HLibInstallation()
{
    return HLib( "Installation" );
}

/** @return Session */
function HLibSession()
{
    return HLib( "Session" );
}

/*
 * Page parent object from which inherits every page used in this framework
 */

include_once('page/page.inc.php');

?>