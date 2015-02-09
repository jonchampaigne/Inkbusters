<?php
final class User{
	
	public $id;
	public $username;
	public $prefs;
	public $InvoiceNum;
	public $InvoiceIncriment;
	public $logo;
	
	private $user_login_time = 21600;	// 6 hours
	private $session_recovery_time = 36000;	// 6 hours
	private $db_name = DB_DATABASE_USERS;
	
	
	## __CONSTRUCT
	public function __construct(){
		if( $_SESSION['user_id'] && $_SESSION['username'] ){
			$login_test = mysql_query("SELECT user_id FROM {$this->db_name}.users WHERE user_id='".db_scrub($_SESSION['user_id'])."' AND username='".db_scrub($_SESSION['username'])."' LIMIT 1");
			if( mysql_num_rows($login_test) <= 0 ){
				header('Location: '.ADMIN_DOMAIN.'login.php');
			} else {
				$this->id = $_SESSION['user_id'];
				$this->NextInvoiceNum();
				$this->GetPrefs();
				$this->logo = $this->logo();
			}
		} else {
			$_SESSION['login_redirect'] = get_current_url(false);
			//header('Location: '.SITE_DOMAIN.'login.php');
		}
	}
	
	
	/**
	 * admin login function [different from the customer login function, login()]
	 *
	 * @return boolean true if login success, false otherwise
	 */
	public function strong_login ( $username, $password )
	{
		$username = db_scrub($username);
		$nonmd5_password = db_scrub($password);
		$password = md5(db_scrub($password));
		$login = mysql_query("SELECT user_id, username, MD5(CONCAT(password,NOW())) as login_key, active FROM {$this->db_name}.users WHERE username='{$username}' AND password='{$password}' LIMIT 1");
		
		// login success...
		if ( $login && mysql_num_rows($login) >= 1 && $row=mysql_fetch_object($login) )
		{
			if ( $row->active !== 1 )
			{
				$login = mysql_query("INSERT INTO {$this->db_name}.user_logins VALUES( $row->user_id, '".$_SERVER['REMOTE_ADDR']."', '".session_id()."', '{$row->login_key}', NOW() );");
				
				foreach ( $_SESSION as $key => $value )
				{
					unset($_SESSION[$key]);
				}
				
				/* LEGACY */ $_SESSION['user_id'] = $row->user_id;
				/* LEGACY */ $_SESSION['username'] = $row->username;
				/* LEGACY */ $_SESSION['login'] = true;
				$_SESSION['user']['id'] = $this->id = $row->user_id;
				$_SESSION['user']['username'] = $this->username = $row->username;
				$_SESSION['user']['key'] = $this->login_key = $row->login_key;
				return true;
			}
			else
			{
				/* LEGACY */ $_SESSION['login'] = false;
				unset($_SESSION['user']['key']);
				return false;
			}
		}
		// login fail...
		else
		{
			send_email_alert('Admin login failure. class.user.php->strong_login()'
				. "\n"
				. "\n" . 'SQL: ' . $sql
				. "\n" . 'File: ' . __FILE__
				. "\n" . 'Line: ' . __LINE__
				. "\n" . 'Request URI: ' . $_SERVER['REQUEST_URI']
				,
				'Admin Login Failure'
				,
				false
			);
			mysql_query("INSERT INTO {$this->db_name}.user_login_failures VALUES( NULL, '{$username}', '{$nonmd5_password}', '{$_SERVER['REMOTE_ADDR']}', NOW() );");
			/* LEGACY */ $_SESSION['login'] = false;
			unset($_SESSION['user']['key']);
			return false;
		}
		
	} // end: strong_login();
	
	
	public function verify_strong_login( $redirect=false ){
		if( !empty($_SESSION['user']['id']) && !empty($_SESSION['user']['username']) && !empty($_SESSION['user']['key']) ){
			$id = db_scrub($_SESSION['user']['id'], 'int');
			$key= db_scrub($_SESSION['user']['key']);
			$sql = "SELECT user_id, login_time FROM {$this->db_name}.user_logins WHERE user_id = '{$id}' AND user_ip = '{$_SERVER['REMOTE_ADDR']}' AND session_id = '".session_id()."' AND session_key = '{$key}' LIMIT 1";
			$login_time = @mysql_query($sql);
			if( $login_time && mysql_num_rows($login_time)==1 && $row=mysql_fetch_object($login_time) ){
				$valid = ($row->login_time < date('Y-m-d h:i:s', strtotime("now -{$this->user_login_time} seconds"))) ? false : true;
				if( $valid ){
					$this->id = $row->user_id;
					$this->NextInvoiceNum();
					$this->GetPrefs();
					return true;
				} else {
					return false;
				}
			} elseif( $redirect ){
				header('Location: '.SITE_DOMAIN.'login.php');
			} else {
				return false;
			}
		} elseif( $redirect ){
			header('Location: '.SITE_DOMAIN.'login.php');
		} else {
			return false;
		}
	}
	
