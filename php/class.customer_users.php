<?php
final class Customer_Users{
	
	public $id;
	public $customer_id;
	public $user_name;
	public $customer_name;
	public $timezone;
	public $owned_by;
	
	protected $key;
	
	private $db_customer = DB_DATABASE_CUSTOMERS;
	private $db_user = DB_DATABASE_USERS;
	
	## __CONSTRUCT
	public function __construct(){
		$this->id = $_SESSION['guest_user']->id;
		$this->customer_id = $_SESSION['guest_user']->customer_id;
		$this->user_name = $_SESSION['guest_user']->user_name;
		$this->timezone = $_SESSION['guest_user']->timezone;
		$this->customer_name = $_SESSION['guest_user']->customer_name;
		$this->owned_by = $_SESSION['guest_user']->owned_by;
		$this->GetPrefs();
	}
	
	## GETPREFS
	## A modified version of GetPrefs() from the User Class. This class is here because the User Class is not available to guest users, however the information is required to view and print Invoices
	private function GetPrefs(){
		$prefs = mysql_query("SELECT first_name, last_name, email, address_one, address_two, phone, fax, mobile FROM {$this->db_user}.user_prefs WHERE user_id = ".db_scrub($this->owned_by));
		if( $prefs && mysql_num_rows($prefs)>=1 && $row=mysql_fetch_object($prefs) ) $this->prefs = $row;
		
		$prefs = mysql_query("SELECT submit_orders, view_invoices FROM {$this->db_customer}.customer_user_accounts WHERE user_account_id  = ".db_scrub($this->id));
		if( $prefs && mysql_num_rows($prefs)>=1 && $row=mysql_fetch_object($prefs) ){
			$this->prefs->submit_orders = $row->submit_orders;
			$this->prefs->view_invoices = $row->view_invoices;
		}
	}
	
	public function login( $username, $password ){
		$username = db_scrub($username);
		$password = db_scrub($password);
		
		$sql = <<<HEREDOC
		SELECT ua.user_account_id, uac.customer_id, ua.user_name
		FROM {$this->db_customer}.customer_user_accounts ua
		LEFT JOIN {$this->db_customer}.customer_user_accounts_to_customers uac
			ON ua.user_account_id = uac.user_account_id
		WHERE ua.username = '{$username}'
			AND ua.password = '{$password}'
		LIMIT 1
HEREDOC;

		$login = mysql_query($sql) or die(mysql_error().'<br />Line: '.__LINE__);
		if( $login && mysql_num_rows($login)>=1 && $row=mysql_fetch_object($login) ){
			$this->id = $_SESSION['guest_user']->id = $row->user_account_id;
			$this->customer_id = $_SESSION['guest_user']->customer_id = $row->customer_id;
			$this->user_name = $_SESSION['guest_user']->user_name = $row->user_name;
			
			$key = md5(date('F jS, Y - h:i:sa P'));
			$sql = "UPDATE {$this->db_customer}.customer_user_accounts SET current_key = '{$key}' LIMIT 1";
			$update = mysql_query($sql) or die(mysql_error().'<br />Line: '.__LINE__);
			if($update) $this->key = $_SESSION['guest_user']->key = $key;
			
			$sql = <<<HEREDOC
			SELECT c.customer_name, up.timezone, cu.user_id
			FROM {$this->db_customer}.customer_user_accounts ua
			LEFT JOIN {$this->db_customer}.customer_user_accounts_to_customers uac
				ON ua.user_account_id = uac.user_account_id
			LEFT JOIN {$this->db_customer}.customers c
				ON uac.customer_id = c.customer_id
			LEFT JOIN {$this->db_customer}.customers_to_users cu
				ON uac.customer_id = cu.customer_id
			LEFT JOIN {$this->db_user}.user_prefs up
				ON cu.user_id = up.user_id
			WHERE ua.user_account_id = '{$this->id}'
			LIMIT 1
HEREDOC;
			$info = mysql_query($sql) or die(mysql_error().'<br />Line: '.__LINE__);
			if( $info && mysql_num_rows($info)>=1 && $row=mysql_fetch_object($info) ){
				$this->timezone = $_SESSION['guest_user']->timezone = $row->timezone;
				$this->customer_name = $_SESSION['guest_user']->customer_name = $row->customer_name;
				$this->owned_by = $_SESSION['guest_user']->owned_by = $row->user_id;
			}

			return true;
		} else {
			return false;
		}
	}
	
