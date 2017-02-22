<?php

class StackTraceRegistry extends HexaComponentImpl
{
    public function RegisterStackTrace($pass = 2)
    {
        $trace = getStackTrace($pass);
        $traceId = md5($trace);

        $dir = APP_DATA_DIR . 'logs/stacktraces';
        ensureDirectoryExists($dir);

        $stackFileName = $dir . '/' . $traceId . '.txt';

        if (!file_exists($stackFileName)) {
            $stackFile = fopen($stackFileName, 'w');
            if ($stackFile != null) {
                fwrite($stackFile, "TRACE_ID: $traceId\r\n\r\n");
                fwrite($stackFile, "CALL STACK:\r\n$trace\r\n\r\n");
                fclose($stackFile);
            }
        }

        return $traceId;
    }
}