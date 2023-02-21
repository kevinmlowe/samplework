<?




/*******************************************************************************************/
function validate_card_number($num, $name) 
//
// This function accepts a credit card number and a code for a credit card name. 
// First, the card type is checked against the array $valid_card_types, defined in the global 
// configuration file which contains the card types that this merchamt accepts. Then the number 
// is checked against card-specific criteria, then validated with the Luhn Mod 10 
// formula. Otherwise it is only checked against the formula. Valid name codes are:
//
//    MC - Master Card
//    VISA - Visa
//    AMEX - American Express
//    DSC - Discover
//    DNC - Diners Club
//    JCB - JCB
//
// A description of the criteria used in this function can be found at
// http://www.beachnet.com/~hstiles/cardtype.html. 
/*******************************************************************************************/
	{
	global $valid_card_types;
	//  Innocent until proven guilty
	$GoodCard = true;

	// is this card type accepted by this merchant
	$card_accepted =0;
	reset ($valid_card_types);
	foreach ($valid_card_types as $card_type => $value) 
		{
		if ($card_type == $name)
			$card_accepted =1;
		}
	if (!$card_accepted) // will ensure card fails
		$name = "Not Accepted";

	//  Get rid of any non-digits
	$num = preg_replace("/[^[:digit:]]/", "", $num);

	//  Perform card-specific checks
	switch ($name) 
		{
		case "MC" :
		  $GoodCard = preg_match("/^5[1-5].{14}$/", $num);
		  break;

		case "VISA" :
  		  $GoodCard = preg_match("/^4.{15,18}$|^4.{12}$/", $num);
		  break;

		case "AMEX" :
		  $GoodCard = preg_match("/^3[47].{13}$/", $num);
		  break;

		case "DSC" :
		  $GoodCard = preg_match("/^6011.{12}$/", $num);
		  break;

		case "DNC" :
		  $GoodCard = preg_match("/^30[0-5].{11}$|^3[68].{12}$/", $num);
		  break;

		case "JCB" :
		  $GoodCard = preg_match("/^3.{15}$|^2131|1800.{11}$/", $num);
		  break;
		default: // will capture an invalid card type, as card name will be "Not Accepted"
			$GoodCard =0;
		}

	//  The Luhn formula works right to left, so reverse the number.
	$num = strrev($num);
	$Total = 0;

	for ($x=0; $x<strlen($num); $x++) 
		{
		$digit = substr($num,$x,1);
		// If it's an odd digit, double it
		if ($x/2 != floor($x/2)) 
			{
			$digit *= 2;
			// If the result is two digits, add them
			if (strlen($digit) == 2) 
				$digit = substr($digit,0,1) + substr($digit,1,1);
			}

		// Add the current digit, doubled and added if applicable, to the Total
		$Total += $digit;
		} // END for ($x=0; $x<strlen($num); $x++) 


	//  If it passed the card-specific check and the Total is evenly divisible by 10, it's cool!
	if ($GoodCard && $Total % 10 == 0) 
		return true; 
	else 
		return false;
	}

/*******************************************************************************************/
// END function validate_card($num, $name) 
/*******************************************************************************************/

/*******************************************************************************************/
function compare_dates($left_date, $right_date)
// compares two dates in YYYY-MM-DD format and returns which is later, LEFT, RIGHT or EQUAL
/*******************************************************************************************/
	{
	preg_match("/([0-9]{4})-([0-9]{1,2})-([0-9]{1,2})/", $left_date, $left_date_array);
	$l_day = $left_date_array[3];
	$l_month = $left_date_array[2];
	$l_year = $left_date_array[1];

	// use MKTIME to create a timestamp for the dates, and use DATE to get numeric day  
	// of year, ie days since Jan 1st (the "z" format).
	$l_day_number = date("z", mktime(0,0,0, $l_month, $l_day, $l_year) );


	preg_match("/([0-9]{4})-([0-9]{1,2})-([0-9]{1,2})/", $right_date, $right_date_array);
	$r_day = $right_date_array[3];
	$r_month = $right_date_array[2];
	$r_year = $right_date_array[1];

	$r_day_number = date("z", mktime(0,0,0, $r_month, $r_day, $r_year) );


	if ($l_year == $r_year)
		{
		if ($l_day_number == $r_day_number)
			return "equal";
		elseif ($l_day_number > $r_day_number)
			return "left";
		elseif ($l_day_number < $r_day_number)
			return "right";
		}

	elseif ($l_year > $r_year)
		return "left";

	elseif ($l_year < $r_year)
		return "right";
	}
