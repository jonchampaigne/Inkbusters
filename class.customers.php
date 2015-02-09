<?php
class Customers{
	public		$customer_id;
	public		$where_printer_type;
	public		$where_printer_manufacturer;
	
	private		$num_printers;
	private		$num_cartridges;
	private		$num_proposals;
	private		$num_invoices;
	
	## __CONSTRUCT
	public function __construct( $customer_id = false ){
		db_connect('customers');
		if(!empty($customer_id)){
			$this->customer_id = $customer_id;
			$this->get_customer_info( $this->customer_id );
		}
		return true;
	}
	
	public function get_customer_folders( $id = false ){
		
		
		if(!$id) $id = $_SESSION['user_id'];
		$sql = <<<HEREDOC
			SELECT f.folder_id, f.folder_name
			FROM {$DB_DATABASE_CUSTOMERS}.folders f
			WHERE f.user_id = {$id}
			ORDER BY f.folder_name ASC
HEREDOC;
		$folders = mysql_query($sql);
		return ($folders && mysql_num_rows($folders)>=1)? $folders : false;
	}
	
	// NEWER FUNCTION THAN get_users_customers()
	public function get_customers( $id=false, $sort=false ){
	
		global $DB_DATABASE_USERS, $DB_DATABASE_CUSTOMERS, $DB_DATABASE_PROPOSALS, $DB_DATABASE_INVOICES;
	
		if(!$id) $id = db_scrub($_SESSION['user_id']);
		$sql = <<<HEREDOC
			SELECT c.customer_id, c.customer_name, fc.folder_id, c.date_added, c.date_updated, p.proposal_id, p.finalized
			FROM {$DB_DATABASE_CUSTOMERS}.customers c
			LEFT JOIN {$DB_DATABASE_CUSTOMERS}.folders_to_customers fc
			ON c.customer_id = fc.customer_id
			LEFT JOIN {$DB_DATABASE_CUSTOMERS}.customer_proposals p
			ON c.customer_id = p.customer_id
			INNER JOIN {$DB_DATABASE_CUSTOMERS}.customers_to_users u
			ON c.customer_id = u.customer_id
			WHERE u.user_id = $id
HEREDOC;
		
		if($sort){
			if( !empty($sort['sort_year']) && $sort['sort_year']!='all' ){
				$year = db_scrub($sort['sort_year'], 'int');
				$sql .= "\nAND c.date_added >= '{$year}-01-01'\nAND c.date_added <= '{$year}-12-31'";
			}
			$sql .= "\nGROUP BY c.customer_id";
			if( !empty($sort['sort_mode']) ){
				switch($sort['proposal_sort_mode']){
					default:
					case 'ignore':
						$sql .= "\nORDER BY ";	break;
					case 'first':
						$sql .= "\nORDER BY p.finalized=0 DESC, ";	break;
					case 'last':
						$sql .= "\nORDER BY p.finalized=0 ASC, ";		break;
				}
				switch($sort['sort_mode']){
					default:
					case 'name_asc':
						$sql .= "\nc.customer_name ASC";	break;
					case 'name_desc':
						$sql .= "\nc.customer_name DESC";	break;
					case 'created_desc':
						$sql .= "\nc.date_added DESC";		break;
					case 'created_asc':
						$sql .= "\nc.date_added ASC";		break;
				}
			}
		} else {
			$sql .= "\nGROUP BY c.customer_id";
			$sql .= "\nORDER BY c.customer_name ASC";
		}
		
		$customers = mysql_query($sql) or die(mysql_error());
		return ($customers && mysql_num_rows($customers)>=1)? $customers : false;
	}
	
	public function get_years_with_customers( $user_id ){
		$user_id = db_scrub($user_id);
		
		$sql = <<<HEREDOC
			SELECT YEAR( c.date_added ) AS year
			FROM {$DB_DATABASE_CUSTOMERS}.customers c
			LEFT JOIN {$DB_DATABASE_CUSTOMERS}.customers_to_users cu
			ON c.customer_id = cu.customer_id
			WHERE cu.user_id = {$user_id}
			GROUP BY year
			ORDER BY year DESC 
HEREDOC;
		$years = mysql_query($sql);
		return ($years && mysql_num_rows($years)>=1)? $years : false;
	}
	
