<?php

abstract class PageProxySecure extends PageImpl
{
    abstract function ProcessQuery( $params );

    public function Execute( $params, $posts )
    {
        HLibSecurity()->AnalyseLoggedUser();

        $loggedUser = HLibSecurity()->GetLoggedUser();
        if( $loggedUser == null )
        {
            echo "Not logged in<br/>";

            return;
        }

        $params = array_merge( $params, $posts );

        $this->ProcessQuery( $params );
    }
}

?>