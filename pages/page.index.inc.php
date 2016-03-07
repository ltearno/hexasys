<?php

class page_index extends PageMVCSecure
{
    private function getPrefixes( $urlPrefix )
    {
        $res = array();

        $prefixes = explode( ".", $urlPrefix );
        foreach( $prefixes as $p )
            if( $p != "" )
                $res[] = $p;

        return $res;
    }

    function isSys( $urlPrefixes )
    {
        if( count( $urlPrefixes ) > 0 && $urlPrefixes[0] == "sys" )
            return true;

        return false;
    }

    function printBreadcrumb( $urlPrefixes )
    {
        echo "<div style='border:1px solid white;'>";

        if( $this->isSys( $urlPrefixes ) )
        {
            // print a link to app root
            echo $this->ctx->getHref( "app root", array( "url_prefix" => null ) );

            echo " / ";

            // and the breadcrumb in sys
            $this->printBreadCrumbImpl( $urlPrefixes );
        }
        else
        {
            // print the breadcrumb in app
            $this->printBreadCrumbImpl( $urlPrefixes );

            echo " / ";

            // and a link to sys root
            echo $this->ctx->getHref( "sys root", array( "url_prefix" => "sys." ) );
        }

        echo "</div>";

        echo "<br/>";
    }

    function printBreadCrumbImpl( $urlPrefixes )
    {
        $fApp = !$this->isSys( $urlPrefixes );

        // breadcrumb
        $breadcrumb = "";
        $breadPath = "";
        if( !$fApp )
            $breadPath = "sys.";

        $breadcrumb .= $this->ctx->getHref( $fApp ? "app root" : "sys root", array( "url_prefix" => $fApp ? null : "sys." ) );

        if( !$fApp )
            array_shift( $urlPrefixes );

        foreach( $urlPrefixes as $prefix )
        {
            $breadcrumb .= " > ";

            $breadPath .= $prefix . ".";
            $breadcrumb .= $this->ctx->getHref( $prefix, array( "url_prefix" => $breadPath ) );
        }

        echo $breadcrumb;
    }

    public function Run( $params, $posts, &$view_data, &$message )
    {
        //echo ROOT_DIRECTORY . "<br/><br/>";

        $urlPrefix = $this->ctx->getItem( "url_prefix" );
        if( $urlPrefix != null )
        {
            $urlPrefixes = $this->getPrefixes( $urlPrefix );
        }
        else
        {
            $urlPrefixes = array();
        }

        // bread crumb
        $this->printBreadcrumb( $urlPrefixes );

        // links in this directory
        $isSys = $this->isSys( $urlPrefixes );
        if( $isSys )
            $dir = HEXA_DIR . "/pages";
        else
            $dir = APP_DIR . "/pages";

        if( $isSys )
        {
            $dirPrefixes = $urlPrefixes;
            array_shift( $dirPrefixes );
            $dir .= "/" . implode( "/", $dirPrefixes );
        }
        else
        {
            $dir .= "/" . implode( "/", $urlPrefixes );
        }

        //$urlPrefixes = array_pop( $urlPrefixes );

        $this->printDir( $dir, $urlPrefix );
    }

    function printDir( $dir, $urlPrefix )
    {
        echo "<div style='padding: 3px; float:left; border:1px solid white;'><b>Sub-directories</b><br/>";

        $res = scandir( $dir );

        sort( $res );

        echo "<table>";
        foreach( $res as $dirName )
        {
            $file = $dir . "/" . $dirName;
            if( is_dir( $file ) && $dirName != "." && $dirName != ".." )
            {
                echo "<tr><td>";
                echo $this->ctx->getHref( $dirName, array( "url_prefix" => $urlPrefix . $dirName . "." ) ) . "<br/>";
                echo "</td></tr>";
            }
        }
        echo "</table>";

        echo "</div>";
        echo "<div style='padding: 3px; margin-left:10px; float:left;border:1px solid white;'><b>Pages</b><br/>";

        $files = array();
        foreach( $res as $fileName )
            if( preg_match( "/^page\.?([^.]+)\.inc\.php/i", $fileName, $matches ) > 0 )
                $files[] = $matches[1];
        $nbFiles = count( $files );

        if( $nbFiles == 0 )
        {
            echo "No pages...";
        }
        else
        {
            $nbByColumn = 15;
            $left = $nbFiles % $nbByColumn;
            $nbColumns = ($nbFiles - $left) / $nbByColumn;
            if( $left > 0 )
                $nbColumns++;

            echo "<table>";
            for( $line = 0; $line < $nbByColumn; $line++ )
            {
                echo "<tr>";
                for( $col = 0; $col < $nbColumns; $col++ )
                {
                    $idx = $line + $col * $nbByColumn;

                    if( $idx < $nbFiles )
                    {
                        echo "<td> $idx - ";

                        $pageName = $files[$idx];
                        echo "<a href='?container=" . $urlPrefix . $pageName . "'>" . $pageName . "</a>";

                        echo "</td>";
                    }
                }
                echo "</tr>";
            }
            echo "</table>";
        }

        echo "</div>";
    }
}

?>