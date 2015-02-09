<?php
final class Orders{
	
	private $db_customer = DB_DATABASE_CUSTOMERS;
	private $db_user = DB_DATABASE_USERS;
	
	## __CONSTRUCT
	public function __construct(){
		return true;
	}
	
	public function has_new_orders(){
		global $User;
		$sql = <<<HEREDOC
		SELECT COUNT(o.completed) as num
		FROM {$this->db_customer}.orders o
		LEFT JOIN {$this->db_customer}.customers_to_users ctu
			ON o.customer_id = ctu.customer_id
		WHERE ctu.user_id = '{$User->id}'
			AND o.emailed = 0
			AND o.completed = 0
			AND o.invoice_id = 0
		GROUP BY o.completed
HEREDOC;

		$new = mysql_query($sql) or my_mysql_error($sql);
		return ($new && mysql_num_rows($new)>0) ? $new : false;
	}
	
	public function get_order_specs( $order_id ){
		$order_id = db_scrub($order_id, 'int');
		$sql = <<<HEREDOC
		SELECT o.order_id, o.customer_id, ctu.user_name, ctu.email, ctu.username, o.date_time, o.ip_address, o.po_number, o.comment, c.customer_name, 
			COUNT(otc.order_id) as num_cartridges,
			SUM(otc.qty) as total_qty,
			SUM(otc.price*otc.qty) as total
		FROM {$this->db_customer}.orders o
		LEFT JOIN {$this->db_customer}.orders_to_cartridges otc
			ON o.order_id = otc.order_id
		LEFT JOIN {$this->db_customer}.customer_user_accounts ctu
			ON o.user_account_id  = ctu.user_account_id 
		LEFT JOIN {$this->db_customer}.customers c
			ON o.customer_id = c.customer_id
		WHERE o.order_id = '{$order_id}'
		GROUP BY otc.order_id
HEREDOC;
		
		$specs = mysql_query($sql) or my_mysql_error($sql);
		return ($specs && mysql_num_rows($specs)>0) ? $specs : false;
	}
	
	public function get_customer_addresses( $order_id ){
		$order_id = db_scrub($order_id, 'int');
		$sql = <<<HEREDOC
		SELECT at.address_type, ca.address_one, ca.address_two, ca.city, ca.state, ca.zip
		FROM {$this->db_customer}.orders o
		LEFT JOIN {$this->db_customer}.customer_addresses ca
			ON o.customer_id = ca.customer_id
		LEFT JOIN {$this->db_customer}.address_types at
			ON ca.address_type = at.address_type_id
		WHERE o.order_id = '{$order_id}'
HEREDOC;

		$addresses = mysql_query($sql) or my_mysql_error($sql);
		return ($addresses && mysql_num_rows($addresses)>0) ? $addresses : false;
	}
	
	public function get_order( $order_id ){
		$order_id = db_scrub($order_id, 'int');
		$sql = <<<HEREDOC
		SELECT otc.cartridge_id, otc.printer, otc.printer_location, otc.cartridge, otc.shorthand, otc.description, otc.price, otc.qty,
			SUM(otc.price*otc.qty) as subtotal
		FROM {$this->db_customer}.orders_to_cartridges otc
		WHERE otc.order_id = '{$order_id}'
		GROUP BY otc.cartridge_id
HEREDOC;

		$order = mysql_query($sql) or my_mysql_error($sql);
		return ($order && mysql_num_rows($order)>0) ? $order : false;
	}
	
