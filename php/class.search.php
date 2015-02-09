<?php
class Search{
	public	$found_printers;
	public	$printers_were_found;
	public	$manufacturers_of_printer;
	public	$types_of_printer;
	public	$where_printer_type;
	public	$where_printer_manufacturer;
	public	$include_printer_manufacturer;
	
	public	$found_cartridges;
	public	$cartridges_were_found;
	public	$manufacturers_of_cartridge;
	public	$types_of_cartridge;
	public	$where_cartridge_type;
	public	$where_cartridge_manufacturer;
	public	$include_cartridge_manufacturer;
	
	public	$compatible_cartridges;
	public	$has_compatible_cartridges;
	
	public	$compatible_printers;
	public	$has_compatible_printers;
	
	## __CONSTRUCT
	public function __construct(){
		db_connect();
		return true;
	}
	
	## Search for printers
	public function for_printer( $printer, $exact=false, $by_id=false ){
		$printer = db_scrub($printer, 'string');
		$printer = str_ireplace(array('and ','or ','the ','all ',' and',' or',' the',' all'), '', trim($printer));
		$org_printer = $printer;
		
		$mans = mysql_query("SELECT DISTINCT manufacturer_id, manufacturer_name FROM manufacturers");
		
		while( $row = mysql_fetch_object($mans) ){
			if( strstr($printer, $row->manufacturer_name) ){
				$this->where_printer_manufacturer = (!empty($this->where_printer_manufacturer)) ? ($this->where_printer_manufacturer.','.$row->manufacturer_id) : $row->manufacturer_id;
				$printer = trim(str_ireplace(trim($row->manufacturer_name), '', $printer));
			}
		}
		
		$sql = 'SELECT DISTINCT p.printer_id, p.printer';
		$sql .= ($this->include_printer_manufacturer) ? ", m.manufacturer_id, m.manufacturer_name" : '';
		$sql .= <<<HEREDOC
				FROM printers p
			LEFT JOIN printers_to_types pt
				ON pt.printer_id = p.printer_id
			LEFT JOIN printer_types t
				ON pt.type_id = t.type_id
			LEFT JOIN printers_to_manufacturers pm
				ON pm.printer_id = p.printer_id
			LEFT JOIN manufacturers m
				ON pm.manufacturer_id = m.manufacturer_id
			WHERE p.printer_active = 1
				AND IF( t.type_id IS NOT NULL, t.type_active = 1, 1)
				AND IF( m.manufacturer_id IS NOT NULL, m.manufacturer_active = 1, 1)
HEREDOC;
		if( $by_id == true ){
			$sql .= "\nAND p.printer_id = {$printer}";
		} else {
			$sql .= ($exact) ? "\nAND p.printer LIKE '{$printer}'" : " AND (p.printer LIKE '%{$printer}%' OR p.printer LIKE '%{$org_printer}%')";
		}
		
		if( $this->where_printer_type!='all') $sql .= (!empty($this->where_printer_type)) ? ("\nAND (t.type_id = '".implode(' OR t.type_id = ', explode(',', $this->where_printer_type))."')") : '';
		if( $this->where_printer_manufacturer!='all') $sql .= (!empty($this->where_printer_manufacturer)) ? ("\nAND (m.manufacturer_id = '".implode(' OR m.manufacturer_id = ', explode(',', $this->where_printer_manufacturer))."')") : '';
		$sql .= ($this->include_printer_manufacturer) ? "\nORDER BY m.manufacturer_name ASC, p.printer ASC" : "\nORDER BY m.manufacturer_name ASC, p.printer ASC";
		
		//barf( $sql );
		
		$this->found_printers = mysql_query($sql) or die(mysql_error());
		if( $this->found_printers && mysql_num_rows($this->found_printers) <= 0 ){
			$this->printers_were_found = false;
			return false;
		} else {
			$this->printers_were_found = true;
			return true;
		}
	}
	
	
	## Get all cartridges associated with a printer
	public function get_cartridges_that_fit( $id ){
		$id = db_scrub($id, 'int');
		$sql = <<<HEREDOC
			SELECT DISTINCT c.cartridge_id, c.cartridge, c.shorthand, c.price_retail, c.price_user
				FROM printers p
			INNER JOIN cartridges_to_printers cp
				ON cp.printer_id = p.printer_id
			INNER JOIN cartridges c
				ON cp.cartridge_id = c.cartridge_id
			WHERE p.printer_id = $id
				AND p.printer_active = 1
				AND c.cartridge_active = 1
HEREDOC;
		
		$this->compatible_cartridges = mysql_query($sql) or die(mysql_error());
		if( $this->compatible_cartridges && mysql_num_rows($this->compatible_cartridges) <= 0 ){
			$this->has_compatible_cartridges = false;
			return false;
		} else {
			$this->has_compatible_cartridges = true;
			return true;
		}
	}
	
	public function get_manufacturers_of_printer( $id ){
		$id = db_scrub($id, 'int');
		$sql = <<<HEREDOC
			SELECT DISTINCT m.manufacturer_id, m.manufacturer_name
				FROM manufacturers  m
			INNER JOIN printers_to_manufacturers mp
				ON m.manufacturer_id = mp.manufacturer_id
			WHERE mp.printer_id = $id
				AND m.manufacturer_active = 1
HEREDOC;
		
		//barf( $sql );
		
		$this->manufacturers_of_printer = mysql_query($sql) or die(mysql_error());
		if( $this->manufacturers_of_printer && mysql_num_rows($this->manufacturers_of_printer) <= 0 ){
			return false;
		} else {
			return true;
		}
	}
	
