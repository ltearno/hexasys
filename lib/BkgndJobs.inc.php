<?php

/*
*/


/**
 * Interface IBkgndJobMng
 *
 * Interface of Jobs which are ran periodically in the background, typically through the cron daemon
 */
interface IBkgndJobMng
{
    public function Init( $db, $qpath );

    /**
     * When a job is first created, this method is called for the JobMng to install its data.
     * This method must return a value that will used to call the JobMng back when stepping the Job
     *
     * @param $parameters
     */
    public function OnCreate( $parameters );

    /**
     * Main invocation method for the job. This is called periodically by the system.
     *
     * @param $ctx          value of the last stored Job state variable. Implementation can modify the $ctx variable, and it will be stored accross calls for the next Step() call
     * @param $finished     If the job is finished, the $finished var must be set to 1
     * @param $nextCallTime date-time at which the job wants to be called again
     */
    public function Step( &$ctx, &$finished, &$nextCallTime );

    /**
     * Called when the job is destroyed, it is the moment for the JobMng to free its resources, if any
     *
     * @param $ctx job context
     */
    public function OnDelete( $ctx );

    /**
     * Provided the current $ctx value of the job, this method should return a string shortly describing
     * the job state
     *
     * @param $ctx job context
     */
    public function GetDescription( $ctx );
}

class BkgndJobs extends HexaComponentImpl
{
    /**
     * @param $jobAddress string
     * @return IBkgndJobMng
     */
    private function getJobMngInstance( $jobAddress )
    {
        $classContainer = APP_DIR . "jobs/job." . $jobAddress . ".inc.php";
        include_once($classContainer);

        $classToCall = "job_" . $jobAddress;

        /** @var IBkgndJobMng $jobMng */
        $jobMng = new $classToCall();

        if( $jobMng != null )
            $jobMng->Init( $this->db, $this->QPath );

        return $jobMng;
    }

    public function GetJobDescription( $jobId )
    {
        $job = $this->QPath->QueryOne( "bkgnd_jobs [id=$jobId]" );
        if( $job == null )
            return "*JOB NOT FOUND *";

        $jobAddress = $job["bkgnd_jobs.job_mng_address"];
        $jobMng = $this->getJobMngInstance( $jobAddress );

        $ctxVar = HLibStoredVariables()->Read( SYS_JOBS, $job["bkgnd_jobs.ctx_variable_name"] );

        return $jobMng->GetDescription( $ctxVar );
    }

    public function Create( $jobAddress, $params )
    {
        $jobMng = $this->getJobMngInstance( $jobAddress );
        if( $jobMng == null )
            return -1;

        $jobCtx = $jobMng->OnCreate( $params );

        $ctxVarName = "job_var_" . uniqid();
        $jobId = $this->QPath->Insert( "bkgnd_jobs", array( "job_mng_address" => $jobAddress, "ctx_variable_name" => $ctxVarName ) );

        HLibStoredVariables()->Store( SYS_JOBS, $ctxVarName, $jobCtx );

        return $jobId;
    }

    public function Step( $jobId )
    {
        $job = $this->QPath->QueryOne( "bkgnd_jobs [id=$jobId]" );
        if( $job == null )
            return -1;

        echo "Execute job " . $job["bkgnd_jobs.id"] . " " . $job["bkgnd_jobs.job_mng_address"] . "<br/>";
        ob_flush();

        $jobMng = $this->getJobMngInstance( $job['bkgnd_jobs.job_mng_address'] );
        if( $jobMng == null )
            return -2;

        $ctxVar = HLibStoredVariables()->Read( SYS_JOBS, $job['bkgnd_jobs.ctx_variable_name'] );

        $nextCallTime = $job["bkgnd_jobs.next_call_time"];

        echo "BkgndJob context variable dump:<br/>";
        Dump( $ctxVar );

        $finished = false;
        $m = HLibMeasure()->Start();
        $jobMng->Step( $ctxVar, $finished, $nextCallTime );
        $ms = HLibMeasure()->End( $m );

        HLibStoredVariables()->Store( SYS_JOBS, $job['bkgnd_jobs.ctx_variable_name'], $ctxVar );

        $this->QPath->Update( "bkgnd_jobs", "id=$jobId", array(
            "next_call_time" => $nextCallTime,
            "exec_time" => $ms + $job['bkgnd_jobs.exec_time'],
            "times_stepped" => 1 + $job['bkgnd_jobs.times_stepped'],
            "finished" => ($finished ? 1 : 0) ) );

        return 0;
    }

    public function Delete( $jobId )
    {
        $job = $this->QPath->QueryOne( "bkgnd_jobs [id=$jobId]" );
        if( $job == null )
            return 0; // maybe already done, anyway : nothing to do !

        $jobMng = $this->getJobMngInstance( $job['bkgnd_jobs.job_mng_address'] );
        if( $jobMng == null )
            return -2;

        $ctxVar = HLibStoredVariables()->Read( SYS_JOBS, $job['bkgnd_jobs.ctx_variable_name'] );

        $jobMng->OnDelete( $ctxVar );

        // delete the Stored ctx variable
        HLibStoredVariables()->Remove( SYS_JOBS, $job['bkgnd_jobs.ctx_variable_name'] );

        // remove the database entry
        $this->QPath->Delete( "bkgnd_jobs", "id=$jobId" );

        return 0;
    }

    // Chooses a non-finished job and step it
    public function Auto()
    {
        $toStepJobs = $this->QPath->QueryValueList( "bkgnd_jobs [finished=0] [next_call_time<=now()]", "bkgnd_jobs.id" );
        if( count( $toStepJobs ) == 0 )
            return; // there is no job to execute

        // take one randomly
        shuffle( $toStepJobs );
        $jobId = $toStepJobs[0];

        $this->Step( $jobId );
    }
}

?>