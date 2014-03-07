<?php

class page_step extends PageMVCSecure
{
	function Run( $params, $posts, &$view_data, &$messageNotUsed )
	{
		HLib("BkgndJobs")->Auto();
	}
}

?>