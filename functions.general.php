<?php

/**
 * Magic function that will try to load a class if it isn't defined
 *
 * @param string $classname
 */
function __autoload( $classname )
{
	$file = strtolower($classname);
	include "class.{$file}.php";
}

/**
 * Function for debugging. Prints to screen a formatted version of the passed variable
 * 
 * @param mixed $var
 * @param bool $hidden
 * @return null
 */
function barf( $var, $hidden=false ){
	echo ($hidden) ? '<!-- ' : '<pre style="border: 1px solid #000000; border-left: none; background-color: #DDFFDD; color: #000000; font-family: \'Courier New\', Courier, mono; font-size: 11px; font-weight: bold; display: inline; max-width: 719px; max-height: 500px; overflow-x: hidden; overflow-y: auto; padding: 8px; position: absolute; top: 0px; left: 0px; filter: alpha(opacity=92); opacity: 0.92; text-align: left; z-order: 100;" ondblclick="this.style.display=\'none\'">' ;
	if (is_array($var) || is_object($var)) print_r($var); else var_dump($var);
	echo ($hidden) ? " -->" : "</pre>" ;
}

/**
 * Function for debugging. Emails a formatted version of the passed variable
 * 
 * @param mixed $var
 * @return null
 */
function hurl( $var, $addy=ADMIN_EMAIL )
{
	mail($addy, 'hurl results', print_r($var, true));
}

/**
 * Function to create a database connection.
 * 
 * @return database_resource
 */
function db_connect( $mode=false )
{
	@mysql_close();
	
	$result = mysql_connect(DB_LOCATION, DB_ACCOUNT, DB_PASSWORD);
	
	switch( $mode ){
		default:
		case 'main':
			return (!$result || !mysql_select_db(DB_DATABASE)) ? false : $result;
			break;
		case 'customers':
			return (!$result || !mysql_select_db(DB_DATABASE_CUSTOMERS)) ? false : $result;
			break;
		case 'proposals':
			return (!$result || !mysql_select_db(DB_DATABASE_PROPOSALS)) ? false : $result;
			break;
		case 'invoices':
			return (!$result || !mysql_select_db(DB_DATABASE_INVOICES)) ? false : $result;
			break;
	}
}

/**
 * Ensures user is coming in on secure port, redirects to $redir if not
 * 
 * @author Jared Markell
 * @version 1.1
 * @param string $redir
 * @return null
 */
function forcessl( $redir='' )
{
	if (empty($redir)) $redir = 'https://'.$_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI'];
	
	switch ($_SERVER['HTTPS']) {
		# Possible "true" values for HTTPS
		case 'on':
		case '1':
		case 1:
		case true:
			break;
		default:
			header("Location: $redir");
			exit;
	}
}

/**
 * Converts strings to htmlentity-safe strings from any var, including multideminsional arrays
 * for more info, visit http://www.php.net/manual/en/function.stripslashes.php
 * 
 * @version 1.0
 * @param mixed $value
 * @return mixed
 */
function htmlentities_deep( $value='' )
{
	return (is_array($value)) ?
		array_map('htmlentities_deep', $value):
		htmlentities($value);
}

/**
 * Strips slashes out of any var, including multideminsional arrays
 * for more info, visit http://www.php.net/manual/en/function.stripslashes.php
 * 
 * @version 1.0
 * @param mixed $value
 * @return mixed
 */
function stripslashes_deep( $value='' )
{
	return (is_array($value)) ?
		array_map('stripslashes_deep', $value):
		stripslashes($value);
}

/**
 * Removes inserted slashes from superglobal arrays on servers where magic quotes are on
 * 
 * 12/07/04: Added support for multi-deminisional arrays
 * 12/08/05: Now only runs once even if called twice, to prevent double strip slashing
 * 
 * @version 1.2
 * @return null
 */
