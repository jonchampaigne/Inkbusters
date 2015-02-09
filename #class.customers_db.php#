<?php
class Customers_DB extends Customers {
	private $customer_db_name = DB_DATABASE_CUSTOMERS;	// Is not effected by invoice mode
	private $invoice_db_name = DB_DATABASE_INVOICES;	// Is not effected by invoice mode
	protected $db_name = DB_DATABASE_CUSTOMERS;
	protected $test_mode = false;
	protected $invoice_mode = false;
	protected $session_id;
	protected $user_id;
	protected $updated;
	
	
	## __CONSTRUCT
	function __construct( $user_id ){
		if(!$user_id) die('User identification required to access Customer Database.');
		$this->user_id = $user_id;
		$this->session_id = session_id();
		return (db_connect('customers') && !empty($this->db_name)) ? true : false;
	}
	
	
	## __DESTRUCT
	function __destruct() {
		if( !empty($this->updated) ){
			$sql = "UPDATE {$this->db_name}.customers SET date_updated = NOW() WHERE customer_id = {$this->updated}";
			if($this->test_mode){ print_r('<pre style="color: #00aa00;">'.$sql.'</pre>'); }
			else{ mysql_query($sql) or die( hurl("Customers_DB Class Destruct Error:\r\n".mysql_error()."\r\nLine: ".__LINE__)); }
		}
	}
	