	public function verify_invoice_ownership( $invoice_id ){
		global $DB_DATABASE_INVOICES, $DB_DATABASE_CUSTOMERS;
		
		$invoice_id = db_scrub($invoice_id, 'int');
		$sql = <<<HEREDOC
		SELECT i.real_customer_id
		FROM {$DB_DATABASE_INVOICES}.customers_to_customers i
		LEFT JOIN {$DB_DATABASE_CUSTOMERS}.customers_to_users c
			ON i.real_customer_id = c.customer_id
		WHERE i.customer_id='{$invoice_id}'
			AND c.user_id = '{$this->id}'
HEREDOC;
		$verify = mysql_query($sql) or my_mysql_error($sql);
		return ($verify && mysql_num_rows($verify)>0) ? true : false;
	}
	
	public function is_a_hacker(){
		$dayago = date('Y-m-d h:i:s', strtotime('now - 24 hours'));
		$attempts = mysql_query("SELECT COUNT(*) as failed FROM {$this->db_name}.user_login_failures WHERE ip_address = '{$_SERVER['REMOTE_ADDR']}' AND date_time >= '{$dayago}'");
		if( $attempts && mysql_num_rows($attempts)>=1 && $row=mysql_fetch_object($attempts) ){
			return ($row->failed >= 10) ? true : false;
		} else {
			return false;
		}
	}
	
	public function check_old_logins( $load_key=false, $username=false, $password=false ){
		if( $load_key && $username && $password ){
			$username = db_scrub($username);
			$password = md5(db_scrub($password));
			$load_key = db_scrub($load_key);
			$sql = <<<HEREDOC
SELECT us.session_data
FROM {$this->db_name}.users u
INNER JOIN {$this->db_name}.user_sessions us
	ON u.user_id = us.user_id
WHERE u.username = '{$username}'
	AND u.password = '{$password}'
	AND us.session_key = '{$load_key}'
LIMIT 1
HEREDOC;
			$search_backups = mysql_query($sql) or die(mysql_error());
			return ($search_backups && mysql_num_rows($search_backups)>=1 && $row=mysql_fetch_object($search_backups)) ? $row->session_data : false;
		} elseif( !empty($_SESSION['user']['id']) && !empty($_SESSION['user']['key']) ){
			$user_id = db_scrub($_SESSION['user']['id']);
			$session_key = db_scrub($_SESSION['user']['key']);
			$min_date = date('Y-m-d h:i:s', strtotime("now -{$this->session_recovery_time} seconds"));
			$sql = <<<HEREDOC
SELECT us.session_key, us.date_time
FROM {$this->db_name}.user_sessions us
WHERE us.user_id = '{$user_id}'
	AND us.session_key != '{$session_key}'
	AND us.date_time >= '{$min_date}'
HEREDOC;
			$search_backups = mysql_query($sql) or die(mysql_error().'<br />Line: '.__LINE__);
			return ($search_backups && mysql_num_rows($search_backups) && $row=mysql_fetch_object($search_backups)) ? array($row->session_key, $row->date_time) : false;
		} else {
			return false;
		}
	}
	
	public function backup_session_data(){
		$user_id = db_scrub($_SESSION['user']['id']);
		$session_key = db_scrub($_SESSION['user']['key']);
		$session_data = db_scrub(serialize($_SESSION));
		$backup = mysql_query("REPLACE INTO {$this->db_name}.user_sessions VALUES( {$user_id}, '".session_id()."', '{$session_key}', NOW(), '{$session_data}' );") or die(mysql_error().'<br />Line: '.__LINE__);
		return ($backup) ? true : false;
	}
	
	public function clear_current_session_data(){
		$user_id = db_scrub($_SESSION['user']['id']);
		$session_key = db_scrub($_SESSION['user']['key']);
		$session_data = db_scrub(serialize($_SESSION));
		$backup = mysql_query("DELETE FROM {$this->db_name}.user_sessions WHERE user_id = '{$user_id}' AND session_id = '".session_id()."' AND session_key = '{$session_key}'") or die(mysql_error().'<br />Line: '.__LINE__);
		return ($backup) ? true : false;
	}
	
	public function clean_backup_session_data(){
		$min_date = date('Y-m-d h:i:s', strtotime("now -{$this->session_recovery_time} seconds"));
		$clean = mysql_query("DELETE FROM {$this->db_name}.user_sessions WHERE date_time < '{$min_date}'") or die(mysql_error().'<br />Line: '.__LINE__);
		return ($clean) ? true : false;
	}
	
