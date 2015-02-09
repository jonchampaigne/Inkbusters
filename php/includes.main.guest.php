<?php
	define('GUEST_MODE', true);
	
	include('includes.session.php');
	include('includes.constants.php');
	include('functions.general.php');
	
	if(strstr(get_current_url(), CUSTOMER_URL_PATH)) header('Location: '.str_replace(CUSTOMER_URL_PATH, CUSTOMER_DOMAIN, get_current_url()));
	
	db_connect();
	$User = new Customer_Users();
?>