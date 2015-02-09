<?php
/**
 * functions.common.php contains common things
 */
 
/**
 * debug prints a nice html string of debug info for a variable (similar to print_r)
 */
function debug ( $var )
{
	return '<pre>' . nl2br(print_r($var, 1)) . '</pre>';
}


/**
 * is_even() determines if a given scalar is even (returns true) or odd (returns false)
 */
function is_even ( $number )
{
	if ((int)$number % 2)
	{
		return false;
	}
	else
	{
		return true;
	}
}


##################################################################################################################################################################
function array_value(&$array, $value)
# Use:  to get an element's value from an array
# In:  $array is the array to get from, $value is the element number to get (0-based)
#	*Note: if value is -1, then returns the last element...
# Out: nice array element value...
{
	if ($value<0)
	{ //start from ending index...
		if (isset($array[count($array)+$value])) return $array[count($array)+$value]; //*Note: value is negative, so this is a plus...
		else  { echo error('array_value: negative index ['.$value.'] does not exist.<br />'.print_r($array,true));  return false; }
	} //end if: start from ending index;
	elseif (isset($array[$value])) return $array[$value];
	else  { echo error('array_value: index ['.$value.'] does not exist.<br />'.print_r($array,true));  return false; }

} //end function: array_value();


##################################################################################################################################################################
function no_cache()
# Use:  [from tracker()] send noCache http headers {heisted from PHP manual}...
# In:  nothing   **Note: [2005.09.04] find & replace "no_cache();" && "no_cache('ignoreMainPower');" --> "no_cache($serverVars);" && "no_cache($serverVars,$option='');"...
# Out:  no cache headers  **Note: this is the ONLY script that you call from ALL pages [unless you call the tracker from that page, which in turn calls no_cache()...]...
{	
	header("Expires: Mon, 26 Jul 1997 05:00:00 GMT"); //date in the past...
	header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT"); //always modified...
	header("Cache-Control: no-store, no-cache, must-revalidate"); //HTTP/1.1...
	header("Cache-Control: post-check=0, pre-check=0", false);
	header("Pragma: no-cache"); //HTTP/1.0...

	return;
} //end function: no_cache();


	/**
	 * image_size_html()  Returns the html snippet for the height and width of an image.
	 * @param string $image is the location of the image (relative or full path)
	 */
	function image_size_html ( $image )
	{
		return array_value(getimagesize($image), 3);
	}
	
	
	/**
	 * check_email_addr()  Checks to make sure a string looks like a valid email address.
	 *
	 * @param string $addr is the string to check
	 * @returns boolean true if string looks like an email, false otherwise
	 */
	function check_email_addr ( $addr )
	{
		// ok, so lets make sure the email two non-empty parts w.r.t. '@'
		// and at least two non-empty parts w.r.t. '.' for the latter part of above
		
		$addr = explode('@', $addr);
		if (count($addr)!=2 || $addr[0]=='' || $addr[1]=='')
		{
			return false;
		}
		
		// now test domain part of email for goodness...
		$addr = explode('.', $addr[1]);
		if (count($addr)<2 || $addr[0]=='' || $addr[1]=='')
		{
			return false;
		}
		
		return true;
	}
	
	
	/**
	 * this_page()  returns the current url page, without any get params
	 */
	function this_page ()
	{
		// first, strip GETS...
		$page = explode('?', $_SERVER['REQUEST_URI']);
		$addr = array_value($page, 0);
		
		// get the last thing after the last "/"...
		$page = explode('/', $addr);
		$addr = array_value($page, -1);
		
		return $addr;
	}