function deslash(  )
{
	static $first_run = true;
	
	if ($first_run && get_magic_quotes_gpc()) {
		$_GET = stripslashes_deep($_GET);
		$_POST = stripslashes_deep($_POST);
		$_FILES = stripslashes_deep($_FILES);
		$_COOKIE = stripslashes_deep($_COOKIE);
	}
	
	$first_run = false;
}

/**
 * Converts various inputs to be exactly 1 or 0. Great for processing booleans in forms
 * 
 * @deprecated
 * @version 1.0
 * @param mixed $value
 * @return int
 */
function boolval( $value )
{
	$bool = (int)( ((float)$value > 0)
		||	($value === true)
		||	($value === 'true')
		||	($value === 'on')
		||	($value === 'yes')
		||	($value === 'checked')
		||	($value === 'accepted')
	);
	return $bool;
}

/**
 * Determine if a number is even or not
 * 
 * @deprecated
 * @param int $num
 * @return bool
 */
function is_even( $num )
{
	return (strpos($num/2, '.')===false);
}

/**
 * Correctly safely "scrubs" a variable for use with insertion into MySQL(only) database
 * 
 * @author Jared Markell
 * @version 1.1
 * @param mixed $var
 * @param string $cast
 * @return varies
 */
function db_scrub( $var, $cast='string' )
{
	switch ($cast) {
		case 'string':
		default:
			return mysql_real_escape_string($var);
		case 'bool':
		case 'boolean':
			if (is_numeric($var)) return (int)(bool)(float)$var;// helps make '0.0' return 0(false)
			if (is_bool($var)) return (int)$var;
			# If it's not numeric or boolean, check it as a string
			$truths = Array ( 'true', 'checked', 'selected', 'on' );
			return (int)in_array(strtolower($var), $truths, true);
		case 'int':
			return (int)$var;
		case 'float':
		case 'real':
		case 'decimal':
		case 'double':
			return (float)$var;
		case 'date':
			# If the date can't be converted in php, don't pass it to MySQL. Instead, pass empty string
			$stamp = strtotime($var);
			return ($stamp > 0) ? date('Y-m-d', $stamp) : '';
		case 'time':
			# If the date can't be converted in php, don't pass it to MySQL. Instead, pass empty string
			$stamp = strtotime($var);
			return ($stamp > 0) ? date('H:i:s', $stamp) : '';
		case 'datetime':
			# If the date can't be converted in php, don't pass it to MySQL. Instead, pass empty string
			$stamp = strtotime($var);
			return ($stamp > 0) ? date('Y-m-d H:i:s', $stamp) : '';
		case 'char':
			return mysql_real_escape_string($var{0});
	}
}

/**
 * Modify a variable in current query string, and return the modified query string. Accepts arrays for both $var and $val
 *
 * @param mixed $var
 * @param mixed $val
 * @param string $qstring
 * @return string
 */
function hax_query( $var, $val=null )
{
	# Required functions
	if (!function_exists('http_build_query') || !function_exists('stripslashes_deep')) return false;
	
	# Convert needle and haystack to arrays
	$var = (array)$var;
	$val = (array)$val;
	
	# Since GET can be modified, we're trying to stick with query string and rebuild array
	parse_str(stripslashes($_SERVER['QUERY_STRING']), $new_query);
	$new_query = stripslashes_deep($new_query);
	
	# Cycle through query and edit/add/remove
	foreach ($var as $key => $index) {
		if (isset($val[$key])) {
			$new_query[$index] = $val[$key];
		}
		else {
			unset ($new_query[$index]);
		}
	}
	
	return http_build_query($new_query);
}

/**
 * Makes an email spambot proof for public viewing.
 * 
 * @param string $oldaddy
 * @param bool $mailto
 * @return sring
 */
function spoof_email( $oldaddy, $mailto=false )
{
	if ($mailto) $oldaddy = "mailto:$oldaddy";
	
	$newaddy = '';// buffer
	for ( $i=0 ; $i < strlen($oldaddy) ; $i++ ) {
		$char = $oldaddy{$i};
		switch (true) {
			# Spoof this character if its any of the listed below, or randomly
			case (bool)rand(0,1):
			case $char === ':':
			case $char === '@':
			case $char === '.':
				$newaddy .= '&#'.ord($char).';';
				break;
			default:
				$newaddy .= $char;
		}
	}
	return $newaddy;
}

