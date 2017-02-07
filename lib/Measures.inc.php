<?php

class Measures extends HexaComponentImpl
{
    var $data;
    var $name;

    function __construct($name = '')
    {
        $this->name = $name;
        $this->data = array();

        $this->store("START");
    }

    public function Store($text)
    {
        $this->data[] = array(
            "time" => microtime(true),
            "text" => $text
        );
    }

    public function Report()
    {
        $logger = new Logger();
        $logger->Init("Measures_$this->name.txt", Logger::LOG_MSG);

        for ($i = 1; $i < count($this->data); $i++) {
            $startTime = $this->data[$i - 1]["time"];
            $endTime = $this->data[$i]["time"];

            $ms = (1000 * ($endTime - $startTime));

            $logger->Log(Logger::LOG_MSG, $this->data[$i]["text"] . ": " . $ms . "ms");
        }

        $total = 1000 * ($this->data[count($this->data) - 1]["time"] - $this->data[0]["time"]);

        $logger->Log(Logger::LOG_MSG, "TOTAL: $total ms");

        $logger->Term();
    }
}

?>