	public function is_logged_in(){
		return (!empty($_SESSION['guest_user']->id) && !empty($_SESSION['guest_user']->customer_id)) ? true : false;
	}
	
	public function get_invoices(){
		$Customers = new Customers();
		return $Customers->get_invoices( $this->owned_by, $this->customer_id, false );
	}
	
	public function verify_cartridge_access( $cartridge_id ){
		if(!$cartridge_id) return false;
		$user_id = db_scrub($this->id);
		$customer_id = db_scrub($this->customer_id);
		$cartridge_id = db_scrub($cartridge_id);
		
		$sql = <<<HEREDOC
		SELECT 1 as allowed
		FROM {$this->db_customer}.customer_user_accounts ua
		LEFT JOIN {$this->db_customer}.customer_user_accounts_to_customers uac
			ON ua.user_account_id = uac.user_account_id
		LEFT JOIN {$this->db_customer}.customers c
			ON uac.customer_id = c.customer_id
		LEFT JOIN {$this->db_customer}.cartridges cart
			ON c.customer_id = cart.customer_id
		WHERE ua.user_account_id = '{$user_id}'
			AND c.customer_id = '{$customer_id}'
			AND cart.cartridge_id = '{$cartridge_id}'
		LIMIT 1
HEREDOC;
		$access = mysql_query($sql) or die(mysql_error().'<br />Line: '.__LINE__);
		return ($access && mysql_num_rows($access)>=1 && $row=mysql_fetch_object($access)) ? true : false;
	}
	
	public function verify_user_access( $user_account_id, $customer_id ){
		$user_account_id = db_scrub($user_account_id, 'int');
		$customer_id = db_scrub($customer_id, 'int');
		$sql = "SELECT customer_id FROM {$this->db_customer}.customer_user_accounts_to_customers WHERE customer_id = '{$customer_id}' AND user_account_id = '{$user_account_id}' LIMIT 1";
		$access = mysql_query($sql) or my_mysql_error($sql);
		return ($access && mysql_num_rows($access)>0) ? true : false;
	}
	
	public function get_cartridge_info( $cartridge_id ){
		$cartridge_id = db_scrub($cartridge_id, 'int');
		
		$sql = <<<HEREDOC
		SELECT c.cartridge, c.shorthand, c.price_user as price, 
			GROUP_CONCAT(DISTINCT ct.cartridge_type ORDER BY ct.cartridge_type ASC SEPARATOR '; ') as description,
			p.printer, m.manufacturer_name as printer_manufacturer, p.printer_location
			FROM {$this->db_customer}.cartridges c
		
		LEFT JOIN {$this->db_customer}.cartridges_to_types ctt
			ON c.cartridge_id = ctt.cartridge_id
		LEFT JOIN {$this->db_customer}.cartridge_types ct
			ON ctt.cart_type_id  = ct.cart_type_id
		
		LEFT JOIN {$this->db_customer}.cartridges_to_printers ctp
			ON c.cartridge_id  = ctp.cartridge_id
		LEFT JOIN {$this->db_customer}.printers p
			ON ctp.printer_id = p.printer_id
			
		LEFT JOIN {$this->db_customer}.printers_to_manufacturers ptm
			ON ptm.printer_id = p.printer_id
		LEFT JOIN {$this->db_customer}.manufacturers m
			ON ptm.manufacturer_id  = m.manufacturer_id
		
		WHERE c.cartridge_id = '{$cartridge_id}'
		GROUP BY c.cartridge_id
HEREDOC;

		$cartridge = mysql_query($sql) or my_mysql_error($sql);
		return ($cartridge && mysql_num_rows($cartridge)>0) ? $cartridge : false;
	}
	
	
	public function get_accounts( $customer_id ){
		$customer_id = db_scrub($customer_id);
		
		$sql = <<<HEREDOC
		SELECT cua.user_account_id, cua.user_name, cua.email, cua.username, cua.password, cua.date_added, cua.submit_orders, cua.view_invoices
		FROM {$this->db_customer}.customer_user_accounts cua
		LEFT JOIN {$this->db_customer}.customer_user_accounts_to_customers cuatc
			ON cua.user_account_id = cuatc.user_account_id
		WHERE cuatc.customer_id = '{$customer_id}'
		ORDER BY cua.user_name ASC, cua.date_added ASC
HEREDOC;

		$accounts = mysql_query($sql) or my_mysql_error($sql);
		return ($accounts) ? $accounts : false;
	}
	