/**
 * Validates an email
 * 
 * @param string $email
 * @return bool
 */
function verify_email( $email )
{
	return (bool)eregi("^[a-zA-Z0-9\._-]+@+[a-zA-Z0-9\._-]+\.+[a-zA-Z0-9\-\.]+$", $email);
}

/**
* Returns the current full URL of the current executing script/page
*
* @param    none
* @return   string
* @access   public
*/
function get_current_url( $strip_query=false ) {
	$url = (!empty($_SERVER['HTTPS']) ? 'https://' : 'http://');
	$url.= $_SERVER['SERVER_NAME'];
	$url.= ($strip_query ? $_SERVER['PHP_SELF'] : $_SERVER['REQUEST_URI']);
	
	return $url;
}

/**
 * Removes the last word from a text block
 *
 * @param string $summary
 * @return string
 */
function kill_klingon( $summary ) {
	# Remove any white space, if there were 2 spaces together before the trim (after a sentence, per se)
	$summary = trim($summary);
	# Remove last chunklen
	$summary = substr($summary, 0, strrpos($summary, ' '));
	# Remove last char if it's not a digit or alpha char (sometimes you can get a comma or extra period)
	$summary = substr($summary, 0, -1).ereg_replace('[^[:digit:][:alpha:]]', '', substr($summary, -1));
	
	return $summary;
}

/**
 * Returns an english readable error reponse for upload errors.
 *
 * @param int $error_num
 * @return string
 */
function file_upload_error( $error_num )
{
	switch ($error_num) {
		case UPLOAD_ERR_OK :
			return 'The file uploaded successfully.';
		case UPLOAD_ERR_NO_FILE :
			return 'No file was uploaded.';
		case UPLOAD_ERR_INI_SIZE :
			return 'The uploaded file exceeds the max upload size ('.ini_get('upload_max_filesize').').';
		case UPLOAD_ERR_FORM_SIZE :
			return 'The uploaded file exceeds the max upload size specified by the form developer.';
		case UPLOAD_ERR_PARTIAL :
			return 'The file was only partially uploaded.';
		case UPLOAD_ERR_NO_TMP_DIR :
			return 'Missing a temporary folder to upload to.';
		case UPLOAD_ERR_CANT_WRITE :
			return 'Failed to write file to disk.';
		default:
			return 'Unknown error has occured.';
	}
}

/**
 * Converts an array of values to radio buttons
 *
 * @param array $options
 * @param string $input_name
 * @param string $separator
 * @param string $selected
 * @return string
 */
function array_to_radios( $options, $input_name, $separator="<br />\n", $selected='' )
{
	if (!is_array($options)) return false;
	
	$return = '';
	foreach ($options as $option) {
		$name = htmlentities($input_name);
		$value = htmlentities($option);
		$label = str_replace(' ', '_', $name.'-'.$value);
		
		$return .= '<nobr><input type="radio" name="'.$name.'" value="'.$value.'" id="'.$label.'" '.($option==$selected ? 'checked ' : '').'/>'.
			' <label for="'.$label.'">'.$value.'</label></nobr>'.$separator;
	}
	
	return $return;
}

/**
 * Converts an array of values to select list
 *
 * @param array $options
 * @param string $input_name
 * @param string $selected
 * @return string
 */
function array_to_select( $options, $input_name, $selected='' )
{
	if (!is_array($options)) return false;
	
	$return = '<select name="'.$input_name.'">';
	foreach ($options as $option) {
		$name = htmlentities($input_name);
		$value = htmlentities($option);
		
		$return .= '<option value="'.$value.'"'.($option==$selected ? ' selected' : '').'>'.$value.'</option>'."\n";
	}
	$return .= '</select>';
	
	return $return;
}