	public function get_invoices( $user_id, $customer_id=false, $sort=false ){
	
		global $DB_DATABASE_USERS, $DB_DATABASE_CUSTOMERS, $DB_DATABASE_PROPOSALS, $DB_DATABASE_INVOICES;
	
		$user_id = db_scrub($user_id);
		if($customer_id) $customer_id = db_scrub($customer_id);
		
		$sql = <<<HEREDOC
		SELECT cc.real_customer_id as customer_id, c.customer_id as invoice_id, c.customer_name, c.date_added, c.date_updated,

			i.invoice_number, i.invoice_date, i.po_number, i.total, i.customer_comment, i.private_comment,
			IF( vi.invoice_id IS NOT NULL, 1, 0) as voided, vi.reason
		FROM {$DB_DATABASE_INVOICES}.customers c
		INNER JOIN {$DB_DATABASE_INVOICES}.invoices i
			ON c.customer_id = i.invoice_id
		INNER JOIN {$DB_DATABASE_INVOICES}.customers_to_customers cc
			ON c.customer_id = cc.customer_id
		INNER JOIN {$DB_DATABASE_CUSTOMERS}.customers_to_users cu
			ON cc.real_customer_id = cu.customer_id
		LEFT JOIN {$DB_DATABASE_INVOICES}.voided_invoices vi
			ON c.customer_id = vi.invoice_id
		WHERE cu.user_id = '{$user_id}'
HEREDOC;
		if($customer_id) $sql .= "\nAND cc.real_customer_id = '{$customer_id}'";
		$sql .= "\nORDER BY i.invoice_date DESC, i.invoice_number DESC";
		
		$invoices = mysql_query($sql) or die(mysql_error());
		return ($invoices && mysql_num_rows($invoices)>=1)? $invoices : false;
	}
	
	public function get_num_invoices( $customer_id ){
	
		global $DB_DATABASE_USERS, $DB_DATABASE_CUSTOMERS, $DB_DATABASE_PROPOSALS, $DB_DATABASE_INVOICES;
	
		$customer_id = db_scrub($customer_id);
		$sql = <<<HEREDOC
			SELECT COUNT(*) as num_invoices
			FROM {$DB_DATABASE_INVOICES}.customers_to_customers cc
			WHERE cc.real_customer_id = {$customer_id}
HEREDOC;
		$num_invoices = mysql_query($sql) or die(mysql_error());
		return ($num_invoices && mysql_num_rows($num_invoices)>=1) ?  mysql_result($num_invoices, 0, 'num_invoices') : 0;
	}
	
	public function get_users_customers( $id = false ){
		if(!$id) $id = db_scrub($_SESSION['user_id']);
		$sql = <<<HEREDOC
			SELECT c.customer_id, c.customer_name, c.date_added, c.date_updated
			FROM customers c
			INNER JOIN customers_to_users u
			ON c.customer_id = u.customer_id
			WHERE u.user_id = $id
			ORDER BY c.customer_name ASC
HEREDOC;
		
		$customers = mysql_query($sql);
		while( $row = mysql_fetch_object($customers) ){
			$this->customers[ $row->customer_id ]->customer_id = $row->customer_id;
			$this->customers[ $row->customer_id ]->customer_name = $row->customer_name;
			$this->customers[ $row->customer_id ]->date_added = $row->date_added;
			$this->customers[ $row->customer_id ]->date_updated = $row->date_updated;
		}
	}
	