	public function change_user_setting( $setting_name, $user_account_id ){
		$user_account_id = db_scrub($user_account_id, 'int');
		$setting_name = db_scrub($setting_name);
		$settings_array = array('submit_orders','view_invoices');
		if( in_array($setting_name, $settings_array) ){
			$sql = "SELECT {$setting_name} FROM {$this->db_customer}.customer_user_accounts WHERE user_account_id='{$user_account_id}'";
			$current = mysql_query($sql) or my_mysql_error($sql);
			$set = ($current && mysql_num_rows($current)) ? ((mysql_result($current,0,$setting_name)=='0')?1:0) : false;
			if( $set!==false ){
				$sql = "UPDATE {$this->db_customer}.customer_user_accounts SET {$setting_name}='{$set}' WHERE user_account_id='{$user_account_id}'";
				$update = mysql_query($sql) or my_mysql_error($sql);
				return ($update) ? true : false;
			} else {
				return false;
			}
		} else {
			return false;
		}
	}
	
	public function change_user_info( $user_account_id, $user_name=false, $email=false, $username=false, $password=false ){
		if( $username && $this->username_exists($username, $user_account_id) ) die('This username already exists for another user. Please use a different username.');
		
		$user_account_id = db_scrub($user_account_id, 'int');
		if($user_name) $user_name = db_scrub($user_name);
		if($email) $email = db_scrub($email);
		if($username) $username = db_scrub($username);
		if($password) $password = db_scrub($password);
		
		$username = strtolower($username);
		
		if( $user_name || $username || $password ){
			if($user_name) $sets[] = "user_name='{$user_name}'";
			if($email) $sets[] = "email='{$email}'";
			if($username) $sets[] = "username='{$username}'";
			if($password) $sets[] = "password='{$password}'";
			
			$sql  = "UPDATE {$this->db_customer}.customer_user_accounts SET ".implode(', ',$sets)." WHERE user_account_id='{$user_account_id}'";
			$update = mysql_query($sql) or my_mysql_error($sql);
			return ($update) ? true : false;
		} else {
			return false;
		}
	}
	
	public function delete_user( $user_account_id ){
		$user_account_id = db_scrub($user_account_id, 'int');
		$sql = "DELETE FROM {$this->db_customer}.customer_user_accounts WHERE user_account_id='{$user_account_id}' LIMIT 1";
		$delete1 = mysql_query($sql) or my_mysql_error($sql);
		$sql = "DELETE FROM {$this->db_customer}.customer_user_accounts_to_customers WHERE user_account_id='{$user_account_id}' LIMIT 1";
		$delete2 = mysql_query($sql) or my_mysql_error($sql);
		return ($delete1 && $delete2) ? true : false;
	}
	
	public function add_user( $customer_id, $user_name, $email, $username, $password ){
		if( $username && $this->username_exists($username, $user_account_id) ) die('This username already exists for another user. Please use a different username.');
		$customer_id = db_scrub($customer_id, 'int');
		$user_name = db_scrub($user_name);
		$email = db_scrub($email);
		$username = db_scrub($username);
		$password = db_scrub($password);
		
		$email = strtolower($email);
		$username = strtolower($username);
		
		$sql = "INSERT INTO {$this->db_customer}.customer_user_accounts VALUES( NULL, '{$user_name}', '{$email}', '{$username}', '{$password}', NOW(), DEFAULT, DEFAULT, '' );";
		$add1 = mysql_query($sql) or my_mysql_error($sql);
		if( $add1 ){
			$user_account_id = mysql_insert_id();
			$sql = "INSERT INTO {$this->db_customer}.customer_user_accounts_to_customers VALUES( '{$customer_id}', '{$user_account_id}' );";
			$add2 = mysql_query($sql) or my_mysql_error($sql);
			return ($add1 && $add2) ? true : false;
		} else {
			return false;
		}
	}
	
	public function username_exists( $username, $user_account_id=false ){
		$username = db_scrub($username);
		if($user_account_id) $user_account_id = db_scrub($user_account_id, 'int');
		$sql = "SELECT username FROM {$this->db_customer}.customer_user_accounts WHERE username='{$username}'";
		if($user_account_id) $sql .= " AND user_account_id!='{$user_account_id}'";
		$exists = mysql_query($sql) or my_mysql_error($sql);
		return ($exists && mysql_num_rows($exists)>0) ? true : false;
	}
}
?>