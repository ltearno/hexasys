<?php

require_once('../include/nstrees.php');

class QTreeMng
{
    var $handle = null;

    public function Init( $connection, $table, $leftFieldName, $rightFieldName )
    {
        $this->handle = array();
        $this->handle['dbconnection'] = $connection;
        $this->handle['table'] = $table;
        $this->handle['lvalname'] = $leftFieldName;
        $this->handle['rvalname'] = $rightFieldName;
    }

    public function CreateTree( $type = null, $data = null )
    {
        $root = $this->GetRoot();
        $node = $this->AddLastChild( $root, $type, $data );

        return $node;
    }

    public function PrintTree( $printedFields )
    {
        nstPrintTree( $this->handle, $printedFields );
    }

    public function GetNode( $nodeId )
    {
        return nstGetNodeWhere( $this->handle, "id=$nodeId" );
    }

    public function GetRoot()
    {
        $root = nstRoot( $this->handle );
        if( $root == null )
            $root = nstNewRoot( $this->handle );

        return $root;
    }

    public function GetRootNode( $node )
    {
        return nstNodeRoot( $this->handle, $node );
    }

    public function GetParent( $node )
    {
        return nstAncestor( $this->handle, $node );
    }

    public function GetNbChildren( $node )
    {
        return nstNbChildren( $this->handle, $node );
    }

    public function AddBrother( $node, $type = null, $data = null )
    {
        $newNode = nstNewNextSibling( $this->handle, $node );
        if( $data != null && $type != null )
            $this->SetData( $newNode, $type, $data );

        return $newNode;
    }

    public function AddLastChild( $node, $type = null, $data = null )
    {
        $newNode = nstNewLastChild( $this->handle, $node );
        if( $data != null && $type != null )
            $this->SetData( $newNode, $type, $data );

        return $newNode;
    }

    public function AddFirstChild( $node, $type = null, $data = null )
    {
        $newNode = nstNewFirstChild( $this->handle, $node );
        if( $data != null && $type != null )
            $this->SetData( $newNode, $type, $data );

        return $newNode;
    }

    public function AddPrevSibling( $node, $type = null, $data = null )
    {
        $newNode = nstNewPrevSibling( $this->handle, $node );
        if( $data != null && $type != null )
            $this->SetData( $newNode, $type, $data );

        return $newNode;
    }

    public function RemoveNode( $node )
    {
        nstDelete( $this->handle, $node );

        return 1;
    }


    public function SetData( $node, $type, $data )
    {
        nstSetNodeAttributes( $this->handle, $node, array( "type" => $type, "data" => $data ) );
    }


    public function Walk( $node )
    {
        return nstWalkPreorder( $this->handle, $node );
    }

    public function WalkCurrent( $walkHandle )
    {
        return nstWalkCurrent( $this->handle, $walkHandle );
    }

    public function WalkNext( &$walkHandle )
    {
        return nstWalkNext( $this->handle, $walkHandle );
    }

    public function WalkLevel( $walkHandle )
    {
        return nstWalkLevel( $this->handle, $walkHandle );
    }
}

?>