<?php

class RPCProxy
{
    var $baseUrl = "invalid://";
    var $rpcEndpointPageAddress;
    var $token = null;

    var $lastCalledUrl;
    var $lastAnswer;

    public function __construct( $baseUrl, $rpcEndpointPageAddress, $token )
    {
        $this->baseUrl = $baseUrl;
        $this->rpcEndpointPageAddress = $rpcEndpointPageAddress;
        $this->token = $token;
    }

    public function __call( $name, $arguments )
    {
        $res = $this->callUrl( $name, $arguments, $rawAnswer );

        return $res;
    }

    function callUrl( $method, $params, &$rawAnswer )
    {
        $url = "{$this->baseUrl}?container={$this->rpcEndpointPageAddress}";

        $this->lastCalledUrl = $url;
        $this->lastAnswer = null;

        $postPayload = array(
            "magic" => "v1",
            "method" => $method,
            "parameters" => json2string( $params ),
            "user_security_token" => $this->token );

        $ch = curl_init( $url );
        curl_setopt( $ch, CURLOPT_POST, true );
        // curl_setopt( $ch, CURLOPT_HTTPHEADER, array( "Content-type: text/html; charset=UTF-8" ) );
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $postPayload );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        $response = curl_exec( $ch );
        curl_close( $ch );

        // remove BOM if present
        $__BOM = pack( 'CCC', 239, 187, 191 );
        if( 0 === strpos( $response, $__BOM ) )
            $response = substr( $response, 3 );

        $rawAnswer = $response;
        $this->lastAnswer = $rawAnswer;

        $res = string2Json( $response );

        return $res;
    }
}

class RPCServer
{
    // service real implementation
    var $impl;

    public function __construct( $impl )
    {
        $this->impl = $impl;
    }

    public function ProcessRequest( $params )
    {
        if( (!isset($params["magic"])) || ($params["magic"] != "v1") )
        {
            echo "No fool !";

            return null;
        }

        if( !isset($params["method"]) )
        {
            echo "NO PARAMS GIVEN : ERROR<br/>";

            return null;
        }

        $method = $params["method"];

        if( !isset($params["parameters"]) )
        {
            echo "NO PARAMS GIVEN : ERROR<br/>";

            return null;
        }

        $parameters = string2Json( $params["parameters"] );

        $res = call_user_func_array( array( $this->impl, $method ), $parameters );

        echo json2string( $res );
    }
}

?>