	public function get_users_orders( $user_id, $sort=false, $customer_id=false ){
		$user_id = db_scrub($user_id, 'int');
		if($customer_id) $customer_id = db_scrub($customer_id, 'int');
		
		$sql = <<<HEREDOC
		SELECT o.order_id, o.customer_id, c.customer_name, o.user_account_id, cua.user_name, cua.username, 
			o.date_time, o.ip_address, o.emailed, o.completed, o.completed_time, o.invoice_id, 
			SUM(otc.qty) as qty, SUM(otc.price*otc.qty) as subtotal
		FROM {$this->db_customer}.orders o
		LEFT JOIN {$this->db_customer}.customers c
			ON o.customer_id = c.customer_id
		LEFT JOIN {$this->db_customer}.customer_user_accounts cua
			ON o.user_account_id = cua.user_account_id
		LEFT JOIN {$this->db_customer}.customers_to_users ctu
			ON c.customer_id = ctu.customer_id
		LEFT JOIN {$this->db_customer}.orders_to_cartridges otc
			ON o.order_id = otc.order_id
		WHERE ctu.user_id = '{$user_id}'
HEREDOC;

		if($customer_id) $sql .= "\n\tAND o.customer_id = '{$customer_id}'";
		$sql .= "\nGROUP BY o.order_id";
		if(!$sort) $sql .= "\nORDER BY o.completed=0 DESC, o.date_time DESC, c.customer_name ASC";
		

		$orders = mysql_query($sql) or my_mysql_error($sql);
		return ($orders && mysql_num_rows($orders)>0) ? $orders : false;
	}
	
	public function get_order_owner( $order_id ){
		$order_id = db_scrub($order_id, 'int');
		$sql = <<<HEREDOC
		SELECT up.first_name, up.last_name, up.email
		FROM {$this->db_customer}.orders o
		LEFT JOIN {$this->db_customer}.customers_to_users ctu
			ON o.customer_id = ctu.customer_id
		LEFT JOIN {$this->db_user}.user_prefs up
			ON ctu.user_id = up.user_id
		WHERE o.order_id = '{$order_id}'
HEREDOC;

		$owner = mysql_query($sql) or my_mysql_error($sql);
		return ($owner && mysql_num_rows($owner)>0) ? $owner : false;
	}
	