	public function get_customer_info( $id=false ){
		$DB_DATABASE_USERS = DB_DATABASE_USERS; //hmm
		
		if(!$id) $id = $this->customer_id;
		$sql = <<<HEREDOC
			SELECT u.user_id, c.customer_id, c.customer_name, c.date_added, c.date_updated, c.active_status AS active
			FROM customers c
			INNER JOIN customers_to_users u
			ON c.customer_id = u.customer_id
			WHERE c.customer_id = {$id}
			LIMIT 1
HEREDOC;

		$info = mysql_query($sql);
		//die($info); //**debug
		
		if (DEBUG && !$info) die('FILE = ' . __FILE__ . '<br />LINE = ' . __LINE__ .'<br />blerg = '.debug(mysql_error()));
		
		while( $info && $row = mysql_fetch_object($info) ){
			$this->customers[ $row->customer_id ]->info->user_id = $row->user_id;
			$this->customers[ $row->customer_id ]->info->customer_id = $row->customer_id;
			$this->customers[ $row->customer_id ]->info->customer_name = $row->customer_name;
			$this->customers[ $row->customer_id ]->info->date_added = $row->date_added;
			$this->customers[ $row->customer_id ]->info->date_updated = $row->date_updated;
			$this->customers[ $row->customer_id ]->info->active = $row->active; // add above:  u.active
			
			$sql = <<<HEREDOC
				SELECT contact_id, first_name, last_name, email, mobile, phone, fax
				FROM customer_contacts 
				WHERE customer_id = {$id}
				ORDER BY last_name, first_name
HEREDOC;
			
			$contacts = mysql_query($sql);
			while( $contacts && $contact = mysql_fetch_object($contacts) ){
				$this->customers[ $row->customer_id ]->info->contacts[ $contact->contact_id ]->contact_id = $contact->contact_id;
				$this->customers[ $row->customer_id ]->info->contacts[ $contact->contact_id ]->first_name = $contact->first_name;
				$this->customers[ $row->customer_id ]->info->contacts[ $contact->contact_id ]->last_name = $contact->last_name;
				$this->customers[ $row->customer_id ]->info->contacts[ $contact->contact_id ]->email = $contact->email;
				$this->customers[ $row->customer_id ]->info->contacts[ $contact->contact_id ]->mobile = $contact->mobile;
				$this->customers[ $row->customer_id ]->info->contacts[ $contact->contact_id ]->phone = $contact->phone;
				$this->customers[ $row->customer_id ]->info->contacts[ $contact->contact_id ]->fax = $contact->fax;
			}
			
			$sql = <<<HEREDOC
				SELECT a.address_id, a.address_type as address_type_id, at.address_type, address_one, address_two, city, state, zip
				FROM customer_addresses a
				INNER JOIN address_types at
				ON a.address_type = at.address_type_id
				WHERE a.customer_id = {$id}
				ORDER BY at.address_type
HEREDOC;
			
			$addresses = mysql_query($sql);
			while( $addresses && $address = mysql_fetch_object($addresses) ){
				$this->customers[ $row->customer_id ]->info->addresses[ $address->address_id ]->address_id = $address->address_id;
				$this->customers[ $row->customer_id ]->info->addresses[ $address->address_id ]->address_type_id = $address->address_type_id;
				$this->customers[ $row->customer_id ]->info->addresses[ $address->address_id ]->address_type = $address->address_type;
				$this->customers[ $row->customer_id ]->info->addresses[ $address->address_id ]->address_one = $address->address_one;
				$this->customers[ $row->customer_id ]->info->addresses[ $address->address_id ]->address_two = $address->address_two;
				$this->customers[ $row->customer_id ]->info->addresses[ $address->address_id ]->city = $address->city;
				$this->customers[ $row->customer_id ]->info->addresses[ $address->address_id ]->state = $address->state;
				$this->customers[ $row->customer_id ]->info->addresses[ $address->address_id ]->zip = $address->zip;
			}
		}
	}
	
