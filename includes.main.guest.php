<?php
	define('GUEST_MODE', true);
	
	include('includes.session.php');
	include('includes.constants.php');
	include('functions.general.php');
	
	/*debug...
	echo('get_current_url = ' . get_current_url());
	die('   CUSTOMER_URL_PATH = ' . CUSTOMER_URL_PATH);
	
	// orig:  does something about redirecting non-logged in users, but fuxxors non-subdomain things...
	if(strstr(get_current_url(), CUSTOMER_URL_PATH))
	{
		header('Location: '.str_replace(CUSTOMER_URL_PATH, CUSTOMER_DOMAIN, get_current_url()));
		die();
	}
	*/
	
	db_connect();
	$User = new Customer_Users();
?>