	public function render_order( $order_id ){
		$admin = strpos(getcwd(), ADMIN_PATH);
		if( ($admin && $this->verify_order_ownership( $order_id )) || !$admin ){
			## HTML STYLES
			$html = <<<HEREDOC
<style>
	.order_render_table {
		margin: 0px auto 0px auto;
		border: 2px solid #000000;
		border-collapse: collapse;
	}
	.order_render_table td {
		color: #333333;
		font-size: 12px;
		font-family: Arial, Helvetica, sans-serif;
		padding: 2px 6px 2px 6px;
	}
	.order_render_table .header td {
		color: #ffffff;
		font-size: 10px;
		background-color: #888888;
	}
	.order_render_table .total td {
		color: #ffffff;
		font-size: 14px;
		background-color: #888888;
		border: 1px solid #888888;
	}
</style>
HEREDOC;
	
			## HTML - START TABLE
			$html  .= "\n\n<center>\n<table width=\"500\" border=\"1\" class=\"order_render_table\">\n";
			
			## GET MAIN ORDER SPECIFICATIONS
			$order_specs = $this->get_order_specs( $order_id );
			if($order_specs) $info = mysql_fetch_object($order_specs);
			
			## HTML - TITLE ROWS
			$html .= "\t<tr><td colspan=\"5\" align=\"center\" style=\"font-size: 16px; background-color: #eeeeee;\">Order for ".stripslashes($info->customer_name)."</td></tr>\n";
			$html .= "\t<tr><td colspan=\"5\" align=\"left\" style=\"font-size: 10px;\"><span style=\"float: right;\">ID: ".date('dHis', strtotime($info->date_time)).'-'.$info->order_id."</span>Placed by ".stripslashes($info->user_name)." on ".date('F jS, Y', strtotime($info->date_time))."</td></tr>\n";
			
			## GET ADDRESSES ON THE CUSTOMER ACCOUNT SO THEY KNOW WHERE THE ORDER IS GOING
			$addresses = $this->get_customer_addresses( $order_id );
			if($addresses) $html .= "\t<tr class=\"header\"><td colspan=\"5\" align=\"left\"><b>Addresses:</b></td></tr>\n";
			while( $addresses && $row=mysql_fetch_object($addresses) ){
				## HTML - ADDRESSES
				$html .= "\t<tr><td rowspan=\"2\" align=\"left\"><b>".stripslashes($row->address_type)." Address:</b></td><td colspan=\"4\" align=\"left\">".stripslashes($row->address_one)."</td></tr>\n";
				if(!empty($row->address_two)) $html .= "\t<tr><td>&nbsp;</td><td colspan=\"4\" align=\"left\">".stripslashes($row->address_two)."</td></tr>\n";
				$html .= "\t<tr><td colspan=\"4\" align=\"left\">".stripslashes($row->city).' '.stripslashes($row->state).', '.stripslashes($row->zip)."</td></tr>\n";
			}
			
			## GET ORDERED CARTRIDGES
			$total_qty = 0;
			$orders = $this->get_order( $order_id );
			if($orders){
				## HTML - ORDER TITLE ROW
				$html .= "\t<tr class=\"header\">\n";
				$html .= "\t\t<td colspan=\"2\" align=\"left\"><b>Order Details:</b></td>\n";
				$html .= "\t\t<td style=\"font-size: 10px;\" align=\"center\" width=\"55\">Price</td>\n";
				$html .= "\t\t<td style=\"font-size: 10px;\" align=\"center\" width=\"30\">Qty</td>\n";
				$html .= "\t\t<td style=\"font-size: 10px;\" align=\"center\" width=\"55\">Subtotal</td>\n";
				$html .= "\t</tr>\n";
				for( $x=0; $orders, $row=mysql_fetch_object($orders); $x++ ){
					## HTML - ORDERED CARTRIDGE DETAILS
					if($x>0) $html .= "\t<tr><td colspan=\"5\" style=\"background-color: #eeeeee;\">&nbsp;</td></tr>\n";
					if(!empty($row->printer)) $html .= "\t<tr><td align=\"left\"><b>Printer:</b></td><td colspan=\"4\" align=\"left\">".stripslashes($row->printer).((!empty($row->printer_location))?(' ('.stripslashes($row->printer_location).')'):'')."</td></tr>\n";
					if(!empty($row->cartridge)){
						$html .= "\t<tr>\n";
						$html .= "\t\t<td".((!empty($row->description))?' rowspan="2"':'')." align=\"left\"><b>Cartridge:</b></td>\n";
						$html .= "\t\t<td align=\"left\">IB-".stripslashes($row->cartridge).((!empty($row->shorthand))?(' ('.stripslashes($row->shorthand).')'):'')."</td>\n";
						$html .= "\t\t<td align=\"right\" width=\"55\">".(($row->price==0)?'Free':('$'.number_format($row->price, 2, '.', ',')))."</td>\n";
						$html .= "\t\t<td align=\"right\" width=\"30\">x".(int)$row->qty."</td>\n";
						$html .= "\t\t<td align=\"right\" width=\"55\">".(($row->subtotal==0)?'Free':('$'.number_format($row->subtotal, 2, '.', ',')))."</td>\n";
						$html .= "\t</tr>\n";
					}
					if(!empty($row->description)) $html .= "\t<tr><td colspan=\"4\" align=\"left\">".stripslashes($row->description)."</td></tr>\n";
					$total_qty+=intval($row->qty);
				}
			} else {
				## HTML - ORDER TITLE ROW
				$html .= "\t<tr class=\"header\">\n";
				$html .= "\t\t<td colspan=\"5\" align=\"center\"><b>There was a problem loading this order.</b></td>\n";
				$html .= "\t</tr>\n";
			}
			
			
			if( !empty($info->po_number) || !empty($info->comment) ){
				## HTML - OTHER INFO TITLE ROW
				$html .= "\t<tr class=\"header\">\n";
				$html .= "\t\t<td colspan=\"5\" align=\"left\"><b>Other Information:</b></td>\n";
				$html .= "\t</tr>\n";
				
				## HTML - PO NUMBER ROW
				$html .= "\t<tr>\n";
				$html .= "\t\t<td align=\"left\"><b>PO Number:</b></td>\n";
				$html .= "\t\t<td colspan=\"4\" align=\"left\">".(($info->po_number) ? stripslashes($info->po_number) : 'No PO Number given.')."</td>\n";
				$html .= "\t</tr>\n";
				
				## HTML - COMMENTS ROW
				$html .= "\t<tr>\n";
				$html .= "\t\t<td align=\"left\"><b>Comment:</b></td>\n";
				$html .= "\t\t<td colspan=\"4\" align=\"left\">".(($info->comment) ? stripslashes($info->comment) : 'No comment given.')."</td>\n";
				$html .= "\t</tr>\n";
			}
			
			
			## HTML - ORDER TOTALS
			$html .= "\t<tr><td colspan=\"5\" style=\"background-color: #eeeeee;\">&nbsp;</td></tr>\n";
			$html .= "\t<tr class=\"total\">\n";
			$html .= "\t\t<td colspan=\"3\" align=\"left\"><b>Total:</b></td>\n";
			$html .= "\t\t<td align=\"center\" style=\"font-size: 12px;\" title=\"".(int)$total_qty." Cartridges Total\">(".(int)$total_qty.")</td>\n";
			$html .= "\t\t<td align=\"right\"><b>".(($info->total==0)?'Free':('$'.number_format($info->total, 2, '.', ',')))."<b/></td>\n";
			$html .= "\t</tr>\n";
			
			## HTML - TABLE END
			$html .= "</table>\n</center>";
			return $html;
		} else {
			return 'This order does not belong to one of your customers.';
		}
	}
	