	public function get_customer( $id ){
		$sql = <<<HEREDOC
			SELECT u.user_id, c.customer_id, c.customer_name, c.date_added, c.date_updated
			FROM customers c
			INNER JOIN customers_to_users u
			ON c.customer_id = u.customer_id
			WHERE c.customer_id = {$id}
			LIMIT 1
HEREDOC;
		$info = mysql_query($sql);
		$return = mysql_fetch_object($info);
		
		if( $return->customer_id ){
			$sql = <<<HEREDOC
				SELECT first_name, last_name, email, mobile, phone, fax
				FROM customer_contacts 
				WHERE customer_id = {$id}
				ORDER BY last_name, first_name
HEREDOC;
			$return->contacts = mysql_query($sql);
			$return->has_contacts = (mysql_num_rows($return->contacts)>=1) ? true : false;
		}
		
		if( $return->customer_id ){
			$sql = <<<HEREDOC
				SELECT a.address_type as address_type_id, at.address_type, address_one, address_two, city, state, zip
				FROM customer_addresses a
				INNER JOIN address_types at
				ON a.address_type = at.address_type_id
				WHERE a.customer_id = {$id}
				ORDER BY at.address_type
HEREDOC;
			$return->addresses = mysql_query($sql);
			$return->has_addresses = (mysql_num_rows($return->addresses)>=1) ? true : false;
		}
		
		return $return;
	}
	
	
	
	
## Search for printers
	public function get_printers( $customer_id = false, $id = false ){
		if(!empty($customer_id)) $this->customer_id = $customer_id;
		if(empty($this->customer_id)){
			barf('ERROR: Trying to get printers without selecting a customer.');	exit;
		}
		
		$sql = "SELECT DISTINCT p.printer_id, p.printer, p.printer_qty, p.org_printer_id FROM printers p";
		$sql .= (!empty($this->where_printer_type)) ? "\nLEFT JOIN printers_to_types pt ON pt.printer_id = p.printer_id" : '';
		$sql .= (!empty($this->where_printer_type)) ? "\nLEFT JOIN printer_types t ON pt.type_id = t.type_id" : '';
		$sql .= (!empty($this->where_printer_manufacturer)) ? "\nLEFT JOIN printers_to_manufacturers pm ON pm.printer_id = p.printer_id" : '';
		$sql .= (!empty($this->where_printer_manufacturer)) ? "\nLEFT JOIN manufacturers m ON pm.manufacturer_id = m.manufacturer_id" : '';
		$sql .= "\nWHERE p.customer_id = {$this->customer_id}";
		$sql .= (!empty($id)) ? "\nAND p.printer_id = {$id}" : '';
		$sql .= (!empty($this->where_printer_type)) ? ("\nAND (t.type_id = ".implode(' OR t.type_id = ', $this->where_printer_type) .")") : '';
		$sql .= (!empty($this->where_printer_manufacturer)) ? ("\nAND (m.manufacturer_id = ".implode(' OR m.manufacturer_id = ', $this->where_printer_manufacturer) .")") : '';
		$sql .= (!empty($this->where_printer_manufacturer)) ? "\nORDER BY m.manufacturer_name ASC, p.printer ASC" : "\nORDER BY p.printer ASC";
		
		unset($this->where_printer_type);	unset($this->where_printer_manufacturer);

		$printers = mysql_query($sql) or die(mysql_error());
		if( $printers && mysql_num_rows($printers) <= 0 ){
			$this->customers[ $this->customer_id ]->has_printers = false;
			return false;
		} else {
			$this->customers[ $this->customer_id ]->has_printers = true;
			$this->customers[ $this->customer_id ]->printers = array();
			
			while( $row = mysql_fetch_object($printers) ){
				$this->customers[ $this->customer_id ]->printers[ $row->printer_id ]->printer_id = $row->printer_id;
				$this->customers[ $this->customer_id ]->printers[ $row->printer_id ]->printer = $row->printer;
				$this->customers[ $this->customer_id ]->printers[ $row->printer_id ]->printer_qty = $row->printer_qty;
				$this->customers[ $this->customer_id ]->printers[ $row->printer_id ]->org_printer_id = $row->org_printer_id;
				$this->get_printer_manufacturers( $row->printer_id );
				$this->get_printer_types( $row->printer_id );
				$this->get_cartridges( $row->printer_id );
			}
			return true;
		}
	}

## Alias for get_printers()
## Search for just one printer
	public function get_printer( $id ){
		$this->get_printers( $this->customer_id, $id );
	}
	
## Extends get_printers()
## Sets the printer type WHERE clause
	public function get_printers_with_type( $types ){
		$this->where_printer_type = (is_array($types)) ? $types : explode(',', $types);
	}
	
## Extends get_printers()
## Sets the printer manufacturer WHERE clause
	public function get_printers_with_manufacturer( $manufacturers ){
		$this->where_printer_manufacturer = (is_array($manufacturers)) ? $manufacturers : explode(',', $manufacturers);
	}
	
## Extends get_printers()
## Gets and adds manufacturers to printer information
	private function get_printer_manufacturers( $id ){
		
		$id = db_scrub($id, 'int');
		$sql = <<<HEREDOC
			SELECT DISTINCT m.manufacturer_id, m.manufacturer_name, m.org_manufacturer_id
				FROM manufacturers  m
			INNER JOIN printers_to_manufacturers mp
				ON m.manufacturer_id = mp.manufacturer_id
			WHERE mp.printer_id = $id
HEREDOC;
		
		$mans = mysql_query($sql) or die(mysql_error());
		if( $mans && mysql_num_rows($mans) <= 0 ){
			return false;
		} else {
			$this->customers[ $this->customer_id ]->printers[ $id ]->num_manufacturers = mysql_num_rows($mans);
			$this->customers[ $this->customer_id ]->printers[ $id ]->manufacturers = array();	$x=0;
			while( ($row = mysql_fetch_object($mans)) && ($x+=1) ){
				$this->customers[ $this->customer_id ]->printers[ $id ]->manufacturers[$x]->manufacturer_id = $row->manufacturer_id;
				$this->customers[ $this->customer_id ]->printers[ $id ]->manufacturers[$x]->manufacturer_name = $row->manufacturer_name;
				$this->customers[ $this->customer_id ]->printers[ $id ]->manufacturers[$x]->org_manufacturer_id = $row->org_manufacturer_id;
			}
			return true;
		}
	}
	
## Extends get_printers()
## Gets and adds type to printer information
	private function get_printer_types( $id ){
		$id = db_scrub($id, 'int');
		$sql = <<<HEREDOC
			SELECT DISTINCT t.type_id, t.printer_type, t.org_type_id
				FROM printer_types t
			INNER JOIN printers_to_types pt
				ON t.type_id = pt.type_id
			WHERE pt.printer_id = $id
HEREDOC;
		
		$types = mysql_query($sql) or die(mysql_error());
		if( $types && mysql_num_rows($types) <= 0 ){
			return false;
		} else {
			$this->customers[ $this->customer_id ]->printers[ $id ]->num_types = mysql_num_rows($types);
			$this->customers[ $this->customer_id ]->printers[ $id ]->types = array();	$x=0;
			while( ($row = mysql_fetch_object($types)) && ($x+=1) ){
				$this->customers[ $this->customer_id ]->printers[ $id ]->types[$x]->type_id = $row->type_id;
				$this->customers[ $this->customer_id ]->printers[ $id ]->types[$x]->printer_type = $row->printer_type;
				$this->customers[ $this->customer_id ]->printers[ $id ]->types[$x]->org_type_id = $row->org_type_id;
			}
			return true;
		}
	}

## Extends get_printers()
## Gets all cartridges that fit a given printer
	public function get_cartridges( $id ){
		$id = db_scrub($id, 'int');
		$sql = <<<HEREDOC
			SELECT DISTINCT c.cartridge_id, c.cartridge, c.shorthand, c.price_retail, c.price_user, c.org_cartridge_id
				FROM printers p
			INNER JOIN cartridges_to_printers cp
				ON cp.printer_id = p.printer_id
			INNER JOIN cartridges c
				ON cp.cartridge_id = c.cartridge_id
			WHERE p.printer_id = $id
				AND p.customer_id = $this->customer_id
				AND c.customer_id = $this->customer_id
HEREDOC;
		
		$cartridges = mysql_query($sql) or die(mysql_error());
		if( $cartridges && mysql_num_rows($cartridges) <= 0 ){
			$this->customers[ $this->customer_id ]->printers[ $id ]->has_cartridges = false;
			return false;
		} else {
			$this->customers[ $this->customer_id ]->printers[ $id ]->has_cartridges = true;
			$this->customers[ $this->customer_id ]->printers[ $id ]->cartridges = array();
			
			while( $row = mysql_fetch_object($cartridges) ){
				$this->customers[ $this->customer_id ]->printers[ $id ]->cartridges[ $row->cartridge_id ]->cartridge_id = $row->cartridge_id;
				$this->customers[ $this->customer_id ]->printers[ $id ]->cartridges[ $row->cartridge_id ]->cartridge = $row->cartridge;
				$this->customers[ $this->customer_id ]->printers[ $id ]->cartridges[ $row->cartridge_id ]->shorthand = $row->shorthand;
				$this->customers[ $this->customer_id ]->printers[ $id ]->cartridges[ $row->cartridge_id ]->price_retail = $row->price_retail;
				$this->customers[ $this->customer_id ]->printers[ $id ]->cartridges[ $row->cartridge_id ]->price_user = $row->price_user;
				$this->customers[ $this->customer_id ]->printers[ $id ]->cartridges[ $row->cartridge_id ]->org_cartridge_id = $row->org_cartridge_id;
				$this->get_cartridge_manufacturers( $row->cartridge_id, $id );
				$this->get_cartridge_types( $row->cartridge_id, $id );
			}
			return true;
		}
	}

## Extends get_printers()
## Extends get_cartridges()
## Gets and adds manufacturers to cartridge information
	private function get_cartridge_manufacturers( $id, $printer_id ){
		$id = db_scrub($id, 'int');
		$sql = <<<HEREDOC
			SELECT DISTINCT m.manufacturer_id, m.manufacturer_name, m.org_manufacturer_id
				FROM manufacturers  m
			INNER JOIN cartridges_to_manufacturers mc
				ON m.manufacturer_id = mc.manufacturer_id
			WHERE mc.cartridge_id = $id
HEREDOC;
		
		$mans = mysql_query($sql) or die(mysql_error());
		if( $mans && mysql_num_rows($mans) <= 0 ){
			$this->customers[ $this->customer_id ]->printers[ $printer_id ]->cartridges[ $id ]->num_manufacturers = 0;
			return false;
		} else {
			$this->customers[ $this->customer_id ]->printers[ $printer_id ]->cartridges[ $id ]->num_manufacturers = mysql_num_rows($mans);
			$this->customers[ $this->customer_id ]->printers[ $printer_id ]->cartridges[ $id ]->manufacturers = array();	$x=0;
			while( ($row = mysql_fetch_object($mans)) && ($x+=1) ){
				$this->customers[ $this->customer_id ]->printers[ $printer_id ]->cartridges[ $id ]->manufacturers[$x]->manufacturer_id = $row->manufacturer_id;
				$this->customers[ $this->customer_id ]->printers[ $printer_id ]->cartridges[ $id ]->manufacturers[$x]->manufacturer_name = $row->manufacturer_name;
				$this->customers[ $this->customer_id ]->printers[ $printer_id ]->cartridges[ $id ]->manufacturers[$x]->org_manufacturer_id = $row->org_manufacturer_id;
			}
			return true;
		}
	}

## Extends get_printers()
## Extends get_cartridges()
## Gets and adds type to cartridge information
	private function get_cartridge_types( $id, $printer_id ){
		$id = db_scrub($id, 'int');
		$sql = <<<HEREDOC
			SELECT DISTINCT t.cart_type_id, t.cartridge_type, t.org_cart_type_id
				FROM cartridge_types t
			INNER JOIN cartridges_to_types ct
				ON t.cart_type_id = ct.cart_type_id
			WHERE ct.cartridge_id = $id
HEREDOC;
		
		$types = mysql_query($sql) or die(mysql_error());
		if( $types && mysql_num_rows($types) <= 0 ){
			$this->customers[ $this->customer_id ]->printers[ $printer_id ]->cartridges[ $id ]->num_types = 0;
			return false;
		} else {
			$this->customers[ $this->customer_id ]->printers[ $printer_id ]->cartridges[ $id ]->num_types = mysql_num_rows($types);
			$this->customers[ $this->customer_id ]->printers[ $printer_id ]->cartridges[ $id ]->types = array();	$x=0;
			while( ($row = mysql_fetch_object($types)) && ($x+=1) ){
				$this->customers[ $this->customer_id ]->printers[ $printer_id ]->cartridges[ $id ]->types[$x]->cart_type_id = $row->cart_type_id;
				$this->customers[ $this->customer_id ]->printers[ $printer_id ]->cartridges[ $id ]->types[$x]->cartridge_type = $row->cartridge_type;
				$this->customers[ $this->customer_id ]->printers[ $printer_id ]->cartridges[ $id ]->types[$x]->org_cart_type_id = $row->org_cart_type_id;
			}
			return true;
		}
	}
	
	
	
## Searches the database for the printer, and suggests cartridges 
## 		that the main DB has that the customer does not have.
	public function suggest_cartridges( $customer_id, $printer_id ){
		if( empty($this->customers[ $customer_id ]->printers[ $printer_id ]) )	$this->get_printers( $customer_id );
		$printer = $this->customers[ $customer_id ]->printers[ $printer_id ];
		
		if( count($printer->cartridges) > 0){
			foreach( $printer->cartridges as $id => $cartridge ){
				$current_cartridges[] = $cartridge->org_cartridge_id;
			}
		} else {
			$current_cartridges = array();
		}
		
		$Search = new Search();
		$found_old = $Search->for_printer( $printer->org_printer_id, true, true );
		if( $found_old ){
			$Search->get_cartridges_that_fit( $printer->org_printer_id );
			if( $Search->has_compatible_cartridges ){
				while( $row = mysql_fetch_object( $Search->compatible_cartridges ) ){
					if( !in_array($row->cartridge_id, $current_cartridges) ){
						$Search->get_manufacturers_of_cartridge( $row->cartridge_id  );
						$suggested[ $row->cartridge_id ]->num_manufacturers = mysql_num_rows($Search->manufacturers_of_cartridge);
						$Search->get_types_of_cartridge( $row->cartridge_id  );
						$suggested[ $row->cartridge_id ]->num_types = mysql_num_rows($Search->types_of_cartridge);
						
						$suggested[ $row->cartridge_id ]->cartridge = $row->cartridge;
						$suggested[ $row->cartridge_id ]->shorthand = $row->shorthand;
						$suggested[ $row->cartridge_id ]->price_retail = $row->price_retail;
						$suggested[ $row->cartridge_id ]->price_user = $row->price_user;
						
						while( $mans = mysql_fetch_object($Search->manufacturers_of_cartridge) ){
							$suggested[ $row->cartridge_id ]->manufacturers[ $mans->manufacturer_id ]->manufacturer_name = $mans->manufacturer_name;
						}
						
						while( $types = mysql_fetch_object($Search->types_of_cartridge) ){
							$suggested[ $row->cartridge_id ]->types[ $types->type_id ]->cartridge_type = $types->type;
						}
					}
				}
				db_connect('customers');
				return ( !empty($suggested) ) ? $suggested : false;
			} else {
				db_connect('customers');
				return false;
			}
		} else {
			db_connect('customers');
			return false;
		}
	}
	
	
	
	
## Adds cartridge to customer account
	public function add_cartridge( $cartridge, $printer_id, $customer_id, $custom=false ){
		if( !is_object($cartridge) ){
			$Search = new Search();
			$Search->for_cartridge( $cartridge, true, true );
			if( $Search->cartridges_were_found ){
				while( $row = mysql_fetch_object($Search->found_cartridges) ){
					$Search->get_manufacturers_of_cartridge( $row->cartridge_id  );
					$Search->get_types_of_cartridge( $row->cartridge_id  );
					
					$price = str_replace('$', '', $price);
					$price = db_scrub($price, 'int');
					
					unset($cartridge);
					$cartridge->cartridge_id = $row->cartridge_id;
					$cartridge->cartridge = $row->cartridge;
					$cartridge->shorthand = $row->shorthand;
					$cartridge->price_retail = $price;
					$cartridge->price_user  = $row->price_user ;
					
					if($custom->cartridge) $cartridge->cartridge = db_scrub($custom->cartridge);
					if($custom->shorthand) $cartridge->shorthand = db_scrub($custom->shorthand);
					if($custom->price_retail) $cartridge->price_retail = db_scrub($custom->price_retail, 'int');
					if($custom->price_user) $cartridge->price_user = db_scrub($custom->price_user, 'int');
					
					while( $row = mysql_fetch_object( $Search->manufacturers_of_cartridge ) ){
						$cartridge->manufacturers[ $row->manufacturer_id ]->org_manufacturer_id = $row->manufacturer_id;
						$cartridge->manufacturers[ $row->manufacturer_id ]->manufacturer_name = $row->manufacturer_name;
					}
					
					while( $row = mysql_fetch_object( $Search->types_of_cartridge ) ){
						$cartridge->types[ $row->type_id ]->org_type_id = $row->type_id;
						$cartridge->types[ $row->type_id ]->type = $row->type;
					}
				}
			}
		}
		
		db_connect('customers');
		
		$sql = 'INSERT INTO cartridges VALUES(';
		$sql .= "{$cartridge->cartridge_id}, ";
		$sql .= "{$customer_id}, ";
		$sql .= "null, ";
		$sql .= "'{$cartridge->cartridge}', ";
		$sql .= "'{$cartridge->shorthand}', ";
		$sql .= "{$cartridge->price_retail}, ";
		$sql .= "{$cartridge->price_user}";
		$sql .= ");";
		
		mysql_query($sql) or die(__LINE__ .'<br />'. mysql_error() .'<br />'. $sql);
		$new_cart_id = mysql_insert_id();
		
		foreach( $cartridge->manufacturers as $manufacturer_id => $manufacturer ){
			if( !empty($manufacturer->org_manufacturer_id) ){
				$man_exists = mysql_query("SELECT * FROM manufacturers WHERE manufacturer_name = '{$manufacturer->manufacturer_name}' AND customer_id = '{$customer_id}' LIMIT 1");
				if( mysql_num_rows($man_exists) <= 0 ){
					$sql = 'INSERT INTO manufacturers VALUES(';
					$sql .= "{$manufacturer->org_manufacturer_id}, ";
					$sql .= "{$customer_id}, ";
					$sql .= "null, ";
					$sql .= "'{$manufacturer->manufacturer_name}' ";
					$sql .= ");";

					mysql_query($sql) or die(__LINE__ .'<br />'. mysql_error() .'<br />'. $sql);
					$man_id = mysql_insert_id();
				} else {
					$man_id = mysql_result($man_exists, 0, 'manufacturer_id');
				}
				
				$sql = 'INSERT INTO cartridges_to_manufacturers  VALUES(';
				$sql .= "{$customer_id}, ";
				$sql .= "{$new_cart_id}, ";
				$sql .= "{$man_id}";
				$sql .= ");";
				
				mysql_query($sql) or die(__LINE__ .'<br />'. mysql_error() .'<br />'. $sql);
			}
		}
		
		foreach( $cartridge->types as $type_id => $type ){
			if( !empty($manufacturer->org_manufacturer_id) ){
				$type_exists = mysql_query("SELECT * FROM cartridge_types WHERE cartridge_type = '{$type->type}' AND customer_id = '{$customer_id}' LIMIT 1");
				if( mysql_num_rows($type_exists) <= 0 ){
					$sql = 'INSERT INTO cartridge_types VALUES(';
					$sql .= "{$type_id}, ";
					$sql .= "{$customer_id}, ";
					$sql .= "null, ";
					$sql .= "'{$type->type}'";
					$sql .= ");";

					mysql_query($sql) or die(__LINE__ .'<br />'. mysql_error() .'<br />'. $sql);
					$new_type_id = mysql_insert_id();
				} else {
					$new_type_id = mysql_result($type_exists, 0, 'cart_type_id');
				}
				
				$sql = 'INSERT INTO cartridges_to_types VALUES(';
				$sql .= "{$customer_id}, ";
				$sql .= "{$new_cart_id}, ";
				$sql .= "{$new_type_id}";
				$sql .= ");";

				mysql_query($sql) or die(__LINE__ .'<br />'. mysql_error() .'<br />'. $sql);
			}
		}
		
		$sql = 'INSERT INTO cartridges_to_printers VALUES(';
		$sql .= "{$customer_id}, ";
		$sql .= "{$new_cart_id}, ";
		$sql .= "{$printer_id}";
		$sql .= ");";
		
		mysql_query($sql) or die(__LINE__ .'<br />'. mysql_error() .'<br />'. $sql);
	}
	