	public function get_types_of_printer( $id ){
		$id = db_scrub($id, 'int');
		$sql = <<<HEREDOC
			SELECT DISTINCT t.type_id, t.printer_type
				FROM printer_types t
			INNER JOIN printers_to_types pt
				ON t.type_id = pt.type_id
			WHERE pt.printer_id = $id
				AND t.type_active = 1
HEREDOC;
		
		//barf( $sql );
		
		$this->types_of_printer = mysql_query($sql) or die(mysql_error());
		if( $this->types_of_printer && mysql_num_rows($this->types_of_printer) <= 0 ){
			return false;
		} else {
			return true;
		}
	}
	
	
	## Search for cartridges
	public function for_cartridge( $cartridge, $exact=false, $by_id=false ){
		$cartridge = db_scrub($cartridge, 'string');
		$sql = 'SELECT DISTINCT c.cartridge_id, c.cartridge, c.shorthand, c.price_retail, c.price_user';
		$sql .= ($this->include_cartridge_manufacturer) ? ", m.manufacturer_id, m.manufacturer_name" : '';
		$sql .= <<<HEREDOC
				FROM cartridges c
			LEFT JOIN cartridges_to_types ct
				ON c.cartridge_id = ct.cartridge_id
			LEFT JOIN cartridge_types t
				ON ct.cart_type_id = t.cart_type_id
			LEFT JOIN cartridges_to_manufacturers cm
				ON cm.cartridge_id = c.cartridge_id
			LEFT JOIN manufacturers m
				ON cm.manufacturer_id = m.manufacturer_id
			WHERE c.cartridge_active = 1
				AND IF( t.cart_type_id IS NOT NULL, t.cart_type_active = 1, 1)
				AND IF( m.manufacturer_id IS NOT NULL, m.manufacturer_active = 1, 1)
HEREDOC;
		if( $by_id == true ){
			$sql .= "\nAND c.cartridge_id = {$cartridge}";
		} else {
			$sql .= ($exact) ? "\nAND c.cartridge LIKE '{$cartridge}'" : "\nAND c.cartridge LIKE '%{$cartridge}%'";
		}
		$sql .= (!empty($this->where_cartridge_type)) ? (" AND (t.cart_type_id = ".implode(' OR t.cart_type_id = ', explode(',', $this->where_cartridge_type)).")") : '';
		$sql .= (!empty($this->where_cartridge_manufacturer)) ? (" AND (m.manufacturer_id = ".implode(' OR m.manufacturer_id = ', explode(',', $this->where_cartridge_manufacturer)).")") : '';
		$sql .= "\nORDER BY c.cartridge DESC";
		
		//barf($sql);
		
		$this->found_cartridges = mysql_query($sql) or die(mysql_error());
		
		if( $this->found_cartridges && mysql_num_rows($this->found_cartridges) <= 0 ){
			$this->cartridges_were_found = false;
			return false;
		} else {
			$this->cartridges_were_found = true;
			return true;
		}
	}
	
	
	## Get all printers associated with a cartridge
	public function get_printers_that_fit( $id ){
		$id = db_scrub($id, 'int');
		$sql = <<<HEREDOC
			SELECT DISTINCT p.printer_id, p.printer
				FROM printers p
			INNER JOIN cartridges_to_printers cp
				ON cp.printer_id = p.printer_id
			INNER JOIN cartridges c
				ON cp.cartridge_id = c.cartridge_id
			WHERE c.cartridge_id = $id
				AND p.printer_active = 1
				AND c.cartridge_active = 1
HEREDOC;
		
		$this->compatible_printers = mysql_query($sql) or die(mysql_error());
		if( $this->compatible_printers && mysql_num_rows($this->compatible_printers) <= 0 ){
			$this->has_compatible_printers = false;
			return false;
		} else {
			$this->has_compatible_printers = true;
			return true;
		}
	}
	
	
	public function get_manufacturers_of_cartridge( $id ){
		$id = db_scrub($id, 'int');
		$sql = <<<HEREDOC
			SELECT DISTINCT m.manufacturer_id, m.manufacturer_name
				FROM manufacturers  m
			INNER JOIN cartridges_to_manufacturers mp
				ON m.manufacturer_id = mp.manufacturer_id
			WHERE mp.cartridge_id = $id
				AND m.manufacturer_active = 1
HEREDOC;
		
		//barf( $sql );
		
		$this->manufacturers_of_cartridge = mysql_query($sql) or die(mysql_error());
		if( $this->manufacturers_of_cartridge && mysql_num_rows($this->manufacturers_of_cartridge) <= 0 ){
			return false;
		} else {
			return true;
		}
	}
	
	
	public function get_types_of_cartridge( $id ){
		$id = db_scrub($id, 'int');
		$sql = <<<HEREDOC
			SELECT DISTINCT t.cart_type_id as type_id , t.cartridge_type  as type
				FROM cartridge_types  t
			INNER JOIN cartridges_to_types ct
				ON t.cart_type_id = ct.cart_type_id
			WHERE ct.cartridge_id = $id
				AND t.cart_type_active = 1
HEREDOC;
		
		//barf( $sql );
		
		$this->types_of_cartridge = mysql_query($sql) or die(mysql_error());
		if( $this->types_of_cartridge && mysql_num_rows($this->types_of_cartridge) <= 0 ){
			return false;
		} else {
			return true;
		}
	}
}
?>