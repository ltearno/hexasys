<?php

/*
	Page not found page, so to present an error to the user
*/

class page_notFound extends PageMVCSecure
{
    function Run( $params, $posts, &$view_data, &$messageNotUsed )
    {
        echo "<br/>You don't know where you want to go ?<br/><br/>";

        echo "<b>Requested page does not exist !</br>";
    }
}

?>