<?php
class Invoices_DB extends Customers_DB {
	public $updating = false;
	private $customer_db_name = DB_DATABASE_CUSTOMERS;	// Is not effected by invoice mode
	private $invoice_db_name = DB_DATABASE_INVOICES;	// Is not effected by invoice mode
	private $user_db_name = DB_DATABASE_USERS;	// Is not effected by invoice mode
	
	## __CONSTRUCT
	function __construct( $customer_id, $invoice_id ){
		$customer_id = db_scrub($customer_id);
		if( $invoice_id ){
			$invoice_id = db_scrub($invoice_id);
			$sql = "SELECT invoice_id FROM {$this->invoice_db_name}.invoices WHERE invoice_id = '{$invoice_id}' LIMIT 1";
			$invoice_exists = mysql_query($sql) or die(mysql_error().'<br />'.$sql.'<br />Line: '.__LINE__);
			if( $invoice_exists && mysql_num_rows($invoice_exists)>=1 && $row=mysql_fetch_object($invoice_exists) ){
				$this->updating = ($row->invoice_id == $invoice_id) ? true : false;
			}
		} else {
			$this->updating = false;
		}
	}
	
	
	## VERIFY OWNERSHIP
	/**
	  * Verifies that the supplied user owns the supplied invoice
	  * $invoice_id		int			ID of the customer account
	  * $user_id		int			ID of the user account
	  **/
	public function verify_ownership( $invoice_id=false, $user_id=false ){
		if( !$invoice_id || !$user_id ){
			return false;
		} else {
			$user_id = db_scrub($user_id, 'int');
			$invoice_id = db_scrub($invoice_id, 'int');
		
			$sql = <<<HEREDOC
			SELECT c.customer_id as invoice_id, cu.user_id
			FROM {$this->invoice_db_name}.customers c
			INNER JOIN {$this->invoice_db_name}.customers_to_customers cc
			ON c.customer_id = cc.customer_id
			INNER JOIN {$this->customer_db_name}.customers_to_users cu
			ON cc.real_customer_id = cu.customer_id
			WHERE cu.user_id = '{$user_id}'	
			AND c.customer_id = '{$invoice_id}'
			LIMIT 1
HEREDOC;
			$check_ownership = mysql_query($sql) or die(__file__ . ': ' . mysql_error() . '<br />Line: '.__LINE__);
			return ( !$check_ownership || mysql_num_rows($check_ownership)<=0 || mysql_result($check_ownership, 0, 'invoice_id')!=$invoice_id ) ? false : true;
		}
	}
	
	
	## GET INVOICE
	/**
	  * Gets an invoice
	  * $customer_id	int			ID of the customer account
	  * $invoice_id		int			ID of the customer contact
	  **/
	public function get_invoice( $invoice_id ){
		if(!$invoice_id) die("Invoice ID Required: ".__LINE__);
		$invoice_id = db_scrub($invoice_id, 'int');
		
		$sql = "SELECT * FROM {$this->invoice_db_name}.invoices WHERE invoice_id = {$invoice_id} LIMIT 1";
		
		$invoice = mysql_query($sql) or die(mysql_error().'<br />Line: '.__LINE__);
		return ($invoice) ? $invoice : false;
	}
	
	
	## PUT INVOICE
	/**
	  * Adds or updates invoice information. Info is ignored if existent and unchanged
	  * $customer_id	int/false	ID of the customer account
	  * $invoice		object		Contains Invoice data
	  **/
	public function put_invoice( $customer_id, $id, $invoice ){
		if(!$customer_id) die("Customer ID Required: ".__LINE__);
		$customer_id = db_scrub(stripslashes($customer_id), 'int');
		
		if( $id && ctype_digit( (string)$id ) ){
			$id = db_scrub(stripslashes($id), 'int');
			$check_existing = $this->get_invoice( $id );
			
			if( !$check_existing || mysql_num_rows($check_existing)<=0 ){
				$query_mode = 'new';
			} else {
				$row = mysql_fetch_object($check_existing);
				switch(true){
					case stripslashes($row->invoice_number) !== stripslashes($invoice->number):
					case stripslashes($row->invoice_date) !== date('Y-m-d', strtotime($invoice->date)):
					case stripslashes($row->po_number) !== stripslashes($invoice->po):
					case stripslashes($row->shipment) !== stripslashes($invoice->shipment):
					case stripslashes($row->shipping_fee) !== number_format($invoice->shipping_fee, 2, '.', ''):
					case stripslashes($row->additional) !== number_format($invoice->additional, 2, '.', ''):
					case stripslashes($row->additional_description) !== stripslashes($invoice->additional_description):
					case stripslashes($row->tax) !== number_format($invoice->tax, 3, '.', ''):
					case stripslashes($row->customer_comment) !== stripslashes($invoice->customer_comment):
					case stripslashes($row->private_comment) !== stripslashes($invoice->private_comment):
					case stripslashes($row->subtotal) !== number_format($invoice->subtotal, 2, '.', ''):
					case stripslashes($row->total) !== number_format($invoice->total, 2, '.', ''):
						$query_mode = 'update';	break;
					default:
						$query_mode = 'ignore';	break;
				}
			}
		} else { $query_mode = 'new'; }
		
		$number = db_scrub(stripslashes($invoice->number));
		$date = db_scrub(date('Y-m-d', strtotime($invoice->date)));
		$po = db_scrub(stripslashes($invoice->po));
		$shipment = db_scrub(stripslashes($invoice->shipment));
		$shipping_fee = db_scrub(number_format($invoice->shipping_fee, 2, '.', ''));
		$additional = db_scrub(number_format($invoice->additional, 2, '.', ''));
		$additional_description = db_scrub(stripslashes($invoice->additional_description));
		$tax = db_scrub(number_format($invoice->tax, 3, '.', ''));
		$customer_comment = db_scrub(stripslashes($invoice->customer_comment));
		$private_comment = db_scrub(stripslashes($invoice->private_comment));
		$subtotal = db_scrub(number_format($invoice->subtotal, 2, '.', ''));
		$total = db_scrub(number_format($invoice->total, 2, '.', ''));
		
		if( $query_mode == 'ignore' ){
			return false;
		} elseif( $query_mode == 'update' ){
			$invoice->id = db_scrub($invoice->id, 'int');
			$sql = <<<HEREDOC
UPDATE {$this->invoice_db_name}.invoices
SET invoice_number = '{$number}',
	invoice_date = '{$date}',
	po_number = '{$po}',
	shipment = '{$shipment}',
	shipping_fee = '{$shipping_fee}',
	additional = '{$additional}',
	additional_description = '{$additional_description}',
	tax = '{$tax}',
	customer_comment = '{$customer_comment}',
	private_comment = '{$private_comment}',
	subtotal = '{$subtotal}',
	total = '{$total}'
WHERE invoice_id = {$id}
LIMIT 1
HEREDOC;
			if($this->test_mode){ print_r('<pre>'.$sql.'</pre><hr />');
			}else{ mysql_query($sql) or die(mysql_error().'<br />'.$sql.'<br />Line: '.__LINE__); }
			$this_invoice_id = $customer_id;
			
		} elseif( $query_mode == 'new' ){
			$sql = <<<HEREDOC
INSERT INTO {$this->invoice_db_name}.invoices
VALUES(
	'{$id}',
	'{$number}',
	'{$date}',
	'{$po}',
	'{$shipment}',
	'{$shipping_fee}',
	'{$additional}',
	'{$additional_description}',
	'{$tax}',
	'{$customer_comment}',
	'{$private_comment}',
	'{$subtotal}',
	'{$total}'
);
HEREDOC;
			if($this->test_mode){ print_r('<pre>'.$sql.'</pre><hr />');
			}else{ mysql_query($sql) or die(mysql_error().'<br />'.$sql.'<br />Line: '.__LINE__); }
			$this_invoice_id = (!$this->test_mode)? mysql_insert_id() : rand(1,9999);
		}
		
		return $this_invoice_id;
	}
	
	## UPDATE INVOICE NUMBER
	/**
	  * Updates the last incriment, and issue date for the invoice number in the user database
	  **/
	public function update_invoice_number( $new_id, $user_id ){
		$sql = "UPDATE {$this->user_db_name}.user_prefs SET last_invoice_id = '{$new_id}', last_invoice_date = NOW() WHERE user_id = '{$user_id}' LIMIT 1";
		if($this->test_mode){ print_r('<pre>'.$sql.'</pre><hr />');
		}else{ $update = mysql_query($sql) or die(mysql_error().'<br />'.$sql.'<br />Line: '.__LINE__); }
		return ($update) ? true : false;
	}
}
?>