/*******************************************************************************************/
/* END	compare_dates($left_date, $right_date)				   		*/
/*******************************************************************************************/


/*************************************************************************************/
// creates an XML request for authoraisation. Note, amount sent to pay and shop is 
// in units of the smallest fraction of a currence, ie £12.34 would be entered as 1234 pence.
// See PayAndShop e-page remote intergration guide.  
/*************************************************************************************/
function create_authorisation_xml_request($merchant_id, $order_id, $amount, $currency, $card_number, $expiry_date, $card_holder, $card_type, $secret, $autosettle, $realex_test_mode)
	{
	global $log_files;

	$timestamp = date("YmdHis");
	$amount = $amount *100;
	$this_transaction_hash = make_transaction_hash_md5 ($timestamp, $merchant_id, $order_id, $amount, $currency, $card_number, $secret, $autosettle, $realex_test_mode);

	// this overcomes a editplus bug where syntax hightlighting fails after encountering a ? > even within quotes
	$question_mark = "?"; 
	$request = "<?xml version=\"1.0\" encoding=\"UTF-8\" " . $question_mark . ">\n";
	$request .= "<request timestamp=\"$timestamp\" type=\"auth\">\n";
	$request .= "<merchantid>$merchant_id</merchantid>\n";
	if ($realex_test_mode)
		$request .= "<account>internettest</account>\n";
	else
		$request .= "<account>internet</account>\n";

	$request .= "<orderid>$order_id</orderid>\n";
	$request .= "<amount currency=\"$currency\">$amount</amount>\n";
	$request .= "<card>\n";
	$request .= "<number>$card_number</number>\n";
	$request .= "<expdate>$expiry_date</expdate>\n";
	$request .= "<chname>$card_holder</chname>\n";
	$request .= "<type>$card_type</type>\n";
	$request .= "</card>\n";

	$request .= "<autosettle flag=\"$autosettle\" />\n";
	$request .= "<comments>\n";
	$request .= "<comment id=\"1\" />\n";
	$request .= "<comment id=\"2\" />\n";
	$request .= "</comments>\n";
	$request .= "<md5hash>$this_transaction_hash</md5hash>\n";
	$request .= "</request>\n";  

	//error_log("\n" . date("H:i d/m/y") . "\n $request\n\n\n\n\n\n\n\n", 3, "$log_files/auth.log");

	return $request;
	}


/*************************************************************************************/
// creates an MD5 hash of several fields of the XML request
/*************************************************************************************/
function make_transaction_hash_md5 ($timestamp, $merchant_id, $order_id, $amount, $currency, $card_number, $secret)
	{
	$hash_data =  $timestamp . "."  . $merchant_id . "."  . $order_id . "."  . $amount . "."  . $currency . "."  . $card_number;
	$interm_hash = md5($hash_data);
	$final_hash = md5($interm_hash . "."  . $secret);
	return $final_hash;
	}



/*************************************************************************************/
// creates an MD5 hash of several fields of the XML response
/*************************************************************************************/
function verify_response_hash_md5 ($timestamp, $merchant_id, $order_id, $result, $message, $pasref, $authcode, $secret)
	{
	$hash_data =  $timestamp . "."  . $merchant_id . "." . $order_id . "." . $result  . "." . $message  . "." . $pasref . "." . $authcode;
	$interm_hash = md5($hash_data);
	$final_hash = md5($interm_hash . "."  . $secret);
	return $final_hash;
	}