function convert_timezone( $from, $to, $input_time, $format ){
	$input_time = date('Y-m-d H:i:s', strtotime($input_time));
	
	$mytimezone = new DateTimeZone($from);
	$mydatetime = new DateTime($input_time, $mytimezone);
	if(!$to) return $mydatetime->format($format);
	
	$mytimezone2 = new DateTimeZone($to);
	$mydatetime->setTimezone($mytimezone2);
	return $mydatetime->format($format);
}

function echo_timezone_options( $selectedzone ) {
	$all = timezone_identifiers_list();
	
	$i = 0;
	foreach($all AS $zone) {
		$zone = explode('/',$zone);
		$zonen[$i]['continent'] = isset($zone[0]) ? $zone[0] : '';
		$zonen[$i]['city'] = isset($zone[1]) ? $zone[1] : '';
		$zonen[$i]['subcity'] = isset($zone[2]) ? $zone[2] : '';
		$i++;
	}
	
	asort($zonen);
	$structure = '';
	foreach($zonen AS $zone) {
		extract($zone);
		if($continent == 'Africa' || $continent == 'America' || $continent == 'Antarctica' || $continent == 'Arctic' || $continent == 'Asia' || $continent == 'Atlantic' || $continent == 'Australia' || $continent == 'Europe' || $continent == 'Indian' || $continent == 'Pacific') {
			if(!isset($selectcontinent)) {
				$structure .= '<optgroup label="'.$continent.'">'; // continent
			} elseif($selectcontinent != $continent) {
				$structure .= '</optgroup><optgroup label="'.$continent.'">'; // continent
			}
			
			if(isset($city) != ''){
				if (!empty($subcity) != ''){
					$city = $city . '/'. $subcity;
				}
				
				$mytimezone = new DateTimeZone($continent.'/'.$city);
				$mydatetime = new DateTime($input_time, $mytimezone);
				$details = reset($mytimezone->getTransitions());
				$abbr = strtoupper($details['abbr']);
				if( $mydatetime->format('I') ){
					foreach( timezone_transitions_get($mytimezone) as $tr ){
						if ($tr['ts'] > time()){
							$abbr = ($abbr==$tr['abbr']) ? $abbr : (strtoupper($tr['abbr']).'/'.$abbr);
							$gmt = number_format($tr['offset']/60/60, 2, ':', '');
							break;
						}
					}
				}
				
				$structure .= "<option ".((($continent.'/'.$city)==$selectedzone)?'selected="selected "':'')." value=\"".($continent.'/'.$city)."\">";
				$structure .= (!empty($abbr)) ? $abbr : '';
				$structure .= (!empty($gmt)) ? " ({$gmt})" : '';
				$structure .= (!empty($abbr)) ? ' - ' : '';
				$structure .= str_replace('_',' ',$city)."</option>"; //Timezone
			} else {
				if (!empty($subcity) != ''){
					$city = $city . '/'. $subcity;
				}
				$structure .= "<option ".(($continent==$selectedzone)?'selected="selected "':'')." value=\"".$continent."\">".$continent."</option>"; //Timezone
			}
			
			$selectcontinent = $continent;
		}
	}
	$structure .= '</optgroup>';
	return $structure;
}

function offset2num($offset){
	$n = (int)$offset;
	$m = $n%100;
	$h = ($n-$m)/100;
	return $h*3600+$m*60;
}

function an( $word ){
	$vowels = array('a', 'i', 'e', 'o', 'u', 'A', 'I', 'E', 'O', 'U');
	return (in_array(substr(trim($word), 0, 1), $vowels)) ? 'an' : 'a';
}

function stripslashes_dq( $string ){
	return str_replace('"', '&quot;', stripslashes($string));
}

function stripslashes_apos( $string ){
	return str_replace('\'', '&apos;', stripslashes($string));
}

function xml_scrub( $string ){
	return stripslashes(strip_tags(urldecode($string)));
}

function var_scrub( $string ){
	return addslashes(stripslashes(strip_tags(urldecode($string))));
}

