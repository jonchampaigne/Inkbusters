<?php
	require_once('includes.constants.php'); //required: weird, but true...
	
	/*
	if (!IS_LOCAL && !IS_DEV)
	{
		ini_set('session.save_path', '/home/bilyum/ses_data');
	}
	*/
	ini_set('session.cookie_lifetime', 21600);
	ini_set('session.gc_maxlifetime', 21600);
	
	session_start();