	function num_printers( $id=false ){
		if(empty($id)) $id = $this->customer_id;
		if( !is_array($this->num_printers) || !array_key_exists($id, $this->num_printers) ){
			$sql = mysql_query("SELECT printer_id FROM printers WHERE customer_id = ".db_scrub($id,'int'));
			$this->num_printers[$id] = mysql_num_rows($sql);
		}
		return $this->num_printers[$id];
	}
	
	function num_cartridges( $id=false ){
		if(empty($id)) $id = $this->customer_id;
		if( !is_array($this->num_cartridges) || !array_key_exists($id, $this->num_cartridges) ){
			$sql = mysql_query("SELECT cartridge_id FROM cartridges WHERE customer_id = ".db_scrub($id,'int'));
			$this->num_cartridges[$id] = mysql_num_rows($sql);
		}
		return $this->num_cartridges[$id];
	}
	
	function num_proposals( $id=false ){
		if(empty($id)) $id = $this->customer_id;
		if( !is_array($this->num_proposals) || !array_key_exists($id, $this->num_proposals) ){
			db_connect('proposals');
			$sql = mysql_query("SELECT proposal_id FROM proposals_to_customers WHERE customer_id = ".db_scrub($id,'int'));
			$this->num_proposals[$id] = mysql_num_rows($sql);
			db_connect('customers');
		}
		return $this->num_proposals[$id];
	}
	
	function num_invoices( $id=false ){
		if(empty($id)) $id = $this->customer_id;
		if( !is_array($this->num_invoices) || !array_key_exists($id, $this->num_invoices) ){
			db_connect('invoices');
			$sql = mysql_query("SELECT invoice_id FROM invoices_to_customers WHERE customer_id = ".db_scrub($id,'int'));
			$this->num_invoices[$id] = mysql_num_rows($sql);
			db_connect('customers');
		}
		return $this->num_invoices[$id];
	}
}
?>