function my_mysql_error( $sql=false ){
	global $PUBLIC_PATH, $ADMIN_PATH;
	if(!$sql) global $sql;
	$bug = debug_backtrace();
	
	function file_line( $file ){
		global $PUBLIC_PATH, $ADMIN_PATH;
		if( strpos($file, 'class.')!==false ){
			## Protection to hide the location of critical classes
			echo '<strong>Erroring File:</strong>&nbsp;'.ucwords(str_replace(array('.php','_'), array('',' '), substr($file, strpos($file,'class.')+6)))." Class<br />\n";
		} else if( isset($ADMIN_PATH) && strpos($file,$ADMIN_PATH)!==false ){
			echo '<strong>Erroring File:</strong>&nbsp;'.str_replace($ADMIN_PATH, '', $file)."<br />\n";
		} else if( isset($PUBLIC_PATH) && strpos($file,$PUBLIC_PATH)!==false ){
			echo '<strong>Erroring File:</strong>&nbsp;'.str_replace($PUBLIC_PATH, '', $file)."<br />\n";
		} else {
			echo '<strong>Erroring File:</strong>&nbsp;'.$file."<br />\n";
		}
	}
	
	echo '<strong>MySQL Error #'.mysql_errno().':</strong>&nbsp;<font color="#aa0000;">'.mysql_error()."</font><br />\n";
	echo '<strong>Erroring Line:</strong>&nbsp;'.$bug[0]['line']."<br />\n";
	file_line( $bug[0]['file'] );
	if($sql) echo '<strong>MySQL Query:</strong>&nbsp;<pre>'.$sql."</pre>\n";
	
	if( count($bug)>1 ){
		echo "<strong>Error Trace Back:</strong><br />\n";
		for( $x=1; $x<count($bug); $x++ ){
			if($x>1) echo '<br /><br />';
			echo '<div style="padding-left: 15px;">';
			echo '<strong>Line:</strong>&nbsp;'.$bug[$x]['line']."<br />\n";
			file_line( $bug[$x]['file'] );
			if(!empty($bug[$x]['object'])) echo '<strong>Object:</strong>&nbsp;<span style="font-family: \'Courier New\';">'.$bug[$x]['object']."</span><br />\n";
			if(!empty($bug[$x]['function'])) echo '<strong>Function:</strong>&nbsp;<span style="font-family: \'Courier New\';">'.((!empty($bug[$x]['class']) && !empty($bug[$x]['type']))?($bug[$x]['class'].$bug[$x]['type']):'').$bug[$x]['function']."</span><br />\n";
			if(!empty($bug[$x]['args'])){
				echo '<strong>Arguments:</strong><br />'."\n";
				for( $y=0; $y<count($bug[$x]['args']); $y++ ){
					echo "[{$y}]: {$bug[$x]['args'][$y]}<br />\n";
				}
			}
			echo '</div>';
		}
	}
	exit;
}

function image_resize( $original, $save, $to_width=9999, $to_height=9999, $constrain=true, $stretch=false, $quality=100 ){
	list( $from_width, $from_height, $type ) = getimagesize( $original );
	
	switch($type){
        case "1": $from_image = imagecreatefromgif($original); break;
        case "2": $from_image = imagecreatefromjpeg($original); break;
        case "3": $from_image = imagecreatefrompng($original); break;
        default:  $from_image = imagecreatefromjpeg($original);
	} 
	
	if( $constrain ){
		$w_ratio = $from_width / $to_width;
		$h_ratio = $from_height / $to_height;
		$ratio = ($w_ratio>$h_ratio) ? $w_ratio : $h_ratio;
		$width = ($from_width>=$to_width) ? floor($from_width / $ratio) : $from_width;
		$height = ($from_height>=$to_height) ? floor($from_height / $ratio) : $from_height;
	} else if( !$stretch ){
		$width = ($from_width>=$to_width) ? floor($to_width) : $from_width;
		$height = ($from_height>=$to_height) ? floor($to_height) : $from_height;
	} else {
		$width = floor($to_width);
		$height = floor($to_height);
	}
	
	$output = imagecreate($width, $height);
	$output = imagecreatetruecolor($width,$height);
	
	imagecopyresampled($output,$from_image,0,0,0,0,$width,$height,$from_width,$from_height);
	
	if( imagejpeg($output, $save, $quality) ){
		return true;
	} else {
		return false;
	}
}


