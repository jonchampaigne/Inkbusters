<?php
/**
 * includes.main.php
 */
	include('includes.session.php');
	include('includes.constants.php');
	include('functions.general.php');
	include('functions.mobile.php');
	
	$_current_url = get_current_url();
	if(strstr($_current_url, ADMIN_URL_PATH) && !IS_LOCAL && !IS_DEV)
	{
		header('Location: '.str_replace(ADMIN_URL_PATH, ADMIN_DOMAIN, $_current_url));
	}
	if(strstr($_current_url, ADMIN_DOMAIN) && detect_mobile_device())
	{
		header('Location: '.MOBILE_DOMAIN);
	}
	if(strstr($_current_url, MOBILE_URL_PATH))
	{
		header('Location: '.str_replace(MOBILE_URL_PATH, MOBILE_DOMAIN, $_current_url));
	}
	
	db_connect();
	if(!$no_user_include)
	{
		$User = new User();
	}
