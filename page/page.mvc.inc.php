<?php

abstract class PageMVC extends PageImpl
{
    abstract function Run($params, $posts, &$view_data, &$message);

    // Page properties
    var $Title = null;

    // Template for the view
    var $header = '
		<!doctype html>
		<html>
			<head>
			    <meta charset="utf-8">
			    <meta name="viewport" content="width=device-width, initial-scale=1">
			    
				<title>###TITLE###</title>
				
				<link href="###HEXA_DIR###bootstrap/css/bootstrap.min.css" rel="stylesheet">
				<script src="###HEXA_DIR###js/jquery-3.1.1.min.js"></script>
				<script src="###HEXA_DIR###bootstrap/js/bootstrap.min.js"></script>
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

    function Execute($params, $posts)
    {
        $this->generateHeaderPart();

        // call the abstract method
        $msg = "";
        $messages = array();
        $this->Run($params, $posts, $view_data, $msg);
        if ($msg != "")
            $messages[] = $msg;

        // add the messages if any
        if (count($messages) > 0) {
            echo "<div class='messages'>";
            foreach ($messages as $message)
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
        if ($this->Title == null) {
            $str = "No title";
            $parts = explode('.', $this->locationContainer);
            if (count($parts) > 0)
                $str = array_pop($parts);
            if (count($parts) > 0)
                $str .= " (" . implode('.', $parts) . ")";
            if (defined('SYNCHRO_SITE_NAME'))
                $this->Title = SYNCHRO_SITE_NAME . " - " . $str;
            else
                $this->Title = "*** Undefined Site Name *** - " . $str;
        }
        $out = $this->header;
        $out = str_replace('###TITLE###', $this->Title, $out);

        if (defined("HEXA_SYS_URL"))
            $out = str_replace('###HEXA_DIR###', HEXA_SYS_URL, $out);
        else
            $out = str_replace('###HEXA_DIR###', "", $out);

        echo $out;
        echo "<div class='container'>";
    }

    // Outputs the footer of the web page to send to web-clients, called by the framework
    function ViewFooter()
    {
        echo "</div>";
        echo $this->footer;
    }
}

?>