	## _TEST_MODE
	function _test_mode() {
		return (bool)$this->test_mode;
	}
	
	
	function _set_invoice_mode(){
		if($this->invoice_db_name){
			$this->db_name = $this->invoice_db_name;
			$this->invoice_mode = true;
			return true;
		} else {
			return false;
		}
	}
	
	
	## CREATE LOG
	/**
	  * Logs change to customer account tables in the database
	  * $table			string		Name of the effected database table
	  * $id				int/false	ID of the effected row in $table
	  * $type			string		Query type (INSERT, UPDATE, REPLACE, DELETE)
	  *	$data			string		Copy of the executed query
	  *	$description	string		Human readable descriptiong of the event
	  **/
	private function create_log( $table, $id=false, $type, $data, $description='' ){
		if( !$this->invoice_mode ){
			$data = db_scrub(str_replace( array("\n\t","\t"), array("\n",' '), $data));
			$log_sql = "INSERT INTO {$this->db_name}.change_logs VALUES( '', '{$this->user_id}', '{$_SERVER['REMOTE_ADDR']}', '{$this->session_id}', NOW(), '{$table}', '{$id}', '{$type}', '{$data}', '{$description}' );";
			if($this->test_mode){ print_r('<pre style="color: #6666ff;">'.$log_sql.'</pre><hr />'); }
			else{ mysql_query($log_sql) or my_mysql_error($log_sql); }
		}
	}
	
	
	## VERIFY OWNERSHIP
	/**
	  * Verifies that the supplied user owns the supplied customer account
	  * $customer_id	int			ID of the customer account
	  * $user_id		int			ID of the user account
	  **/
	public function verify_ownership( $customer_id=false, $user_id=false, $proposal_id=false ){
		if( !$customer_id || !$user_id ){
			return false;
		} else {
			$user_id = db_scrub($user_id, 'int');
			$customer_id = db_scrub($customer_id, 'int');
			if($proposal_id) $proposal_id = db_scrub($proposal_id, 'int');
			
			$sql  = "SELECT cu.customer_id FROM {$this->customer_db_name}.customers_to_users cu ";
			if($proposal_id) $sql .= "INNER JOIN {$this->customer_db_name}.customer_proposals cp ON cu.customer_id = cp.customer_id ";
			$sql .= "WHERE cu.customer_id = {$customer_id} AND cu.user_id = {$user_id} ";
			if($proposal_id) $sql .= "AND cp.proposal_id = {$proposal_id} ";
			$sql .= "LIMIT 1";
			$check_ownership = mysql_query($sql) or my_mysql_error($sql);
			return ( !$check_ownership || mysql_num_rows($check_ownership)<=0 || mysql_result($check_ownership, 0, 'customer_id')!=$customer_id ) ? false : true;
		}
	}
	
	
	## CREATE CUSTOMER
	/**
	  * Creates a new customer account and associates it with the user account
	  * $customer_id	int			ID of the customer account
	  * $user_id		int			ID of the user account
	  **/
	public function create_customer( $customer_name, $date_added, $user_id, $from_customer=false ){
		if(!$customer_name) die("Customer Name Required: ".__LINE__);
		if( empty($customer_name) || empty($user_id) ){
			return false;
		} else {
			$user_id = db_scrub($user_id, 'int');
			$customer_name = db_scrub(stripslashes($customer_name));
			
			##	Changed to always use NOW() on 07/07/08
			##	$date_added = (!strtotime($date_added)) ? 'NOW()' : ("'".date('Y-m-d h:i:s', strtotime($date_added))."'");
			$date_added = 'NOW()';
			
			$sql = "INSERT INTO {$this->db_name}.customers VALUES( '', '{$customer_name}', 1, {$date_added}, NOW() );";
			
			if($this->test_mode){ print_r('<pre>'.$sql.'</pre><hr />'); }
			else{ mysql_query($sql) or my_mysql_error($sql); }
			$new_customer = (!$this->test_mode) ? mysql_insert_id() : 123456;
			$this->create_log( ($this->db_name.'.customers'), $new_customer, 'INSERT', $sql, 'Customer account created.' );
			
			if( $new_customer && !$this->invoice_mode ){
				## ASSOCIATE CUSTOMER ACCOUNT WITH USER
				$sql = "INSERT INTO {$this->db_name}.customers_to_users VALUES( '{$new_customer}', '{$user_id}' );";
				if($this->test_mode){ print_r('<pre>'.$sql.'</pre><hr />'); }
				else{ mysql_query($sql) or my_mysql_error($sql); }
				$this->create_log( ($this->db_name.'.customers_to_users'), $new_customer, 'INSERT', $sql, 'Customer associated with user.' );
				$this->updated = $new_customer;
			} elseif( $new_customer && $this->invoice_mode ){
				## ASSOCIATE INVOICE CUSTOMER ACCOUNT WITH A CUSTOMER ACCOUNT
				$from_customer = db_scrub($from_customer, 'int');
				$sql = "INSERT INTO {$this->db_name}.customers_to_customers VALUES( '{$new_customer}', '{$from_customer}' );";
				if($this->test_mode){ print_r('<pre>'.$sql.'</pre><hr />'); }
				else{ mysql_query($sql) or my_mysql_error($sql); }
				$this->updated = $new_customer;
			}
			
			return $new_customer;
		}
	}
	
	
	## UPDATE CUSTOMER
	/**
	  * Updates the customer name
	  * $customer_id	int			ID of the customer account
	  * $customer_name	string		Customer's name
	  **/
	public function update_customer( $customer_id, $customer_name, $date_added ){
		if(!$customer_id) die("Customer ID Required: ".__LINE__);
		$customer_id = db_scrub(stripslashes($customer_id), 'int');
		
		$date_added = (!strtotime($date_added)) ? false : ("'".date('Y-m-d h:i:s', strtotime($date_added))."'");
		$sql = "SELECT customer_name FROM {$this->db_name}.customers WHERE customer_id = {$customer_id} LIMIT 1";
		$check_existing = mysql_query($sql) or my_mysql_error($sql);
		
		if( $check_existing && mysql_num_rows($check_existing)>=1 && $row = mysql_fetch_object($check_existing) && !empty($customer_name) ){
			switch(true){
				case stripslashes($row->customer_name) != stripslashes($customer_name):
				case $date_added!=false && $row->customer_name != $date_added:
					$customer_name = db_scrub(stripslashes($customer_name));
					$date_added = (!$date_added) ? '' : ", date_added = {$date_added}";
					$sql = "UPDATE {$this->db_name}.customers SET customer_name = '{$customer_name}'{$date_added} WHERE customer_id = {$customer_id}";
					
					if($this->test_mode){ print_r('<pre>'.$sql.'</pre><hr />'); }
					else{ mysql_query($sql) or my_mysql_error($sql); }
					$this->create_log( ($this->db_name.'.customers'), $customer_id, 'UPDATE', $sql, 'Customer name updated.' );
					$this->updated = $customer_id;
					
					return true;
					break;
				default:
					return false;
					break;
			}
		} else {
			return false;
		}
	}
	
	
	## PUT PROPOSAL
	/**
	  * Adds or updates customer proposal status and information. Proposal is ignored if unchanged.
	  * $customer_id		int/false	ID of the customer account
	  * $proposal_id		int/false	ID of the customer proposal
	  * $date				date		Customer Proposal Date
	  * $customer_comment	string		Customer Proposal's Customer Comment
	  * $private_comment	string		Customer Proposal's Private Comment
	  **/
	public function put_proposal( $customer_id, $proposal_id=false, $date, $customer_comment, $private_comment ){
		if(!$customer_id) die("Customer ID Required: ".__LINE__);
		$customer_id = db_scrub(stripslashes($customer_id), 'int');
		$proposal_date = (!strtotime($date)) ? false : date('Y-m-d h:i:s', strtotime($date));
		
		if( $proposal_id && ctype_digit( (string)$proposal_id ) ){
			$proposal_id = db_scrub(stripslashes($proposal_id), 'int');
			$sql = "SELECT proposal_date, customer_comment, private_comment FROM {$this->db_name}.customer_proposals WHERE customer_id = {$customer_id} AND proposal_id = {$proposal_id} LIMIT 1";
			$check_existing = mysql_query($sql) or my_mysql_error($sql);
			
			if( !$check_existing || mysql_num_rows($check_existing)<=0 ){
				$query_mode = 'new';
			} else {
				$row = mysql_fetch_object($check_existing);
				switch(true){
					case $row->proposal_date !== $proposal_date:
					case stripslashes($row->customer_comment) !== $customer_comment:
					case stripslashes($row->private_comment) !== $private_comment:
						$query_mode = 'update';	break;
					default:
						$query_mode = 'ignore';	break;
				}
			}
		} else { $query_mode = 'new'; }
		
		$proposal_date = (!$proposal_date) ? 'NOW()' : "'{$proposal_date}'";
		$customer_comment = db_scrub(stripslashes($customer_comment));
		$private_comment = db_scrub(stripslashes($private_comment));
		
		if( $query_mode == 'ignore' ){
			return false;
		} elseif( $query_mode == 'update' ){
			$sql = <<<HEREDOC
UPDATE {$this->db_name}.customer_proposals
SET proposal_date = {$proposal_date},
	customer_comment = '{$customer_comment}',
	private_comment = '{$private_comment}'
WHERE proposal_id = {$proposal_id}
LIMIT 1
HEREDOC;
			if($this->test_mode){ print_r('<pre>'.$sql.'</pre><hr />');
			}else{ mysql_query($sql) or my_mysql_error($sql); }
			$this_proposal_id = $proposal_id;
			$this->create_log( ($this->db_name.'.customer_proposals'), $proposal_id, 'UPDATE', $sql, 'Customer proposal updated.' );
			
		} elseif( $query_mode == 'new' ){
			$sql = <<<HEREDOC
INSERT INTO {$this->db_name}.customer_proposals
VALUES(
	'{$customer_id}',
	'',
	{$proposal_date},
	'{$customer_comment}',
	'{$private_comment}',
	0
);
HEREDOC;
			if($this->test_mode){ print_r('<pre>'.$sql.'</pre><hr />');
			}else{ mysql_query($sql) or my_mysql_error($sql); }
			$this_proposal_id = (!$this->test_mode)? mysql_insert_id() : rand(1,9999);
			$this->create_log( ($this->db_name.'.customer_proposals'), $this_proposal_id, 'INSERT', $sql, 'Customer proposal created.' );
		}
		
		$this->updated = $this_proposal_id;
		return $this_proposal_id;
	}
	
	
	## GET CONTACTS
	/**
	  * Gets all contacts from a customer account ($contact_id=false)
	    or gets a single contact from a customer account
	  * $customer_id	int			ID of the customer account
	  * $contact_id		int/false	ID of the customer contact
	  * $limit			int/false	Limit the number of returned rows		
	  **/
	public function get_contacts( $customer_id, $contact_id=false, $limit=false ){
		if(!$customer_id) die("Customer ID Required: ".__LINE__);
		$customer_id = db_scrub($customer_id, 'int');
		if($contact_id) $contact_id = db_scrub($contact_id, 'int');
		if($limit) $limit = db_scrub($limit, 'int');
		
		$sql = "SELECT * FROM {$this->db_name}.customer_contacts WHERE customer_id = {$customer_id}";
		if($contact_id) $sql .= " AND contact_id = {$contact_id} LIMIT 1";
		if(!$contact_id && $limit) $sql .= " LIMIT {$limit}";
		
		$contacts = mysql_query($sql) or my_mysql_error($sql);
		return ($contacts) ? $contacts : false;
	}
	
	
	## PUT CONTACTS
	/**
	  * Adds or updates customer contact. Contact is ignored if existent and unchanged
	  * $customer_id	int/false	ID of the customer account
	  * $id				int/false	ID of the customer contact
	  * $first_name		string		Contact's first name
	  * $last_name		string		Contact's last name
	  * $email			string		Contact's email address
	  * $mobile			string		Contact's mobile phone number
	  * $phone			string		Contact's phone number
	  * $fax			string		Contact's fax number
	  **/
	public function put_contact( $customer_id, $id=false, $first_name, $last_name, $email='', $mobile='', $phone='', $fax='' ){
		if(!$customer_id) die("Customer ID Required: ".__LINE__);
		$customer_id = db_scrub(stripslashes($customer_id), 'int');
		
		if( $id && ctype_digit( (string)$id ) ){
			$id = db_scrub(stripslashes($id), 'int');
			$check_existing = $this->get_contacts( $customer_id, $id );
			
			if( !$check_existing || mysql_num_rows($check_existing)<=0 ){
				$query_mode = 'new';
			} else {
				$row = mysql_fetch_object($check_existing);
				switch(true){
					case stripslashes($row->first_name) !== $first_name:
					case stripslashes($row->last_name) !== $last_name:
					case stripslashes($row->email) !== $email:
					case stripslashes($row->mobile) !== $mobile:
					case stripslashes($row->phone) !== $phone:
					case stripslashes($row->fax) !== $fax:
						$query_mode = 'update';	break;
					default:
						$query_mode = 'ignore';	break;
				}
			}
		} else { $query_mode = 'new'; }
		
		$first_name = db_scrub(stripslashes($first_name));
		$last_name = db_scrub(stripslashes($last_name));
		$email = db_scrub(stripslashes($email));
		$mobile = db_scrub(stripslashes($mobile));
		$phone = db_scrub(stripslashes($phone));
		$fax = db_scrub(stripslashes($fax));
		
		if( $query_mode == 'ignore' ){
			return false;
		} elseif( $query_mode == 'update' ){
			$sql = <<<HEREDOC
UPDATE {$this->db_name}.customer_contacts
SET first_name = '{$first_name}',
	last_name = '{$last_name}',
	email = '{$email}',
	mobile = '{$mobile}',
	phone = '{$phone}',
	fax = '{$fax}'
WHERE contact_id = {$id}
LIMIT 1
HEREDOC;
			if($this->test_mode){ print_r('<pre>'.$sql.'</pre><hr />');
			}else{ mysql_query($sql) or my_mysql_error($sql); }
			$this_contact_id = $id;
			$this->create_log( ($this->db_name.'.customer_contacts'), $id, 'UPDATE', $sql, 'Customer contact updated.' );
			
		} elseif( $query_mode == 'new' ){
			$sql = <<<HEREDOC
INSERT INTO {$this->db_name}.customer_contacts
VALUES(
	'{$customer_id}',
	'',
	'{$first_name}',
	'{$last_name}',
	'{$email}',
	'{$mobile}',
	'{$phone}',
	'{$fax}'
);
HEREDOC;
			if($this->test_mode){ print_r('<pre>'.$sql.'</pre><hr />');
			}else{ mysql_query($sql) or my_mysql_error($sql); }
			$this_contact_id = (!$this->test_mode)? mysql_insert_id() : rand(1,9999);
			$this->create_log( ($this->db_name.'.customer_contacts'), $this_contact_id, 'INSERT', $sql, 'Customer contact added.' );
		}
		
		$this->updated = $customer_id;
		return $this_contact_id;
	}
	
	
	## DELETE CONTACTS
	/**
	  * Removes a contact from a customer account
	  * $customer_id	int			ID of the customer account
	  * $contact_id		int/false	ID of the customer contact
	  * $limit			int/false	Limit the number of returned rows		
	  **/
	public function delete_contacts( $customer_id, $contact_id=false, $limit=false ){
		if(!$customer_id) die("Customer ID Required: ".__LINE__);
		$customer_id = db_scrub($customer_id, 'int');
		if($contact_id) $contact_id = db_scrub($contact_id, 'int');
		if($limit) $limit = db_scrub($limit, 'int');
		
		$sql = "DELETE FROM {$this->db_name}.customer_contacts WHERE customer_id = {$customer_id}";
		if($contact_id) $sql .= " AND contact_id = {$contact_id} LIMIT 1";
		if(!$contact_id && $limit) $sql .= " LIMIT {$limit}";
		
		if($this->test_mode){ print_r('<pre>'.$sql.'</pre><hr />'); }
		else{ mysql_query($sql) or my_mysql_error($sql); }
		$this->create_log( ($this->db_name.'.customer_contacts'), $contact_id, 'DELETE', $sql, 'Customer contact(s) deleted.' );
		
		$this->updated = $customer_id;
		return;
	}
	
	
	## GET ADDRESSES
	/**
	  * Gets all addresses from a customer account ($contact_id=false)
	    or gets a single address from a customer account
	  * $customer_id	int			ID of the customer account
	  * $address_id		int/false	ID of the customer address
	  * $limit			int/false	Limit the number of returned rows		
	  **/
	public function get_addresses( $customer_id, $address_id=false, $limit=false ){
		if(!$customer_id) die("Customer ID Required: ".__LINE__);
		$customer_id = db_scrub($customer_id, 'int');
		if($address_id) $address_id = db_scrub($address_id, 'int');
		if($limit) $limit = db_scrub($limit, 'int');
		
		$sql = "SELECT * FROM {$this->db_name}.customer_addresses WHERE customer_id = {$customer_id}";
		if($address_id) $sql .= " AND address_id = {$address_id} LIMIT 1";
		if(!$address_id && $limit) $sql .= " LIMIT {$limit}";
		
		$addresses = mysql_query($sql) or my_mysql_error($sql);
		return ($addresses) ? $addresses : false;
	}
	
	
	## PUT ADDRESSES
	/**
	  * Adds or updates customer address. Address is ignored if existent and unchanged
	  * $customer_id	int/false	ID of the customer account
	  * $id				int/false	ID of the customer contact
	  * $address_one	string		First line of the address
	  * $address_two	string		Second line of the address
	  * $city			string		City
	  * $state			string		State
	  * $zip			string		ZIP code
	  **/
	public function put_address( $customer_id, $id=false, $address_type_id, $address_one, $address_two='', $city, $state, $zip ){
		if(!$customer_id) die("Customer ID Required: ".__LINE__);
		$customer_id = db_scrub(stripslashes($customer_id), 'int');
		
		if( $id && ctype_digit( (string)$id ) ){
			$id = db_scrub(stripslashes($id), 'int');
			$check_existing = $this->get_addresses( $customer_id, $id );
			if( !$check_existing || mysql_num_rows($check_existing)<=0 ){
				$query_mode = 'new';
			} else {
				$row = mysql_fetch_object($check_existing);
				switch(true){
					case stripslashes($row->address_one) !== $address_one:
					case stripslashes($row->address_two) !== $address_two:
					case stripslashes($row->city) !== $city:
					case stripslashes($row->state) !== $state:
					case stripslashes($row->zip) !== $zip:
						$query_mode = 'update';	break;
					default:
						$query_mode = 'ignore';	break;
				}
			}
		} else { $query_mode = 'new'; }
		
		$address_type_id = db_scrub(stripslashes($address_type_id), 'int');
		$address_one = db_scrub(stripslashes($address_one));
		$address_two = db_scrub(stripslashes($address_two));
		$city = db_scrub(stripslashes($city));
		$state = db_scrub(stripslashes($state));
		$zip = db_scrub(stripslashes($zip));
		
		if( $query_mode == 'ignore' ){
			return false;
		} elseif( $query_mode == 'update' ){
			$sql = <<<HEREDOC
UPDATE {$this->db_name}.customer_addresses
SET address_one = '{$address_one}',
	address_two = '{$address_two}',
	city = '{$city}',
	state = '{$state}',
	zip = '{$zip}'
WHERE address_id = {$id}
LIMIT 1
HEREDOC;
			if($this->test_mode){ print_r('<pre>'.$sql.'</pre><hr />');
			}else{ mysql_query($sql) or my_mysql_error($sql); }
			$this_address_id = $id;
			$this->create_log( ($this->db_name.'.customer_addresses'), $id, 'UPDATE', $sql, 'Customer address updated.' );
		} elseif( $query_mode == 'new' ){
			$sql = <<<HEREDOC
INSERT INTO {$this->db_name}.customer_addresses
VALUES(
	'{$customer_id}',
	'',
	'{$address_type_id}',
	'{$address_one}',
	'{$address_two}',
	'{$city}',
	'{$state}',
	'{$zip}'
);
HEREDOC;
			if($this->test_mode){ print_r('<pre>'.$sql.'</pre><hr />');
			}else{ mysql_query($sql) or my_mysql_error($sql); }
			$this_address_id = (!$this->test_mode)? mysql_insert_id() : rand(1,9999);
			$this->create_log( ($this->db_name.'.customer_addresses'), $this_contact_id, 'INSERT', $sql, 'Customer address added.' );
		}
		
		$this->updated = $customer_id;
		return $this_address_id;
	}
	
	
	## DELETE ADDRESSES
	/**
	  * Removes an address from a customer account
	  * $customer_id	int			ID of the customer account
	  * $address_id		int/false	ID of the customer address
	  * $limit			int/false	Limit the number of returned rows		
	  **/
	public function delete_addresses( $customer_id, $address_id=false, $limit=false ){
		if(!$customer_id) die("Customer ID Required: ".__LINE__);
		$customer_id = db_scrub($customer_id, 'int');
		if($address_id) $address_id = db_scrub($address_id, 'int');
		if($limit) $limit = db_scrub($limit, 'int');
		
		$sql = "DELETE FROM {$this->db_name}.customer_addresses WHERE customer_id = {$customer_id}";
		if($address_id) $sql .= " AND address_id = {$address_id} LIMIT 1";
		if(!$address_id && $limit) $sql .= " LIMIT {$limit}";
		
		if($this->test_mode){ print_r('<pre>'.$sql.'</pre><hr />'); }
		else{ mysql_query($sql) or my_mysql_error($sql); }
		$this->create_log( ($this->db_name.'.customer_addresses'), $address_id, 'DELETE', $sql, 'Customer address(es) deleted.' );
		
		$this->updated = $customer_id;
		return;
	}
	
	
	## GET PRITNERS
	/**
	  * Gets all printers from a customer account ($printer_id=false)
	    or gets a single printer from a customer account
	  * $customer_id	int			ID of the customer account
	  * $printer_id		int/false	ID of the customer printer
	  * $limit			int/false	Limit the number of returned rows		
	  **/
	public function get_printers( $customer_id, $printer_id=false, $limit=false ){
		if(!$customer_id) die("Customer ID Required: ".__LINE__);
		$customer_id = db_scrub($customer_id, 'int');
		if($printer_id) $printer_id = db_scrub($printer_id, 'int');
		if($limit) $limit = db_scrub($limit, 'int');
		
		$sql = "SELECT * FROM {$this->db_name}.printers WHERE customer_id = {$customer_id}";
		if($printer_id) $sql .= " AND printer_id = {$printer_id} LIMIT 1";
		if(!$printer_id && $limit) $sql .= " LIMIT {$limit}";
		
		$printers = mysql_query($sql) or my_mysql_error($sql);
		return ($printers) ? $printers : false;
	}
	
	
	## PUT PRINTERS
	/**
	  * Adds or updates customer printers. Address is ignored if existent and unchanged
	  * $customer_id	int/false	ID of the customer account
	  * $id				int/false	ID of the customer printer
	  * $printer_name	string		Name of the printer
	  * $printer_qty	string		Number of this printer the customer has
	  **/
	public function put_printers( $customer_id, $id=false, $printer_name, $printer_qty=1, $printer_location=false ){
		if(!$customer_id) die("Customer ID Required: ".__LINE__);
		$customer_id = db_scrub(stripslashes($customer_id), 'int');

		if( $id && ctype_digit( (string)$id ) ){
			$id = db_scrub(stripslashes($id));
			$check_existing = $this->get_printers( $customer_id, $id );
			if( !$check_existing || mysql_num_rows($check_existing)<=0 ){
				$query_mode = 'new';
			} else {
				$row = mysql_fetch_object($check_existing);
				switch(true){
					case stripslashes($row->printer) !== $printer_name:
					case stripslashes($row->printer_qty) !== $printer_qty:
					case stripslashes($row->printer_location) != $printer_location:	// Non-Exact comparison so that NULL is the same as empty string
						$query_mode = 'update';	break;
					default:
						$query_mode = 'ignore';	break;
				}
			}
		} else { $query_mode = 'new'; }
		
		$printer_name = db_scrub(stripslashes($printer_name));
		$printer_qty = db_scrub(stripslashes($printer_qty), 'int');
		$printer_location = db_scrub(stripslashes($printer_location));
		
		if( $query_mode == 'ignore' )
		{
			return $id;
		}
		elseif ( $query_mode == 'update' )
		{
			$sql = <<<HEREDOC
UPDATE {$this->db_name}.printers
SET printer = '{$printer_name}',
	printer_qty = '{$printer_qty}',
	printer_location = '{$printer_location}'
WHERE printer_id = {$id}
LIMIT 1
HEREDOC;
			if ($this->test_mode)
			{
				print_r('<pre>'.$sql.'</pre><hr />');
			}
			else
			{
				/* send_email_alert($msg, $subject, $priority)
				send_email_alert('Saving Printer, query mode == update.'
					. "\n"
					. "\n" . 'SQL: ' . $sql
					. "\n" . 'File: ' . __FILE__
					. "\n" . 'Line: ' . __LINE__
					. "\n" . 'Request URI: ' . $_SERVER['REQUEST_URI']
					,
					'Saving Printer [query mode == update]'
					,
					false
				);
				*/
				mysql_query($sql) or my_mysql_error($sql);
			}
			$this_printer_id = $id;
			$this->create_log( ($this->db_name.'.printers'), $id, 'UPDATE', $sql, 'Customer printer updated.' );
		}
		elseif ( $query_mode == 'new' )
		{
			$sql = <<<HEREDOC
INSERT INTO {$this->db_name}.printers
VALUES(
	'{$customer_id}',
	'',
	'{$printer_name}',
	'{$printer_qty}',
	'{$printer_location}'
);
HEREDOC;
			if($this->test_mode)
			{
				print_r('<pre>'.$sql.'</pre><hr />');
			}
			else
			{
				/*
				send_email_alert('Saving Printer, query mode == new.'
					. "\n"
					. "\n" . 'SQL: ' . $sql
					. "\n" . 'File: ' . __FILE__
					. "\n" . 'Line: ' . __LINE__
					. "\n" . 'Request URI: ' . $_SERVER['REQUEST_URI']
					,
					'Saving Printer [query mode == new]'
					,
					false
				);
				*/
				mysql_query($sql) or my_mysql_error($sql);
			}
			$this_printer_id = (!$this->test_mode)? mysql_insert_id() : rand(1,9999);
			$this->create_log( ($this->db_name.'.printers'), $this_printer_id, 'INSERT', $sql, 'Customer printer added.' );
		}
		
		$this->updated = $customer_id;
		return ($this_printer_id) ? $this_printer_id : $id;
	}
	
	
	## DELETE PRINTERS (AND CHILDREN)
	/**
	  * Removes a printer from a customer account
	    Also removes all associations to manufacturers and types
		Also removes all associated cartridges
		$this->delete_cartridges then removes all cartridge associations  to manufacturers and cartridges types
	  * $customer_id	int			ID of the customer account
	  * $printer_id		int/false	ID of the customer printers
	  * $limit			int/false	Limit the number of returned rows		
	  **/
	public function delete_printers( $customer_id, $printer_id=false, $limit=false ){
		if(!$customer_id) die("Customer ID Required: ".__LINE__);
		$customer_id = db_scrub($customer_id, 'int');
		if($printer_id) $printer_id = db_scrub($printer_id, 'int');
		if($limit) $limit = db_scrub($limit, 'int');
		
		$sql = "DELETE FROM {$this->db_name}.printers WHERE customer_id = {$customer_id}";
		if($printer_id) $sql .= " AND printer_id = {$printer_id} LIMIT 1";
		if(!$printer_id && $limit) $sql .= " LIMIT {$limit}";
		
		if($this->test_mode){ print_r('<pre>'.$sql.'</pre><hr />'); }
		else{ mysql_query($sql) or my_mysql_error($sql); }
		$this->create_log( ($this->db_name.'.printers'), $printer_id, 'DELETE', $sql, 'Customer printer(s) deleted.' );
		
		## DELETE PRINTER TYPE AND MANUFACTURER ASSOCIATIONS
		if( $printer_id ){
			$this->unassoc_printers_manufacturers( $customer_id, false, $printer_id, true );
			$this->unassoc_printers_types( $customer_id, false, $printer_id, true );
			$this->delete_cartridges( $customer_id, false, false, $printer_id, true );
		}
		
		$this->updated = $customer_id;
		return;
	}
	
	
	## PUT MANUFACTURERS
	/**
	  * Adds manufacturer if not existent for customer account
	  * $customer_id		int/false	ID of the customer account
	  * $manufacturer_name	string		Manufacturer name
	  **/
	public function put_manufacturers( $customer_id, $manufacturer_name ){
		if(!$customer_id) die("Customer ID Required: ".__LINE__);
		$customer_id = db_scrub(stripslashes($customer_id), 'int');
		$manufacturer_name = db_scrub(stripslashes($manufacturer_name));
		
		$sql = "SELECT * FROM {$this->db_name}.manufacturers WHERE customer_id = {$customer_id} AND manufacturer_name = '{$manufacturer_name}' LIMIT 1";
		$check_existing = mysql_query($sql) or my_mysql_error($sql);
		
		if( !$check_existing || mysql_num_rows($check_existing)<=0 ){
			$sql = "INSERT INTO {$this->db_name}.manufacturers VALUES( '{$customer_id}', '', '{$manufacturer_name}' );";
			
			if($this->test_mode){ print_r('<pre>'.$sql.'</pre><hr />');
			}else{ mysql_query($sql) or my_mysql_error($sql); }
			$manufacturer_id = (!$this->test_mode)? mysql_insert_id() : rand(1,9999);
			$this->create_log( ($this->db_name.'.manufacturers'), $manufacturer_id, 'INSERT', $sql, 'Customer manufacturer added.' );
			
			$this->updated = $customer_id;
			return $manufacturer_id;
		} else {
			return mysql_result($check_existing, 0, 'manufacturer_id');
		}
	}
	
	
	## GET ASSOCIATED PRITNER MANUFACTURERS
	/**
	  * Gets all manufacturers from a printer ($manufacturer_id=false)
	    or gets a single manufacturer ($printer_id=false)
	  * $customer_id		int			ID of the customer account
	  * $printer_id			int/false	ID of the customer printer
	  * $manufacturer_id	int/false	ID of the customer manufacturer
	  * $limit				int/false	Limit the number of returned rows		
	  **/
	public function get_assoc_printers_manufacturers( $customer_id, $printer_id=false, $manufacturer_id=false, $limit=false ){
		if(!$customer_id) die("Customer ID Required: ".__LINE__);
		$customer_id = db_scrub($customer_id, 'int');
		if($printer_id) $printer_id = db_scrub($printer_id, 'int');
		if($manufacturer_id) $manufacturer_id = db_scrub($manufacturer_id, 'int');
		if($limit) $limit = db_scrub($limit, 'int');
		
		$sql = "SELECT pc.*, m.manufacturer_name FROM {$this->db_name}.printers_to_manufacturers pc LEFT JOIN {$this->db_name}.manufacturers m ON pc.manufacturer_id = m.manufacturer_id WHERE pc.customer_id = {$customer_id}";
		if($printer_id) $sql .= " AND pc.printer_id = {$printer_id}";
		if($manufacturer_id) $sql .= " AND pc.manufacturer_id = '{$manufacturer_id}' LIMIT 1";
		if(!$manufacturer_id && $limit) $sql .= " LIMIT {$limit}";
		
		$manufacturers = mysql_query($sql) or my_mysql_error($sql);
		return ($manufacturers) ? $manufacturers : false;
	}
	