/*************************************************************************************/
// cURL request
/*************************************************************************************/
function do_curl($url, $data)
	{
	$ch = curl_init();    
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_POST, 1); 
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
	curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
	$result = curl_exec ($ch);

	$curl_return=array();

	if (!is_string($result))
		{
		$curl_return['STATUS'] = FALSE;
		$curl_return['ERRMSG'] = curl_error($ch);
		}
	else
		{
		$curl_return['STATUS'] = TRUE;
		$curl_return['RESPONSE'] = $result;
		}
	curl_close($ch); 
	return $curl_return;
	}





/*************************************************************************************/
// XML parser functions
/*************************************************************************************/

/* parse_xml is the main function called by the calling script - this script must init these vars before calling:
		$xml_parser_return_array =array();
		$xml_parser_element_value_index =array();
		$xml_parser_is_good = TRUE;

populates $xml_parser_return_array with the data contained in the XML, eg:

Array
(
    [RESPONSE] => Array
        (
            [DATA] => 
            [ATTRIBS] => Array
                (
                    [TIMESTAMP] => 20070518165944
                )

        )

    [RESPONSE>MERCHANTID] => Array
        (
            [DATA] => fallers
        )

    [RESPONSE>ACCOUNT] => Array
        (
            [DATA] => internet
        )


ETC...
)
*/

function parse_xml($xml)
	{
	global $xml_parser_return_array;
	global $xml_parser_element_value_index;
	global $xml_parser_is_good;


	$xml_parser = xml_parser_create(); 
	xml_parser_set_option($xml_parser, XML_OPTION_CASE_FOLDING, 1); 
	xml_set_element_handler($xml_parser, "startElement", "endElement"); 
	xml_set_character_data_handler($xml_parser, "characterData"); 

	// exit now if any XML document errors
	if (!xml_parse($xml_parser, $xml, TRUE)) 
		$xml_parser_is_good = FALSE;

	xml_parser_free($xml_parser); 

	}



function startElement($parser, $name, $attribs) 
	{ // extract the element names and use them as the key to the array
	global $xml_parser_return_array,$xml_parser_element_value_index;

	array_push($xml_parser_element_value_index, $name);

	$data_array_index= implode ('>', $xml_parser_element_value_index); 

	$xml_parser_return_array[$data_array_index]['DATA'] =NULL;

	if (sizeof($attribs)>0)
		$xml_parser_return_array[$data_array_index]['ATTRIBS'] =$attribs;
	} 

function endElement($parser, $name) 
	{ 
	global $xml_parser_element_value_index;
	array_pop($xml_parser_element_value_index);
	} 

function characterData($parser, $data) 
	{ 
	// assign the data to the array using the keys found by the startElement function
	global $xml_parser_return_array,$xml_parser_element_value_index;
		
	$data_array_index= implode ('>', $xml_parser_element_value_index); 

	// characterData is called by xml_parse() for white spaces in the XML so ensure the 
	// array is only populated with data between <element> and </element> tags
	$trimed_data =trim($data);
	if (strlen($trimed_data)>0)
		$xml_parser_return_array[$data_array_index]['DATA'] .=$data;
	} 

/*************************************************************************************/
// END - XML parser functions
/*************************************************************************************/