	public function update_order_email_status( $order_id, $email_status ){
		if( $this->verify_order_ownership($order_id) ){
			$order_id = db_scrub($order_id, 'int');
			$email_status = ($email_status) ? db_scrub($email_status, 'int') : 0;
			$sql = "UPDATE {$this->db_customer}.orders o SET o.emailed='{$email_status}' WHERE o.order_id = '{$order_id}' LIMIT 1";
			$update = mysql_query($sql) or my_mysql_error($sql);
			return ($update) ? $update : false;
		} else {
			return false;
		}
	}
	
	public function complete_order( $order_id ){
		if( $this->verify_order_ownership($order_id) ){
			$order_id = db_scrub($order_id, 'int');
			$sql = "UPDATE {$this->db_customer}.orders o SET o.completed=1, o.completed_time=NOW() WHERE o.order_id = '{$order_id}' LIMIT 1";
			$update = mysql_query($sql) or my_mysql_error($sql);
			return ($update) ? $update : false;
		} else {
			return false;
		}
	}
	
	public function order_to_invoice( $order_id, $invoice_id ){
		if( $this->verify_order_ownership($order_id) ){
			$order_id = db_scrub($order_id, 'int');
			$invoice_id = db_scrub($invoice_id, 'int');
			$sql = "UPDATE {$this->db_customer}.orders o SET o.invoice_id='{$invoice_id}' WHERE o.order_id = '{$order_id}' LIMIT 1";
			$update = mysql_query($sql) or my_mysql_error($sql);
			return ($update) ? $update : false;
		} else {
			return false;
		}
	}
	
	private function verify_order_ownership( $order_id ){
		global $User;
		if( $User->id ){
			$sql = <<<HEREDOC
SELECT ctu.user_id
FROM {$this->db_customer}.orders o
LEFT JOIN {$this->db_customer}.customers_to_users ctu
	ON o.customer_id = ctu.customer_id
WHERE ctu.user_id = '{$User->id}'
HEREDOC;
			$owner = mysql_query($sql) or my_mysql_error($sql);
			return ($owner && mysql_num_rows($owner)>0) ? true : false;
		} else {
			return false;
		}
	}
}
?>