/*
function img_resize( $tmpname, $size, $save_dir, $save_name ){
    $save_dir .= ( substr($save_dir,-1) != "/") ? "/" : "";
    $gis = GetImageSize($tmpname);
    $type = $gis[2];
	
    switch($type){
        case "1": $imorig = imagecreatefromgif($tmpname); break;
        case "2": $imorig = imagecreatefromjpeg($tmpname);break;
        case "3": $imorig = imagecreatefrompng($tmpname); break;
        default:  $imorig = imagecreatefromjpeg($tmpname);
	} 

	$x = imageSX($imorig);
	$y = imageSY($imorig);
	
	if($gis[0] <= $size){
		$av = $x;
		$ah = $y;
	} else {
		$yc = $y*1.3333333;
		$d = $x>$yc?$x:$yc;
		$c = $d>$size ? $size/$d : $size;
		$av = $x*$c;
		$ah = $y*$c;
	} 
	   
	$im = imagecreate($av, $ah);
	$im = imagecreatetruecolor($av,$ah);
	
	if(imagecopyresampled($im,$imorig,0,0,0,0,$av,$ah,$x,$y))
	
	if(imagejpeg($im, $save_dir.$save_name))
		return true;
	} else {
		return false;
	}
}
*/


/**
 * debug prints a nice html string of debug info for a variable (similar to print_r)
 */
function debug ( $var )
{
	return '<pre>' . nl2br(print_r($var, 1)) . '</pre>';
}


/**
 * check for a dealer's custom logo, returning scr string if need be
 *
 * This function checks for the existence of a form of form:
 * public_html/admin/_user_images/000[user_id]_logo_original.jpg
 *
 * @param integer $dealerId is the user_id of the dealer to fetch the image for
 * @param string optional $which controls which version of image: "original", "thumb", "resized", or "default". Without the $which, get_dealer_logo() returns boolean.
 */
function get_dealer_logo( $dealer_id, $which = false )
{
	$logo_name = '_user_images/'.str_pad($dealer_id, 5, '0', STR_PAD_LEFT).'_logo_original.jpg';
	
	if( file_exists( ADMIN_PATH . $logo_name ) ){
		if( !$which ){
			return true;
		} else {
			switch( $which ){
				case 'original':
					return $logo_name; break;
				case 'thumb':
					return str_replace('_original', '_thumb', $logo_name); break;
				case 'resized':
					return str_replace('_original', '_resized', $logo_name); break;
				case 'default':
					return '_user_images/default_logo_resized.jpg'; break;
			}
		}
	} elseif( $which == 'default' ){
		return '_user_images/default_logo_resized.jpg';
	} else {
		return false;
	}

} // end: get_dealer_logo;


/**
 * send an admin alert email
 */
function send_email_alert ( $msg, $subject = '', $priority = false )
{
	// add an extra star f/ priority msgs...
	if ($priority)
	{
		$subjectPrefix = '*';
	}
	
	$subjectPrefix .= '* IQ Alert: ';
	$subject = $subjectPrefix . (($subject != '') ? $subject : '');
	
	$msg = '~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
 Ink Quote Administrative Alert:
 ' . date('m/d/Y  H:i:s') . '
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

' . $msg . '

~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
Sent from:  ' . $_SERVER['SERVER_NAME'] . ' @ ' . $_SERVER['SERVER_ADDR'];
	
	$headers = '';
	if ($priority)
	{
		$headers .= "X-Priority: 1\r\n";
	}
	
	return @mail(ALERT_EMAIL, $subject, $msg, $headers);
	
} // end: send_email_alert();
