<?php

class page_list extends PageMVCSecure
{
    function Run( $params, $posts, &$view_data, &$messageNotUsed )
    {
        if( isset($posts["delete_job"]) )
        {
            $jobId = $posts["delete_job"];
            HLibBkgndJobs()->Delete( $jobId );

            echo "Job $jobId has been deleted<br/><br/><br/>";
        }

        if( isset($posts["step_job"]) )
        {
            $jobId = $posts["step_job"];
            HLibBkgndJobs()->Step( $jobId );

            echo "Job $jobId has been stepped<br/><br/><br/>";
        }

        $jobs = $this->QPath->QueryEx( "bkgnd_jobs" );
        echo "Background jobs list:<br/>";
        echo QDumpTable( $jobs, array( "Description and status" => new FieldJobDescription(), "Step" => new FieldJobActions( $this ) ) );
    }
}

class FieldJobDescription
{
    function RowValue( QPathResult $qPathResult, $i )
    {
        return HLibBkgndJobs()->GetJobDescription( $qPathResult->GetVal( $i, "bkgnd_jobs.id" ) );
    }
}

class FieldJobActions
{
    var $page;

    function __construct( Page $page )
    {
        $this->page = $page;
    }

    function RowValue( QPathResult $qPathResult, $i )
    {
        return getButton( $this->page, "Step", array( "step_job" => $qPathResult->GetVal( $i, "bkgnd_jobs.id" ) ), null ) . getButton( $this->page, "Delete", array( "delete_job" => $qPathResult->GetVal( $i, "bkgnd_jobs.id" ) ), null );
    }
}

?>