	## ASSOCIATE PRITNERS AND MANUFACTURERS
	/**
	  * Associates a printer with a manufacturer
	  * $customer_id		int			ID of the customer account
	  * $manufacturer_id	int			ID of the customer manufacturer
	  * $printer_id			int			ID of the customer printer
	  **/
	public function assoc_printers_manufacturers( $customer_id, $manufacturer_id, $printer_id ){
		if(!$customer_id) die("Customer ID Required: ".__LINE__);
		$customer_id = db_scrub(stripslashes($customer_id), 'int');
		$manufacturer_id = db_scrub(stripslashes($manufacturer_id), 'int');
		$printer_id = db_scrub(stripslashes($printer_id), 'int');
		
		if( empty($customer_id) || empty($manufacturer_id) || empty($printer_id) ) return false;
		
		$check_existing = $this->get_assoc_printers_manufacturers( $customer_id, $printer_id, $manufacturer_id );
		if( !$check_existing || mysql_num_rows($check_existing)<=0 ){
			$sql = "INSERT INTO {$this->db_name}.printers_to_manufacturers VALUES( '{$customer_id}', '{$manufacturer_id}', '{$printer_id}' );";
			
			if($this->test_mode){ print_r('<pre>'.$sql.'</pre><hr />'); }
			else{ mysql_query($sql) or my_mysql_error($sql); }
			$this->create_log( ($this->db_name.'.printers_to_manufacturers'), '', 'INSERT', $sql, 'Customer manufacturer associated with customer printer.' );
			$this->updated = $customer_id;
		}
		
		return true;
	}
	
	
	## UNASSOCIATE PRITNERS AND MANUFACTURERS
	/**
	  * Unassociates a printer with all of it's manufacturers ($manufacturer_id=false)
	    or unassociates a pritner with a single manufacturer
	  * $customer_id		int			ID of the customer account
	  * $manufacturer_id	int/false	ID of the customer manufacturer
	  * $printer_id			int			ID of the customer printer
	  * $from_printer		bool		If the function is being run from $this->delete_printers
	  **/
	public function unassoc_printers_manufacturers( $customer_id, $manufacturer_id=false, $printer_id, $from_printer=false ){
		if(!$customer_id) die("Customer ID Required: ".__LINE__);
		$customer_id = db_scrub(stripslashes($customer_id), 'int');
		if($manufacturer_id) $manufacturer_id = db_scrub(stripslashes($manufacturer_id), 'int');
		$printer_id = db_scrub(stripslashes($printer_id), 'int');
		
		$sql =  "DELETE FROM {$this->db_name}.printers_to_manufacturers WHERE customer_id = {$customer_id} AND printer_id = {$printer_id}";
		$sql .= ($manufacturer_id) ? " AND manufacturer_id = '{$manufacturer_id}' LIMIT 1" : " LIMIT 1";
		if($this->test_mode){ print_r('<pre>'.$sql.'</pre><hr />'); }
		else{ mysql_query($sql) or my_mysql_error($sql); }
		
		if( $from_printer ){
			$this->create_log( ($this->db_name.'.printers_to_manufacturers'), '', 'DELETE', $sql, 'Customer printer manufacturer(s) deleted. [From Printer Deletion]' );
		} else {
			$this->create_log( ($this->db_name.'.printers_to_manufacturers'), $manufacturer_id, 'DELETE', $sql, 'Customer printer manufacturer(s) deleted.' );
		}
		
		$this->updated = $customer_id;
		return true;
	}
	
	
	## PUT PRINTER TYPES
	/**
	  * Adds printer type if not existent for customer account
	  * $customer_id	int			ID of the customer account
	  * $type_name		string		Type name
	  **/
	public function put_printers_types( $customer_id, $type_name ){
		if(!$customer_id) die("Customer ID Required: ".__LINE__);
		$customer_id = db_scrub(stripslashes($customer_id), 'int');
		$type_name = db_scrub(stripslashes($type_name));
		
		$sql = "SELECT * FROM {$this->db_name}.printer_types WHERE customer_id = {$customer_id} AND printer_type = '{$type_name}' LIMIT 1";
		$check_existing = mysql_query($sql) or my_mysql_error($sql);
		
		if( !$check_existing || mysql_num_rows($check_existing)<=0 ){
			$sql = "INSERT INTO {$this->db_name}.printer_types VALUES( '{$customer_id}', '', '{$type_name}' );";
			
			if($this->test_mode){ print_r('<pre>'.$sql.'</pre><hr />');
			}else{ mysql_query($sql) or my_mysql_error($sql); }
			$type_id = (!$this->test_mode)? mysql_insert_id() : rand(1,9999);
			$this->create_log( ($this->db_name.'.printer_types'), $type_id, 'INSERT', $sql, 'Customer type added.' );
			
			$this->updated = $customer_id;
			return $type_id;
		} else {
			return mysql_result($check_existing, 0, 'type_id');
		}
	}
	
	
	## GET ASSOCIATED PRITNER TYPES
	/**
	  * Gets all types from a printer ($type_id=false)
	    or gets a single type ($printer_id=false)
	  * $customer_id	int			ID of the customer account
	  * $printer_id		int/false	ID of the customer printer
	  * $type_id		int/false	ID of the customer type
	  * $limit			int/false	Limit the number of returned rows		
	  **/
	public function get_assoc_printers_types( $customer_id, $printer_id=false, $type_id=false, $limit=false ){
		if(!$customer_id) die("Customer ID Required: ".__LINE__);
		$customer_id = db_scrub($customer_id, 'int');
		if($printer_id) $printer_id = db_scrub($printer_id, 'int');
		if($type_id) $type_id = db_scrub($type_id, 'int');
		if($limit) $limit = db_scrub($limit, 'int');
		
		$sql = "SELECT pt.*, t.printer_type FROM {$this->db_name}.printers_to_types pt LEFT JOIN {$this->db_name}.printer_types t ON pt.type_id = t.type_id WHERE pt.customer_id = {$customer_id}";
		if($printer_id) $sql .= " AND pt.printer_id = {$printer_id}";
		if($type_id) $sql .= " AND pt.type_id = '{$type_id}' LIMIT 1";
		if(!$type_id && $limit) $sql .= " LIMIT {$limit}";
		
		$types = mysql_query($sql) or my_mysql_error($sql);
		return ($types) ? $types : false;
	}
	
	
	## ASSOCIATE PRITNERS AND TYPES
	/**
	  * Associates a printer with a printer type
	  * $customer_id	int			ID of the customer account
	  * $type_id		int			ID of the customer printer type
	  * $printer_id		int			ID of the customer printer
	  **/
	public function assoc_printers_types( $customer_id, $type_id, $printer_id ){
		if(!$customer_id) die("Customer ID Required: ".__LINE__);
		$customer_id = db_scrub(stripslashes($customer_id), 'int');
		$type_id = db_scrub(stripslashes($type_id), 'int');
		$printer_id = db_scrub(stripslashes($printer_id), 'int');
		
		if( empty($customer_id) || empty($type_id) || empty($printer_id) ) return false;
		
		$check_existing = $this->get_assoc_printers_types( $customer_id, $printer_id, $type_id );
		if( !$check_existing || mysql_num_rows($check_existing)<=0 ){
			$sql = "INSERT INTO {$this->db_name}.printers_to_types VALUES( '{$customer_id}', '{$type_id}', '{$printer_id}' );";
			
			if($this->test_mode){ print_r('<pre>'.$sql.'</pre><hr />'); }
			else{ mysql_query($sql) or my_mysql_error($sql); }
			$this->create_log( ($this->db_name.'.printers_to_types'), '', 'INSERT', $sql, 'Customer printer type associated with customer printer.' );
			$this->updated = $customer_id;
		}
		
		return true;
	}
	
	
	## UNASSOCIATE PRITNERS AND TYPES
	/**
	  * Unassociates a printer with all of it's types ($type_id=false)
	    or unassociates a pritner with a single type
	  * $customer_id	int			ID of the customer account
	  * $type_id		int/false	ID of the customer type
	  * $printer_id		int			ID of the customer printer
	  * $from_printer	bool		If the function is being run from $this->delete_printers
	  **/
	public function unassoc_printers_types( $customer_id, $type_id=false, $printer_id, $from_printer=false ){
		if(!$customer_id) die("Customer ID Required: ".__LINE__);
		$customer_id = db_scrub(stripslashes($customer_id), 'int');
		if($type_id) $type_id = db_scrub(stripslashes($type_id), 'int');
		$printer_id = db_scrub(stripslashes($printer_id), 'int');
		
		$sql =  "DELETE FROM {$this->db_name}.printers_to_types WHERE customer_id = {$customer_id} AND printer_id = {$printer_id}";
		$sql .= ($type_id) ? " AND type_id = '{$type_id}' LIMIT 1" : " LIMIT 1";
		if($this->test_mode){ print_r('<pre>'.$sql.'</pre><hr />'); }
		else{ mysql_query($sql) or my_mysql_error($sql); }
		
		if( $from_printer ){
			$this->create_log( ($this->db_name.'.printers_to_types'), '', 'DELETE', $sql, 'Customer printer types(s) deleted. [From Printer Deletion]' );
		} else {
			$this->create_log( ($this->db_name.'.printers_to_types'), $type_id, 'DELETE', $sql, 'Customer printer types(s) deleted.' );
		}
		
		$this->updated = $customer_id;
		return true;
	}
	
	
	## GET CARTRIDGES
	/**
	  * Gets all cartridges from a customer account ($cartridge_id=false && $printer_id!=false)
	    or gets a single cartirdges from a customer account	($cartridge_id!=false && $printer_id=false)
		or gets all cartridges from a printer ($printer_id!=false && $cartridge_id!=false)
		or gets a single cartridge from a printer ($printer_id!=false && $cartridge_id=false)
	  * $customer_id	int			ID of the customer account
	  * $cartridge_id	int/false	ID of the customer cartridge
	  * $limit			int/false	Limit the number of returned rows
	  * $printer_id		int/false	ID of the customer pritner		
	  **/
	public function get_cartridges( $customer_id, $cartridge_id=false, $limit=false, $printer_id=false ){
		if(!$customer_id) die("Customer ID Required: ".__LINE__);
		$customer_id = db_scrub($customer_id, 'int');
		if($cartridge_id) $cartridge_id = db_scrub($cartridge_id, 'int');
		if($limit) $limit = db_scrub($limit, 'int');
		if($printer_id) $printer_id = db_scrub($printer_id, 'int');
		
		$sql = "SELECT c.* FROM {$this->db_name}.cartridges c";
		if( $printer_id ){
			$sql .= " LEFT JOIN {$this->db_name}.cartridges_to_printers cp ON c.cartridge_id = cp.cartridge_id WHERE c.customer_id = {$customer_id} AND cp.printer_id = '{$printer_id}'";
			if($cartridge_id) $sql .= " AND c.cartridge_id = {$cartridge_id} LIMIT 1";
		} else {
			$sql .= " WHERE c.customer_id = {$customer_id}";
			if($cartridge_id) $sql .= " AND c.cartridge_id = {$cartridge_id} LIMIT 1";
			if(!$cartridge_id && $limit) $sql .= " LIMIT {$limit}";
		}
		
		$cartridges = mysql_query($sql) or my_mysql_error($sql);
		return ($cartridges) ? $cartridges : false;
	}
	
	
	## PUT CARTRIDGES
	/**
	  * Adds or updates customer cartridges. Cartridge is ignored if existent and unchanged
	  * $customer_id	int/false	ID of the customer account
	  * $id				int/false	ID of the customer printer
	  * $cartridge_name	string		Name of the cartridge
	  * $shorthand		string		Shorthand (Company Number) for cartridge
	  * $price_retail	string/int	Retail cartridge price
	  * $price_user		string/int	User's custom cartridge price
	  **/
	public function put_cartridges( $customer_id, $cart, $printer_id=false ){
		if(!$customer_id) die("Customer ID Required: ".__LINE__);
		$customer_id = db_scrub(stripslashes($customer_id), 'int');
		
		$id = ($cart->id) ? $cart->id : false;
		
		if( $id && ctype_digit( (string)$id ) ){
			$id = db_scrub(stripslashes($id));
			$check_existing = $this->get_cartridges( $customer_id, $id, false, $printer_id );
			if( !$check_existing || mysql_num_rows($check_existing)<=0 ){
				$query_mode = 'new';
			} else {
				$row = mysql_fetch_object($check_existing);
				switch(true){
					case stripslashes($row->cartridge) !== $cart->name:
					case stripslashes($row->shorthand) !== $cart->shorthand:
					case number_format(stripslashes($row->price_retail), 2, '.', ',') !== number_format($cart->price_retail, 2, '.', ','):
					case number_format(stripslashes($row->price_user), 2, '.', ',') !== number_format($cart->price_user, 2, '.', ','):
					case ($this->invoice_mode && number_format($row->qty, 0, '', '') !== number_format($cart->qty, 0, '', '')):
						$query_mode = 'update';	break;
					default:
						$query_mode = 'ignore';	break;
				}
			}
		}
		else
		{
			$query_mode = 'new';
		}
		
		$cartridge_name = db_scrub(stripslashes($cart->name));
		$shorthand = db_scrub(stripslashes($cart->shorthand));
		$price_retail = db_scrub(number_format(stripslashes($cart->price_retail), 2, '.', ','));
		$price_user = db_scrub(number_format(stripslashes($cart->price_user), 2, '.', ','));
		if($this->invoice_mode) $qty = db_scrub(number_format(stripslashes($cart->qty), 0, '', ''));
		if($this->invoice_mode) $qty = ($qty>=1) ? $qty : '0';
		
		if ( $query_mode == 'ignore' )
		{
			return $id;
		}
		elseif ( $query_mode == 'update' )
		{
			$sql = <<<HEREDOC
UPDATE {$this->db_name}.cartridges
SET cartridge = '{$cartridge_name}',
	shorthand = '{$shorthand}',
	price_retail = '{$price_retail}',
	price_user = '{$price_user}'
HEREDOC;
$sql .= ($this->invoice_mode) ? ",\n\tqty = '{$qty}'" : '';
$sql .= "\nWHERE cartridge_id = {$id}\nLIMIT 1";
			if ($this->test_mode)
			{
				print_r('<pre>'.$sql.'</pre><hr />');
			}
			else
			{
				/*
				send_email_alert('Saving Cartridge, query mode == update.'
					. "\n"
					. "\n" . 'SQL: ' . $sql
					. "\n" . 'File: ' . __FILE__
					. "\n" . 'Line: ' . __LINE__
					. "\n" . 'Request URI: ' . $_SERVER['REQUEST_URI']
					,
					'Saving Cartridge [query mode == update]'
					,
					false
				);
				*/
				mysql_query($sql) or my_mysql_error($sql);
			}
			$this_printer_id = $id;
			$this->create_log( ($this->db_name.'.cartridges'), $id, 'UPDATE', $sql, 'Customer cartridge updated.' );
		}
		elseif ( $query_mode == 'new' )
		{
			$sql = <<<HEREDOC
INSERT INTO {$this->db_name}.cartridges
VALUES(
	'{$customer_id}',
	'',
	'{$cartridge_name}',
	'{$shorthand}',
	'{$price_retail}',
	'{$price_user}'
HEREDOC;
$sql .= ($this->invoice_mode) ? ",\n\t'{$qty}'\n);" : "\n);";

			if ($this->test_mode)
			{
				print_r('<pre>'.$sql.'</pre><hr />');
			}
			else
			{
				/*
				send_email_alert('Saving Cartridge, query mode == new.'
					. "\n"
					. "\n" . 'SQL: ' . $sql
					. "\n" . 'File: ' . __FILE__
					. "\n" . 'Line: ' . __LINE__
					. "\n" . 'Request URI: ' . $_SERVER['REQUEST_URI']
					,
					'Saving Cartridge [query mode == new]'
					,
					false
				);
				*/
				mysql_query($sql) or my_mysql_error($sql);
			}
			$this_cartridge_id = (!$this->test_mode)? mysql_insert_id() : rand(1,9999);
			$this->create_log( ($this->db_name.'.cartridges'), $this_cartridge_id, 'INSERT', $sql, 'Customer cartridge added.' );
			
			if($printer_id) $this->assoc_cartridges_printers( $customer_id, $printer_id, $this_cartridge_id );
		}
		
		$this->updated = $customer_id;
		return ($this_cartridge_id) ? $this_cartridge_id : $id;
	}
	
	
	## DELETE CARTRIDGES (AND CHILDREN)
	/**
	  * Removes a single cartridge from a customer account ($printer_id=false)
	  	or removes all cartridges from a customer printer ($printer_id!=false)
	    Also removes all associations to manufacturers and types
		Also removes all associated cartridges
		Also removes all associations to parent printer
		$this->delete_cartridges then removes all cartridge associations  to manufacturers and cartridges types
	  * $customer_id	int			ID of the customer account
	  * $cartridge_id	int/false	ID of the customer cartridge
	  * $limit			int/false	Limit the number of returned rows
	  * $printer_id		int/false	ID of the customer printer
	  * $from_printer	bool		If the function is being run from $this->delete_printers		
	  **/
	public function delete_cartridges( $customer_id, $cartridge_id=false, $limit=false, $printer_id=false, $from_printer=false ){
		if(!$customer_id) die("Customer ID Required: ".__LINE__);
		$customer_id = db_scrub($customer_id, 'int');
		if($cartridge_id) $cartridge_id = db_scrub($cartridge_id, 'int');
		if($limit) $limit = db_scrub($limit, 'int');
		if($printer_id) $printer_id = db_scrub($printer_id, 'int');
		
		if($printer_id) $get_existing = $this->get_cartridges( $customer_id, false, false, $printer_id );
		
		if( !$printer_id ){
			$sql = "DELETE FROM {$this->db_name}.cartridges WHERE customer_id = {$customer_id}";
			if($cartridge_id) $sql .= " AND cartridge_id = '{$cartridge_id}' LIMIT 1";
			if(!$cartridge_id && $limit) $sql .= " LIMIT {$limit}";
		} else {
			$sql = "DELETE {$this->db_name}.cartridges, {$this->db_name}.cartridges_to_printers FROM {$this->db_name}.cartridges LEFT JOIN {$this->db_name}.cartridges_to_printers ON cartridges.cartridge_id = cartridges_to_printers.cartridge_id WHERE cartridges_to_printers.printer_id = '{$printer_id}'";
		}
		
		if($this->test_mode){ print_r('<pre>'.$sql.'</pre><hr />'); }
		else{ mysql_query($sql) or my_mysql_error($sql); }
		$this->create_log( ($this->db_name.'.printers'), $cartridge_id, 'DELETE', $sql, 'Customer cartridges(s) deleted.' );
		
		## DELETE CARTRIDGE TYPE AND MANUFACTURER ASSOCIATIONS
		if( $cartridge_id ){
			$this->unassoc_cartridges_printers( $customer_id, $cartridge_id, $from_printer, ((!$from_printer)?true:false) );
			$this->unassoc_cartridges_manufacturers( $customer_id, false, $cartridge_id, $from_printer, ((!$from_printer)?true:false) );
			$this->unassoc_cartridges_types( $customer_id, false, $cartridge_id, $from_printer, ((!$from_printer)?true:false) );
		} else if( $printer_id ){
			while( $get_existing && mysql_num_rows($get_existing) && $row=mysql_fetch_object($get_existing) ){
				$this->unassoc_cartridges_printers( $customer_id, $cartridge_id, $from_printer, ((!$from_printer)?true:false) );
				$this->unassoc_cartridges_manufacturers( $customer_id, false, $cartridge_id, $from_printer, ((!$from_printer)?true:false) );
				$this->unassoc_cartridges_types( $customer_id, false, $cartridge_id, $from_printer, ((!$from_printer)?true:false) );
			}
		}
		
		$this->updated = $customer_id;
		return;
	}
	
	
	## GET ASSOCIATED CARTRIDGES TO PRINTERS
	/**
	  * Reordered alias for $this->get_cartridges()
	    so that the paramaters are orders like they are in other assoc functions
		but code is not repeated.
	  **/
	public function get_assoc_cartridges_printers( $customer_id, $printer_id=false, $cartridge_id=false, $limit=false ){
		if(!$customer_id) die("Customer ID Required: ".__LINE__);
		return $this->get_cartridges( $customer_id, $cartridge_id, $limit, $printer_id );
	}
	
	
	## ASSOCIATE CARTRIDGES AND PRINTERS
	/**
	  * Associates a printer with a printer type
	  * $customer_id	int			ID of the customer account
	  * $pritner_id		int			ID of the customer printer
	  * $cartridge_id	int			ID of the customer cartridge
	  **/
	public function assoc_cartridges_printers( $customer_id, $printer_id, $cartridge_id ){
		if(!$customer_id) die("Customer ID Required: ".__LINE__);
		$customer_id = db_scrub(stripslashes($customer_id), 'int');
		$printer_id = db_scrub(stripslashes($printer_id), 'int');
		$cartridge_id = db_scrub(stripslashes($cartridge_id), 'int');
		
		if( empty($customer_id) || empty($printer_id) || empty($cartridge_id) ) return false;
		
		$check_existing = $this->get_assoc_cartridges_printers( $customer_id, $printer_id, $cartridge_id );
		if( !$check_existing || mysql_num_rows($check_existing)<=0 ){
			$sql = "INSERT INTO {$this->db_name}.cartridges_to_printers VALUES( '{$customer_id}', '{$cartridge_id}', '{$printer_id}' );";
			
			if($this->test_mode){ print_r('<pre>'.$sql.'</pre><hr />'); }
			else{ mysql_query($sql) or my_mysql_error($sql); }
			$this->create_log( ($this->db_name.'.cartridges_to_printers'), '', 'INSERT', $sql, 'Customer cartridge associated with customer printer.' );
			$this->updated = $customer_id;
		}
		
		return true;
	}
	
	
	## UNASSOCIATE CARTRIDGES AND PRINTER
	/**
	  * Unassociates a printer with a printer type
	  * $customer_id	int			ID of the customer account
	  * $cartridge_id	int			ID of the customer cartridge
	  * $from_printer	bool		If the function is being run from $this->delete_printers
	  * $from_cartridge	bool		If the function is being run from $this->delete_cartridges
	  **/
	public function unassoc_cartridges_printers( $customer_id, $cartridge_id, $from_printer=false, $from_cartridge=false ){
		if(!$customer_id) die("Customer ID Required: ".__LINE__);
		$customer_id = db_scrub(stripslashes($customer_id), 'int');
		$printer_id = db_scrub(stripslashes($printer_id), 'int');
		$cartridge_id = db_scrub(stripslashes($cartridge_id), 'int');
		
		$sql =  "DELETE FROM {$this->db_name}.cartridges_to_printers WHERE customer_id = {$customer_id} AND cartridge_id = {$cartridge_id}";
		if($this->test_mode){ print_r('<pre>'.$sql.'</pre><hr />'); }
		else{ mysql_query($sql) or my_mysql_error($sql); }
		
		$message = ($from_printer) ? 'Customer cartridge to printer assocation delete. [From printer deletion]' : ($from_cartridge) ? 'Customer cartridge to printer assocation delete. [From cartridge deletion]' : 'Customer cartridge to printer assocation delete.';
		$this->create_log( ($this->db_name.'.printers_to_types'), $type_id, 'DELETE', $sql, $message );
		
		$this->updated = $customer_id;
		return true;
	}
		
	
	## GET ASSOCIATED CARTRIDGE MANUFACTURERS
	/**
	  * Gets all manufacturers from a cartridge ($manufacturer_id=false)
	    or gets a single manufacturer ($printer_id=false)
	  * $customer_id		int			ID of the customer account
	  * $cartridge_id		int/false	ID of the customer cartridge
	  * $manufacturer_id	int/false	ID of the customer manufacturer
	  * $limit				int/false	Limit the number of returned rows		
	  **/
	public function get_assoc_cartridges_manufacturers( $customer_id, $cartridge_id=false, $manufacturer_id=false, $limit=false ){
		if(!$customer_id) die("Customer ID Required: ".__LINE__);
		$customer_id = db_scrub($customer_id, 'int');
		if($cartridge_id) $cartridge_id = db_scrub($cartridge_id, 'int');
		if($manufacturer_id) $manufacturer_id = db_scrub($manufacturer_id, 'int');
		if($limit) $limit = db_scrub($limit, 'int');
		
		$sql = "SELECT cm.*, m.manufacturer_name FROM {$this->db_name}.cartridges_to_manufacturers cm LEFT JOIN {$this->db_name}.manufacturers m ON cm.manufacturer_id = m.manufacturer_id WHERE cm.customer_id = {$customer_id}";
		if($cartridge_id) $sql .= " AND cm.cartridge_id = {$cartridge_id}";
		if($manufacturer_id) $sql .= " AND cm.manufacturer_id = '{$manufacturer_id}' LIMIT 1";
		if(!$manufacturer_id && $limit) $sql .= " LIMIT {$limit}";
		
		$manufacturers = mysql_query($sql) or my_mysql_error($sql);
		return ($manufacturers) ? $manufacturers : false;
	}
	
	
	## ASSOCIATE CARTRIDGES AND MANUFACTURERS
	/**
	  * Associates a cartridge with a manufacturer
	  * $customer_id		int			ID of the customer account
	  * $manufacturer_id	int			ID of the customer manufacturer
	  * $cartridge_id			int			ID of the customer cartridge
	  **/
	public function assoc_cartridges_manufacturers( $customer_id, $manufacturer_id, $cartridge_id ){
		if(!$customer_id) die("Customer ID Required: ".__LINE__);
		$customer_id = db_scrub(stripslashes($customer_id), 'int');
		$manufacturer_id = db_scrub(stripslashes($manufacturer_id), 'int');
		$cartridge_id = db_scrub(stripslashes($cartridge_id), 'int');
		
		if( empty($customer_id) || empty($manufacturer_id) || empty($cartridge_id) ) return false;
		
		$check_existing = $this->get_assoc_cartridges_manufacturers( $customer_id, $cartridge_id, $manufacturer_id );
		if( !$check_existing || mysql_num_rows($check_existing)<=0 ){
			$sql = "INSERT INTO {$this->db_name}.cartridges_to_manufacturers VALUES( '{$customer_id}', '{$cartridge_id}', '{$manufacturer_id}' );";
			
			if($this->test_mode){ print_r('<pre>'.$sql.'</pre><hr />'); }
			else{ mysql_query($sql) or my_mysql_error($sql); }
			$this->create_log( ($this->db_name.'.cartridges_to_manufacturers'), '', 'INSERT', $sql, 'Customer manufacturer associated with customer cartridge.' );
			$this->updated = $customer_id;
		}
		
		return true;
	}
	
	
	## UNASSOCIATE CARTRIDGES AND MANUFACTURERS
	/**
	  * Unassociates a cartridge with all of it's manufacturers ($manufacturer_id=false)
	    or unassociates a cartridge with a single manufacturer
	  * $customer_id		int			ID of the customer account
	  * $manufacturer_id	int/false	ID of the customer manufacturer
	  * $cartridge_id		int			ID of the customer cartridge
	  * $from_printer		bool		If the function is being run from $this->delete_printers
	  * $from_cartridge		bool		If the function is being run from $this->delete_cartridges
	  **/
	public function unassoc_cartridges_manufacturers( $customer_id, $manufacturer_id=false, $cartridge_id, $from_printer=false, $from_cartridge=false ){
		if(!$customer_id) die("Customer ID Required: ".__LINE__);
		$customer_id = db_scrub(stripslashes($customer_id), 'int');
		if($manufacturer_id) $manufacturer_id = db_scrub(stripslashes($manufacturer_id), 'int');
		$cartridge_id = db_scrub(stripslashes($cartridge_id), 'int');
		
		$sql =  "DELETE FROM {$this->db_name}.cartridges_to_manufacturers WHERE customer_id = {$customer_id} AND cartridge_id = {$cartridge_id}";
		$sql .= ($manufacturer_id) ? " AND manufacturer_id = '{$manufacturer_id}' LIMIT 1" : " LIMIT 1";
		if($this->test_mode){ print_r('<pre>'.$sql.'</pre><hr />'); }
		else{ mysql_query($sql) or my_mysql_error($sql); }
		
		$message = ($from_printer) ? 'Customer cartridge to manufacturer assocation delete. [From printer deletion]' : ($from_cartridge) ? 'Customer cartridge to manufacturer assocation delete. [From cartridge deletion]' : 'Customer cartridge to manufacturer assocation delete.';
		$this->create_log( ($this->db_name.'.cartridges_to_manufacturers'), $type_id, 'DELETE', $sql, $message );
		
		$this->updated = $customer_id;
		return true;
	}
	
	
	## PUT CARTRIDGE TYPES
	/**
	  * Adds cartridge type if not existent for customer account
	  * $customer_id	int			ID of the customer account
	  * $type_name		string		Type name
	  **/
	public function put_cartridges_types( $customer_id, $type_name ){
		if(!$customer_id) die("Customer ID Required: ".__LINE__);
		$customer_id = db_scrub(stripslashes($customer_id), 'int');
		$type_name = db_scrub(stripslashes($type_name));
		
		$sql = "SELECT * FROM {$this->db_name}.cartridge_types WHERE customer_id = {$customer_id} AND cartridge_type = '{$type_name}' LIMIT 1";
		$check_existing = mysql_query($sql) or my_mysql_error($sql);
		
		if( !$check_existing || mysql_num_rows($check_existing)<=0 ){
			$sql = "INSERT INTO {$this->db_name}.cartridge_types VALUES( '{$customer_id}', '', '{$type_name}' );";
			
			if($this->test_mode){ print_r('<pre>'.$sql.'</pre><hr />');
			}else{ mysql_query($sql) or my_mysql_error($sql); }
			$type_id = (!$this->test_mode)? mysql_insert_id() : rand(1,9999);
			$this->create_log( ($this->db_name.'.cartridge_types'), $type_id, 'INSERT', $sql, 'Customer cartridge type added.' );
			
			$this->updated = $customer_id;
			return $type_id;
		} else {
			return mysql_result($check_existing, 0, 'cart_type_id');
		}
	}
	
	
	## GET ASSOCIATED CARTRIDGE TYPES
	/**
	  * Gets all types from a cartridge ($type_id=false)
	    or gets a single type ($cartridge_id=false)
	  * $customer_id	int			ID of the customer account
	  * $cartridge_id	int/false	ID of the customer cartridge
	  * $type_id		int/false	ID of the customer type
	  * $limit			int/false	Limit the number of returned rows		
	  **/
	public function get_assoc_cartridges_types( $customer_id, $cartridge_id=false, $type_id=false, $limit=false ){
		if(!$customer_id) die("Customer ID Required: ".__LINE__);
		$customer_id = db_scrub($customer_id, 'int');
		if($cartridge_id) $cartridge_id = db_scrub($cartridge_id, 'int');
		if($type_id) $type_id = db_scrub($type_id, 'int');
		if($limit) $limit = db_scrub($limit, 'int');
		
		$sql = "SELECT ct.*, t.cartridge_type FROM {$this->db_name}.cartridges_to_types ct LEFT JOIN {$this->db_name}.cartridge_types t ON ct.cart_type_id = t.cart_type_id WHERE ct.customer_id = {$customer_id}";
		if($cartridge_id) $sql .= " AND ct.cartridge_id = {$cartridge_id}";
		if($type_id) $sql .= " AND ct.cart_type_id = '{$type_id}' LIMIT 1";
		if(!$type_id && $limit) $sql .= " LIMIT {$limit}";
		
		$types = mysql_query($sql) or my_mysql_error($sql);
		return ($types) ? $types : false;
	}
	
	
	## ASSOCIATE CARTRIDGES AND TYPES
	/**
	  * Associates a cartridge with a printer type
	  * $customer_id	int			ID of the customer account
	  * $type_id		int			ID of the customer printer type
	  * $cartridge_id	int			ID of the customer cartridge
	  **/
	public function assoc_cartridges_types( $customer_id, $type_id, $cartridge_id ){
		if(!$customer_id) die("Customer ID Required: ".__LINE__);
		$customer_id = db_scrub(stripslashes($customer_id), 'int');
		$type_id = db_scrub(stripslashes($type_id), 'int');
		$cartridge_id = db_scrub(stripslashes($cartridge_id), 'int');
		
		if( empty($customer_id) || empty($type_id) || empty($cartridge_id) ) return false;
		
		$check_existing = $this->get_assoc_cartridges_types( $customer_id, $cartridge_id, $type_id );
		if( !$check_existing || mysql_num_rows($check_existing)<=0 ){
			$sql = "INSERT INTO {$this->db_name}.cartridges_to_types VALUES( '{$customer_id}', '{$cartridge_id}', '{$type_id}' );";
			
			if($this->test_mode){ print_r('<pre>'.$sql.'</pre><hr />'); }
			else{ mysql_query($sql) or my_mysql_error($sql); }
			$this->create_log( ($this->db_name.'.cartridges_to_types'), '', 'INSERT', $sql, 'Customer cartridge type associated with customer printer.' );
			$this->updated = $customer_id;
		}
		
		return true;
	}
	
	
	## UNASSOCIATE CARTRIDGES AND TYPES
	/**
	  * Unassociates a cartridge with all of it's types ($type_id=false)
	    or unassociates a cartridge with a single type
	  * $customer_id	int			ID of the customer account
	  * $type_id		int/false	ID of the customer type
	  * $cartridge_id	int			ID of the customer cartridge
	  * $from_printer	bool		If the function is being run from $this->delete_printers
	  * $from_cartridge	bool		If the function is being run from $this->delete_cartridges
	  **/
	public function unassoc_cartridges_types( $customer_id, $type_id=false, $cartridge_id, $from_printer=false, $from_cartridge=false ){
		if(!$customer_id) die("Customer ID Required: ".__LINE__);
		$customer_id = db_scrub(stripslashes($customer_id), 'int');
		if($type_id) $type_id = db_scrub(stripslashes($type_id), 'int');
		$cartridge_id = db_scrub(stripslashes($cartridge_id), 'int');
		
		$sql =  "DELETE FROM {$this->db_name}.cartridges_to_types WHERE customer_id = {$customer_id} AND cartridge_id = {$cartridge_id}";
		$sql .= ($type_id) ? " AND cart_type_id = '{$type_id}' LIMIT 1" : " LIMIT 1";
		if($this->test_mode){ print_r('<pre>'.$sql.'</pre><hr />'); }
		else{ mysql_query($sql) or my_mysql_error($sql); }
		
		$message = ($from_printer) ? 'Customer cartridge to type assocation delete. [From printer deletion]' : ($from_cartridge) ? 'Customer cartridge to type assocation delete. [From cartridge deletion]' : 'Customer cartridge to type assocation delete.';
		$this->create_log( ($this->db_name.'.cartridges_to_types'), $type_id, 'DELETE', $sql, $message );
		
		$this->updated = $customer_id;
		return true;
	}
}
?>