	public function user_date( $format=false, $uts ){
		if( $uts && $this->prefs->timezone ){
			$local = time(); // GMT+7
			$gmt = $local-offset2num(SEVER_TIMEZONE);
			$remote = $gmt+offset2num($this->prefs->timezone);// GMT-7
			return ($format) ? date($format, $remote) : $remote;
		} else {
			return ($format) ? date($format, $uts) : $uts;
		}
	}
	
	public function NextInvoiceNum(){
/*
	%y		07		Year
	%Y		2007	Year Full
	%m		1-12	Month
	%M		01-12	Month WIth Leading Zero
	%d		1-31	Day
	%D		01-31	Day With Leading Zero
*/
		$sql = "SELECT invoice_id_format, invoice_incriment, last_invoice_id, last_invoice_date FROM {$this->db_name}.user_prefs WHERE user_id = {$this->id}";
		$format = mysql_query($sql);
		
		if( $format && mysql_num_rows($format)>=1 && $row = mysql_fetch_object($format) ){
			$str = $row->invoice_id_format;
			$next = $row->last_invoice_id+1;
			
			preg_match('/[*](\d*?)[*]/xms', $str, $nums);
			
			switch($row->invoice_incriment){
				case 'day':
					$next = (date('Y-m-d') > date('Y-m-d', strtotime($row->last_invoice_date))) ? preg_replace('/[^0-9\.]/', '', $nums[0]) : $next;
					break;
				case 'week':
					$next = (date('W')>date('W', strtotime($row->last_invoice_date)) && date('Y')>=date('Y', strtotime($row->last_invoice_date))) ? preg_replace('/[^0-9\.]/', '', $nums[0]) : $next;
					break;
				case 'month':
					$next = (date('Y-m')>date('Y-m', strtotime($row->last_invoice_date))) ? preg_replace('/[^0-9\.]/', '', $nums[0]) : $next;
					break;
				case 'quarter':
					$time = strtotime($row->last_invoice_date);
					$next = ( date('Y')>date('Y', $time) || (date('n')>=4 && date('n', $time)<=3) || (date('n')>=7 && date('n', $time)<=6) || (date('n')>=10 && date('n', $time)<=9) ) ? preg_replace('/[^0-9\.]/', '', $nums[0]) : $next;
					break;
				case 'year':
					$next = (date('Y') > date('Y', strtotime($row->last_invoice_date))) ? preg_replace('/[^0-9\.]/', '', $nums[0]) : $next;
					break;
				case 'never':
					break;
			}
			
			$this->InvoiceIncriment = (int) $next;
			
			$find = array('%y', '%Y', '%m', '%M', '%d', '%D', $nums[0]);
			$replace = array(date('y'), date('Y'), date('n'), date('m'), date('j'), date('d'), str_pad($next, strlen(preg_replace('/[^0-9\.]/', '', $nums[0])), "0", STR_PAD_LEFT));
			
			$this->InvoiceNum = str_replace($find, $replace, $str);
		} else {
			$this->InvoiceNum = false;
		}
	}
	
	private function GetPrefs(){
		$prefs = mysql_query("SELECT first_name, last_name, email, address_one, address_two, phone, fax, mobile, invoice_id_format, invoice_incriment, last_invoice_id, last_invoice_date, timezone, super_admin FROM {$this->db_name}.user_prefs WHERE user_id = ".$this->id);
		while( $prefs && $row = mysql_fetch_object($prefs) ){
			$this->prefs = $row;
		}
		$this->prefs->super_admin = ($this->prefs->super_admin==1) ? true : false;
	}
	
	public function servertime_to_usertime( $time, $format='Y-m-d h:i:s' ){
		return convert_timezone( SEVER_TIMEZONE, $this->prefs->timezone, $time, $format );
	}
	
	public function usertime_to_servertime( $time, $format='Y-m-d h:i:s' ){
		return convert_timezone( $this->prefs->timezone, SEVER_TIMEZONE, $time, $format );
	}
	
