<?php

abstract class PageMVC extends PageImpl
{
    abstract function Run( $params, $posts, &$view_data, &$message );

    // Page properties
    var $Title = null;

    // Template for the view
    var $header = '
		<!doctype html>
		<html>
			<head>
				<title>###TITLE###</title>
		
				<!--<link rel="stylesheet" href="###HEXA_DIR###css/reset.css" type="text/css" media="screen" />
				<link rel="stylesheet" href="###HEXA_DIR###css/text.css" type="text/css" media="screen" />
				<link rel="stylesheet" href="###HEXA_DIR###css/core.css" type="text/css" media="screen" />-->
				
					
				<link rel="stylesheet" href="###HEXA_DIR###css/bs/css/bootstrap.min.css" type="text/css" media="screen" />
			<link rel="stylesheet" href="###HEXA_DIR###css/bs/css/bootstrap-theme.min.css" type="text/css" media="screen" />
				<link rel="stylesheet" href="###HEXA_DIR###css/bs/core.css" type="text/css" media="screen" />
		
				<script src="###HEXA_DIR###css/bs/js/bootstrap.min.js"></script>
			</head>
			<body>
		';

    var $footer = '</body></html>';

    function generateHeaderPart()
    {
        // header display
        $this->ViewHeader();
    }

    function generateFooterPart()
    {
        $this->ViewFooter();
    }

    function Execute( $params, $posts )
    {
        $this->generateHeaderPart();

        // call the abstract method
        $msg = "";
        $messages = array();
        $this->Run( $params, $posts, $view_data, $msg );
        if( $msg != "" )
            $messages[] = $msg;

        // add the messages if any
        if( count( $messages ) > 0 )
        {
            echo "<div class='messages'>";
            foreach( $messages as $message )
                echo "<div class='message'>" . $message . "</div>";
            echo "</div>";
        }

        // footer display
        $this->generateFooterPart();
    }

    // Outputs the header of the web page to send to web-clients, called by the framework
    function ViewHeader()
    {
        // Replace the title of the page in the template
        if( $this->Title == null )
        {
            $str = "No title";
            $parts = explode( '.', $this->locationContainer );
            if( count( $parts ) > 0 )
                $str = array_pop( $parts );
            if( count( $parts ) > 0 )
                $str .= " (" . implode( '.', $parts ) . ")";
            if( defined( 'SYNCHRO_SITE_NAME' ) )
                $this->Title = SYNCHRO_SITE_NAME . " - " . $str;
            else
                $this->Title = "*** Undefined Site Name *** - " . $str;
        }
        $out = $this->header;
        $out = str_replace( '###TITLE###', $this->Title, $out );

        $out = str_replace( '###HEXA_DIR###', HEXA_SYS_URL, $out );

        echo $out;
        echo "<div class='content'>";
    }

    // Outputs the footer of the web page to send to web-clients, called by the framework
    function ViewFooter()
    {
        echo "</div>";
        echo $this->footer;
    }
}

?>