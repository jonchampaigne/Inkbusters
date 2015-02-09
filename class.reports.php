<?php
final class Reports{
	
	public $report_by;
	public $report_start;
	public $report_end;
	
	protected $db_invoices = DB_DATABASE_INVOICES;
	
	## __CONSTRUCT
	public function __construct(){
		return true;
	}
	
	public function cartridge_usage_overall( $customer_id ){
		$customer_id = db_scrub($customer_id, 'int');
		
		$sql = <<<HEREDOC
SELECT SUM(c.qty) as num, c.cartridge
FROM {$this->db_invoices}.cartridges c
LEFT JOIN {$this->db_invoices}.customers_to_customers ctc
	ON c.customer_id = ctc.customer_id
LEFT JOIN {$this->db_invoices}.voided_invoices vi
	ON vi.invoice_id = c.customer_id
WHERE ctc.real_customer_id = '{$customer_id}'
	AND c.qty > 0
	AND vi.date_time <=> NULL
GROUP BY c.cartridge DESC
ORDER BY num DESC, c.cartridge ASC
HEREDOC;
		$cartridges = mysql_query($sql) or my_mysql_error($sql);
		return ($cartridges && mysql_num_rows($cartridges)>0) ? $cartridges : false;
	}
	
	public function set_time_constraints( $by='month', $start=false, $end=false ){
		$this->report_by = $by;
		if( !$start && !$end ){
			$this->report_start = date('Y-m-01', strtotime('today -11 months'));
			$this->report_end = date('Y-m-t');
		} elseif( $start && !$end ){
			$this->report_start = date('Y-m-01', strtotime($start));
			$this->report_end = date('Y-m-t', strtotime($start.' +11 months'));
		} elseif( $start && $end ){
			if( date('Y-m-t', strtotime($end)) > date('Y-m-t', strtotime($start.' +11 months')) ) $end = date('Y-m-t', strtotime($start.' +11 months'));
			$this->report_start = date('Y-m-01', strtotime($start));
			$this->report_end = date('Y-m-t', strtotime($end));
		}
	}
	
	public function cartridge_usage_by_time( $customer_id, $cartridge, $by='month', $start=false, $end=false ){
		$customer_id = db_scrub($customer_id, 'int');
		$cartridge = db_scrub($cartridge);
		if($cartridge_id) $cartridge_id = db_scrub($cartridge_id, 'int');
		
		if( !$this->report_by ) $this->set_time_constraints( $by, $start, $end );
		
		if( $this->report_by=='quarter' ){
			$select = ",\n\tIF( MONTH(i.invoice_date)<=3, 1, 
			IF( (MONTH(i.invoice_date)>=4 AND MONTH(i.invoice_date)<=6), 2, 
			IF( (MONTH(i.invoice_date)>=7 AND MONTH(i.invoice_date)<=9), 3, 
			4 ) ) ) as quarter\n";
		}
		
		$where[] = "\tAND i.invoice_date >= '".$this->report_start."'";
		$where[] = "\tAND i.invoice_date <= '".$this->report_end."'";
		
		$sql = "SELECT c.cartridge, YEAR(i.invoice_date) as year, MONTH(i.invoice_date) as month, SUM(c.qty) as num\n";
		if($select) $sql .= $select;
		$sql .= <<<HEREDOC
FROM {$this->db_invoices}.cartridges c
LEFT JOIN {$this->db_invoices}.customers_to_customers ctc
	ON c.customer_id = ctc.customer_id
LEFT JOIN {$this->db_invoices}.voided_invoices vi
	ON vi.invoice_id = c.customer_id
LEFT JOIN {$this->db_invoices}.invoices i
	ON i.invoice_id = c.customer_id
WHERE ctc.real_customer_id = '{$customer_id}'
	AND c.qty > 0
	AND vi.date_time <=> NULL
	AND c.cartridge LIKE '{$cartridge}'
HEREDOC;
		if(is_array($where)) $sql .= "\n".implode("\n", $where);
		if( $this->report_by=='month' ){
			$sql .= "\nGROUP BY year, month, cartridge";
			$sql .= "\nORDER BY year ASC, month ASC, num DESC";
		} elseif( $this->report_by=='quarter' ){
			$sql .= "\nGROUP BY quarter";
			$sql .= "\nORDER BY quarter ASC";
		}

		$cartridge = mysql_query($sql) or my_mysql_error($sql);
		return ($cartridge && mysql_num_rows($cartridge)>0) ? $cartridge : false;
	}
	
	public function earliest( $customer_id ){
		$customer_id = db_scrub($customer_id, 'int');
		
		$sql = <<<HEREDOC
SELECT i.invoice_date
FROM {$this->db_invoices}.cartridges c
LEFT JOIN {$this->db_invoices}.customers_to_customers ctc
	ON c.customer_id = ctc.customer_id
LEFT JOIN {$this->db_invoices}.voided_invoices vi
	ON vi.invoice_id = c.customer_id
LEFT JOIN {$this->db_invoices}.invoices i
	ON i.invoice_id = c.customer_id
WHERE ctc.real_customer_id = '{$customer_id}'
	AND c.qty > 0
	AND vi.date_time <=> NULL
GROUP BY c.cartridge DESC
ORDER BY i.invoice_date ASC
LIMIT 1
HEREDOC;
		$date = mysql_query($sql) or my_mysql_error($sql);
		return ($date && mysql_num_rows($date)>0) ? $date : false;
	}
}
?>