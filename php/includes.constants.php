<?php

# ERROR REPORTING
error_reporting(E_ALL ^ E_NOTICE); // error reporting set to fatal/warning only 

# ADMIN VARIABLES
define('ADMIN_EMAIL',					'engines@orionimaging.com'); //'Tyler.Vigeant@inkbustersusa.com');
define('IS_LOCAL',						substr($_SERVER['DOCUMENT_ROOT'], 0, 3)=='C:/');

if (IS_LOCAL)
{
	# DNS VARIABLES
	define('SITE_DOMAIN',					'http://127.0.0.1/InkBustersUsa.com/public_html/');
	define('ADMIN_DOMAIN',					'http://127.0.0.1/InkBustersUsa.com/public_html/admin/');
	define('ADMIN_URL_PATH',				'http://127.0.0.1/InkBustersUsa.com/public_html/admin/');
	define('CUSTOMER_DOMAIN',				'http://127.0.0.1/InkBustersUsa.com/public_html/customers/');
	define('CUSTOMER_URL_PATH',				'http://127.0.0.1/InkBustersUsa.com/public_html/customers/');
	define('MOBILE_DOMAIN',					'http://127.0.0.1/InkBustersUsa.com/public_html/mobile/');
	define('MOBILE_URL_PATH',				'http://127.0.0.1/InkBustersUsa.com/public_html/mobile/');

	# PATH VARIABLE
	define('PUBLIC_PATH',					$_SERVER['DOCUMENT_ROOT'] . '/InkBustersUsa.com/public_html/');
	define('ADMIN_PATH',					$_SERVER['DOCUMENT_ROOT'] . '/InkBustersUsa.com/public_html/admin/');
	define('CUSTOMER_PATH',					$_SERVER['DOCUMENT_ROOT'] . '/InkBustersUsa.com/public_html/customers/');
}
else //live...
{
	# DNS VARIABLES
	define('SITE_DOMAIN',					'http://www.inkbustersusa.com/');
	define('ADMIN_DOMAIN',					'http://admin.inkbustersusa.com/');
	define('ADMIN_URL_PATH',				'http://www.inkbustersusa.com/admin/');
	define('CUSTOMER_DOMAIN',				'http://customers.inkbustersusa.com/');
	define('CUSTOMER_URL_PATH',				'http://www.inkbustersusa.com/customers/');
	define('MOBILE_DOMAIN',					'http://mobile.inkbustersusa.com/');
	define('MOBILE_URL_PATH',				'http://www.inkbustersusa.com/mobile/');

	# PATH VARIABLE
	define('PUBLIC_PATH',					'/home/bilyum/public_html/');
	define('ADMIN_PATH',					'/home/bilyum/public_html/admin/');
	define('CUSTOMER_PATH',					'/home/bilyum/public_html/customers/');
}

# SESSION VARIABLES
//define('SESSION_NAME',				'owt-ses');
//define('SESSION_DOMAIN',				'time.oregonwebteam.com');

# DATABASE ACCESS VARIABLES
define('DB_LOCATION',					'localhost');
define('DB_ACCOUNT',					'bilyum_inkdata');
define('DB_PASSWORD',					'j8uk94d1');
define('DB_DATABASE',					'bilyum_users');
define('DB_DATABASE_USERS',				'bilyum_users');
define('DB_DATABASE_CUSTOMERS',			'bilyum_customeraccounts');
define('DB_DATABASE_PROPOSALS',			'bilyum_proposals');
define('DB_DATABASE_INVOICES',			'bilyum_invoices');

# SITE VARIABLES
define('SITE_CLOSED',					false);
define('SITE_CLOSED_REASON',			'Under-going updates to dates and timezones.');
define('SEVER_TIMEZONE',				'America/Chicago');

# HEREDOC USABLE VARIABLES
$DB_DATABASE_USERS = DB_DATABASE_USERS;
$DB_DATABASE_CUSTOMERS = DB_DATABASE_CUSTOMERS;
$DB_DATABASE_PROPOSALS = DB_DATABASE_PROPOSALS;
$DB_DATABASE_INVOICES = DB_DATABASE_INVOICES;
$PUBLIC_PATH = PUBLIC_PATH;
$ADMIN_PATH = ADMIN_PATH;

$adminAcl = array(
	'24.21.69.57', //Lawrence, c.10/2009
	//'24.20.194.84', //legacy, removed 10/1/2009
	//'208.54.15.72', //legacy, removed 10/1/2009
);
$admin_ip = in_array($_SERVER['REMOTE_ADDR'], $adminAcl) ? true : false;
