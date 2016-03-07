<?php

class LocaleInfo extends HexaComponentImpl
{
    var $locale = "en";

    public function SetLocale( $locale )
    {
        if( $locale == null || $locale == "" )
            return;

        if( $locale == "default" )
            $this->locale = "en";

        $this->locale = $locale;
    }

    public function GetLocale()
    {
        return $this->locale;
    }
}

?>