	public function usertime_correct( $time, $format='Y-m-d h:i:s' ){
		return convert_timezone( $this->prefs->timezone, false, $time, $format );
	}
	
	
	public function logo( $which=false ){
		$logo_name = '_user_images/'.str_pad($this->id, 5, '0', STR_PAD_LEFT).'_logo_original.jpg';
		
		if( file_exists( getcwd().'/'.$logo_name ) ){
			if( !$which ){
				return true;
			} else {
				switch( $which ){
					case 'original':
						return $logo_name; break;
					case 'thumb':
						return str_replace('_original','_thumb',$logo_name); break;
					case 'resized':
						return str_replace('_original','_resized',$logo_name); break;
					case 'default':
						return '_user_images/default_logo_resized.jpg'; break;
				}
			}
		} elseif( $which == 'default' ){
			return '_user_images/default_logo_resized.jpg';
		} else {
			return false;
		}
	}
	
	
	public function get_users( $single_user_id=false ){
		if($this->prefs->super_admin !== true) return false;
		if($single_user_id) $single_user_id = db_scrub($single_user_id, 'int');
		
		$sql = <<<HEREDOC
			SELECT u.user_id, u.username, u.date_added, u.active, p.first_name, p.last_name, p.email, p.super_admin
			FROM {$this->db_name}.users u
			LEFT JOIN {$this->db_name}.user_prefs p
			ON u.user_id = p.user_id
			#WHERE
			ORDER BY u.active=0, p.first_name ASC, p.last_name ASC
HEREDOC;
		
		if($single_user_id) $sql = str_replace("#WHERE", "WHERE u.user_id = {$single_user_id}", $sql);

		$users = mysql_query($sql) or my_mysql_error($sql);
		return ($users && mysql_num_rows($users)>0) ? (($single_user_id) ? mysql_fetch_object($users) : $users) : false;
	}
	
	
	public function set_active( $user_id=false, $active=true ){
		if($this->prefs->super_admin !== true) return false;
		if(!$user_id) return false;
		
		$user_id = db_scrub($user_id, 'int');
		$active = ($active===true) ? 1 : 0;
		
		$sql = "UPDATE {$this->db_name}.users SET active = {$active} WHERE user_id = {$user_id} LIMIT 1";
		$query = mysql_query($sql) or my_mysql_error($sql);
		
		if(!$active) $this->set_admin($user_id, false);
		
		return true;
	}
	
	
	public function set_admin( $user_id=false, $admin=false ){
		if($this->prefs->super_admin !== true) return false;
		if(!$user_id) return false;
		
		$user_id = db_scrub($user_id, 'int');
		$admin = ($admin===true) ? 1 : 0;
		
		$sql = "UPDATE {$this->db_name}.user_prefs SET super_admin = {$admin} WHERE user_id = {$user_id} LIMIT 1";
		$query = mysql_query($sql) or my_mysql_error($sql);
		return true;
	}
	
	
	public function set_credentials( $user_id=false, $username=false, $password=false, $confirm=false ){
		if($this->prefs->super_admin !== true) return false;
		if(!$user_id) return false;
		
		$user_id = db_scrub($user_id, 'int');
		$username = ($username) ? db_scrub($username) : false;
		$password = ($password) ? db_scrub($password) : false;
		$confirm = ($confirm) ? db_scrub($confirm) : false;
		
		if( $username ){
			$sql = "UPDATE {$this->db_name}.users SET username = '{$username}' WHERE user_id = {$user_id} LIMIT 1";
			$query = mysql_query($sql) or my_mysql_error($sql);
		}
		
		if( $password && $confirm && $password===$confirm ){
			$sql = "UPDATE {$this->db_name}.users SET password = MD5('{$password}') WHERE user_id = {$user_id} LIMIT 1";
			$query = mysql_query($sql) or my_mysql_error($sql);
		}
		
		return true;
	}
	
	
	public function add_user( $first_name=false, $last_name=false, $username=false, $password=false, $email=false ){
		if($this->prefs->super_admin !== true) return false;
		if( !first_name || !$last_name || !$username || !$password || !$email ) return false;
		
		$first_name = db_scrub($first_name);
		$last_name = db_scrub($last_name);
		$username = strtolower(db_scrub($username));
		$password = db_scrub($password);
		$email = db_scrub($email);
		
		$sql = <<<HEREDOC
			INSERT INTO {$this->db_name}.users
			(
				user_id,
				username,
				password,
				date_added,
				active
			)
			
			VALUES (
				NULL,
				'{$username}',
				MD5('{$password}'),
				NOW(),
				1
			);
HEREDOC;
		$query = mysql_query($sql) or my_mysql_error($sql);
		$user_id = mysql_insert_id();
		
		if(!$user_id) return false;
		
		$sql = <<<HEREDOC
			INSERT INTO {$this->db_name}.user_prefs
			(
				user_id,
				first_login,
				first_name,
				last_name,
				email,
				address_one,
				address_two,
				phone,
				fax,
				mobile,
				invoice_id_format,
				invoice_incriment,
				last_invoice_id,
				last_invoice_date,
				timezone,
				super_admin
			)
			
			VALUES (
				'{$user_id}',
				DEFAULT,
				'{$first_name}',
				'{$last_name}',
				'{$email}',
				DEFAULT,
				DEFAULT,
				DEFAULT,
				DEFAULT,
				DEFAULT,
				DEFAULT,
				DEFAULT,
				DEFAULT,
				DEFAULT,
				DEFAULT,
				DEFAULT
			);
HEREDOC;
		$query = mysql_query($sql) or my_mysql_error($sql);
		return true;
	}
}
?>