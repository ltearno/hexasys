<?php

class PageState
{
    var $page;
    var $ctx;
    var $posts;

    public function initState( Page $page, $ctx )
    {
        $this->page = $page;
        $this->ctx = $ctx;
    }

    public function loadStateFromPageParams( $params, $posts = array() )
    {
        foreach( $params as $name => $value )
            $this->ctx[$name] = $value;

        $this->posts = $posts;
    }

    public function getState()
    {
        return $this->ctx;
    }

    public function updateState( $params )
    {
        $this->ctx = $this->mergeParams( $this->ctx, $params );
    }

    public function getItem( $name )
    {
        if( !isset($this->ctx[$name]) )
        {
            if( !isset($this->posts[$name]) )
                return null;
            else
                return $this->posts[$name];
        }

        return $this->ctx[$name];
    }

    public function getHref( $title, $params )
    {
        $html = "<a href='" . $this->getUrl( $params );

        $html .= "'>$title</a>";

        return $html;
    }

    public function getButton( $title, $params )
    {
        return getButton( $this->page, $title, $params, $this->ctx );
    }

    public function getUrl( $params )
    {
        $url = $this->page->locationUrl;

        if( $params != null )
        {
            $state = $this->mergeParams( $this->ctx, $params );

            foreach( $state as $name => $value )
                $url .= "&$name=" . urlencode( $value );
        }

        return $url;
    }

    public function getFormBuilder()
    {
        $builder = new FormBuilder();
        $builder->Init( $this );

        return $builder;
    }

    private function mergeParams( $currentState, $newParams )
    {
        // construct the state to be serialized in the href,
        // which is the current state overriden by the params given by the caller
        foreach( $newParams as $name => $value )
        {
            if( $value == null )
                unset($currentState[$name]);
            else
                $currentState[$name] = $value;
        }

        return $currentState;
    }
}

class FormBuilder
{
    var $ctx;
    var $fields = array();
    var $method;

    public function Init( $ctx )
    {
        $this->ctx = $ctx;
        $this->method = "get";
    }

    public function AddTextField( $description, $name )
    {
        $this->fields[] = array( "type" => "text", "description" => $description, "name" => $name );
    }

    public function GetHTML()
    {
        $url = "";
        if( $this->method == "put" )
            $url = $this->ctx->page->locationUrl;

        $urlParams = "";
        if( $this->method == "put" )
        {
            foreach( $this->ctx->ctx as $name => $value )
                $urlParams .= "&$name=" . urlencode( $value );
        }

        $res = "";
        $res .= "<form action='$url$urlParams' method='$this->method'>";
        if( $this->method == "get" )
        {
            $res .= "<input type='hidden' name='container' value='" . $this->ctx->page->locationContainer . "'/>";
            if( $this->ctx->page->locationPage != null )
                $res .= "<input type='hidden' name='page' value='" . $this->ctx->page->locationPage . "'/>";
            foreach( $this->ctx->ctx as $name => $value )
                $res .= "<input type='hidden' name='$name' value='$value'/>";
        }
        foreach( $this->fields as $field )
        {
            switch( $field['type'] )
            {
                case "text" :
                    $res .= $field['description'] . " : <input type='text' name='" . $field['name'] . "'></input><br/>";
                    break;
            }
        }
        $res .= "<input type='submit' value='submit'/>";
        $res .= "</form>";

        return $res;
    }
}

class QPathPager
{
    var $QPath;
    var $ctx;

    var $query = null;
    var $queryCount = null;

    var $count = null;

    var $size = null;
    var $from = null;

    var $results = null;

    public function __construct( QPath $qpath, PageState $ctx, $query, $queryCount = null )
    {
        $this->QPath = $qpath;
        $this->ctx = $ctx;

        $this->query = $query;

        if( is_null( $queryCount ) )
            $this->queryCount = "F[count(*) AS `count`] ?" . $query;
        else
            $this->queryCount = $queryCount;

        $this->from = $ctx->getItem( "from" );
        if( is_null( $this->from ) )
            $this->from = 0;

        $this->size = $ctx->getItem( "size" );
        if( is_null( $this->size ) )
            $this->size = 5;
    }

    public function GetCount()
    {
        if( is_null( $this->count ) )
        {
            $count = $this->QPath->QueryOne( $this->queryCount );
            if( is_null( $count ) )
                return -1;

            $this->count = $count['count'];
        }

        return $this->count;
    }

    public function GetResults()
    {
        if( is_null( $this->results ) )
            $this->results = $this->QPath->QueryExLimit( $this->query, $this->from . ", " . $this->size );

        return $this->results;
    }

    public function DisplayPager()
    {
        $count = $this->GetCount();

        echo "<div style='position:relative'>";

        echo "<div style='float: right;'>" . $this->size . " of $count records, starting at " . $this->from . "</div>";

        $showPrev = false;
        if( $this->from > 0 )
            $showPrev = true;
        $showNext = false;
        if( $this->from + $this->size < $count )
            $showNext = true;

        $plus = min( max( 0, $count - 1 ), $this->size + 5 );
        $minus = max( 0, $this->size - 5 );

        $showMore = $plus <= $count;
        $showLess = $minus > 0;

        if( $showPrev || $showNext || $showMore || $showLess )
        {
            echo "<div style='float: left;'>";

            if( $showPrev )
                echo $this->ctx->getHref( "prev", array( "from" => (max( 0, $this->from - $this->size )) ) );
            else
                echo "prev";
            echo " - ";
            if( $showNext )
                echo $this->ctx->getHref( "next", array( "from" => ($this->from + $this->size) ) );
            else
                echo "next";

            echo " / ";

            if( $showMore )
                echo $this->ctx->getHref( "more", array( "size" => $plus ) );
            else
                echo "more";
            echo " or ";
            if( $showLess )
                echo $this->ctx->getHref( "less", array( "size" => $minus ) );
            else
                echo "less";
            if( $showMore || $showLess ) ;
            echo " records";

            echo "</div><div style='clear:both;'/>";
        }

        echo "</div>";
    }

    public function Display()
    {
        $count = $this->GetCount();
        if( is_null( $count ) )
        {
            echo "ERROR COUNT NULL<br/>";

            return -1;
        }

        echo "$count records<br/>";

        $res = $this->GetResults();

        echo QDumpTable( $res );

        return $count;
    }
}

?>