/*************************************************************************************/
// parse the XML response from pay&shop and return an associative array with result
// codes and, if the request was successful, authoraisation code and pay&shop ref. 
// These may be used to void or refund this transaction. 
// Inputs - the XML response array returned by the CURL request; the shared secret    
/*************************************************************************************/
function verify_authorisation_response($xml_response_array, $secret)
	{
	// get the info between <result> and </result>
	$response_code = $xml_response_array['RESPONSE>RESULT']['DATA'];


	// response codes 00, 1xx and 2xx include a hash, the rest dont
	if ($response_code == "00" || (preg_match("/^[12]/", $response_code) && strlen($response_code) == 3) ) 
		{ // get all the elements for the hash and to return, 
		$message = $xml_response_array['RESPONSE>MESSAGE']['DATA'];
		$authcode = $xml_response_array['RESPONSE>AUTHCODE']['DATA'];
		$pasref = $xml_response_array['RESPONSE>PASREF']['DATA'];
		$response_hash = $xml_response_array['RESPONSE>MD5HASH']['DATA'];
		$merchant_id = $xml_response_array['RESPONSE>MERCHANTID']['DATA'];
		$order_id = $xml_response_array['RESPONSE>ORDERID']['DATA'];
		$timestamp = $xml_response_array['RESPONSE']['ATTRIBS']['TIMESTAMP'];
		//print "<h3>Time: $timestamp<br>Merchant ID: $merchant_id<br>Order ID: $order_id<br>Response Code: $response_code<br>Message: $message<br>PAS ref: $pasref<br>Authcode: $authcode</h3>\n";


		// create a hash from the above and compare it to the hash sent by pay&shop
		if ($response_hash == verify_response_hash_md5 ($timestamp, $merchant_id, $order_id, $response_code, $message, $pasref, $authcode, $secret))
			{ // Hash and result code OK - return all the relevent details
			$response_array =array();
			$response_array['RESPONSE_CODE'] = $response_code;
			$response_array['MESSAGE'] = $message;
			$response_array['PASREF'] = $pasref;

			if ($response_code == "00")
				{
				$response_array['RESPONSE_CODE'] = $response_code;
				$response_array['AUTHCODE'] = $authcode;
				$response_array['LOGFILE_MESSAGE'] = date("H:i d/m/y") . " - Successful authorisation: Order ID: $order_id - $message - AuthCode: $authcode PASref: $pasref";
				}
			elseif (preg_match("/^1/", $response_code)) // card declined etc
				{
				$response_array['RESPONSE_CODE'] = $response_code;
				$response_array['CARD_ERROR_MSG'] = "Your card has been declined by your bank. <br>Check your expiry date or contact your bank if you believe this is an error.";
				$response_array['LOGFILE_MESSAGE'] = date("H:i d/m/y") . " - DECLINED - Order ID: $order_id -Code: $response_code - Message: $message - PASref: $pasref";
				}
			else // bank error
				{
				$response_array['RESPONSE_CODE'] = $response_code;
				$response_array['CARD_ERROR_MSG'] = "There was a problem connecting to your bank and we cannot authorise your card.";
				$response_array['LOGFILE_MESSAGE'] = date("H:i d/m/y") . " - OTHER ERROR -  Order ID: $order_id - Code: $response_code - Message: $message - PASref: $pasref";
				}

			} // END [TRUE] if ($response_hash == verify_response_hash_md5 ($timestamp, $merchant_id, $order_id, $response_code, $message, $pasref, $authcode, $secret))
		else
			{ // bad hash from pay&shop
			$response_array =array();
			$response_array['RESPONSE_CODE'] = "999"; // NOT a Realex code!!!!!!!
			$response_array['PASREF'] = $pasref;
			$response_array['CARD_ERROR_MSG'] = "There was a problem connecting to your bank and we cannot authorise your card.";
			$response_array['LOGFILE_MESSAGE'] = date("H:i d/m/y") . " - HASH ERROR -  Code: $response_code - Message: $message - PASref: $pasref";
			} // EMD [FALSE] if ($response_hash == verify_response_hash_md5 ($timestamp, $merchant_id, $order_id, $response_code, $message, $pasref, $authcode, $secret))

		return $response_array;
		} // END [TRUE] if ($response_code == "00" || (preg_match("/^[12]/", $response_code) && strlen($response_code) == 3) ) 
	else // Response other than 00, 1xx or 2xx - no hash
		{ 
		$message = $xml_response_array['RESPONSE>MESSAGE']['DATA'];

		$response_array =array();
		$response_array['PASREF'] = "N/A";

		$response_array['RESPONSE_CODE'] = $response_code;
		$response_array['CARD_ERROR_MSG'] = "There was a problem connecting to your bank and we cannot authorise your card.";
		$response_array['LOGFILE_MESSAGE'] = date("H:i d/m/y") . " - OTHER ERROR-  Code: $response_code - Message: $message - PASref: $pasref";

		return $response_array;
		} // END [FALSE] if ($response_code == "00" || (preg_match("/^[12]/", $response_code) && strlen($response_code) == 3) ) 
	}



?>