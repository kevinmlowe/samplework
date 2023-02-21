<?

/******************************************************************************************/
//	AJAX_PROCESS_JOIN.PHP INPUTS - POST data from "member_signin.php" form via jQuery/ajax.
//	This script validates the user membership data. If OK enter to d/b
/******************************************************************************************/
require('../includes/global_config.php');
require('../includes/mysqli_lib.php');
disable_cache();


$dbh = db_connect();



$error_count=0;
$error_text_array = array();
$badfields	= array();


if (isset($_POST["forename"])) $forename = $_POST["forename"]; else $forename ="";
if (isset($_POST["surname"])) $surname = $_POST["surname"]; else $surname ="";
if (isset($_POST["user_email"])) $user_email = $_POST["user_email"]; else $user_email ="";
if (isset($_POST["password_1"])) $password_1 = $_POST["password_1"]; else $password_1 ="";
if (isset($_POST["password_2"])) $password_2 = $_POST["password_2"]; else $password_2 ="";

if (isset($_POST["member_join_password_strength_score"])) $member_join_password_strength_score = $_POST["member_join_password_strength_score"]; else $member_join_password_strength_score =0;


/*********** first name - 20 char string ***************************/

$pre_this_item_error_count = $error_count;
$forename = check_text($forename, "First name", 2, 20, 'STANDARD');

if ($pre_this_item_error_count != $error_count) //  errors
	$badfields[] =  "#member_join_form_forename";




/*********** surname - 20 char string ***************************/
$pre_this_item_error_count = $error_count;
$surname = check_text($surname, "Surname", 2, 20, 'STANDARD');

if ($pre_this_item_error_count != $error_count) //  errors
	$badfields[] =  "#member_join_form_surname";




/*********** User Email - upto 60 char string ***************************/
$pre_this_item_error_count = $error_count;
$user_email = check_text($user_email, "E-mail Address", 6, 60, 'EMAIL');

//see if email is already in d/b
if ($pre_this_item_error_count == $error_count)
	{
	$find_email_query = "select * from $member_table where email=:user_email";
	$find_email_parameters = array( array('P' => ':user_email', 'V'=> $user_email, 'T' => 'S') );
	$find_email_result = db_single_prepared_query($find_email_query, $find_email_parameters);

	$mbr_type = 'NEW';

	// allow for dup emails if the account type is GUEST_ALERT or UNVERIFIED_GST
	if ($find_email_result['ROWCOUNT'] > 0 )
		{
		$mbr_type = $find_email_result['ROWS'][0]['mbr_type'];


		if (preg_match("/^(MEMBER|UNVERIFIED_MBR)$/", $mbr_type)) // TOK
			{
			$error_count++;
			$error_text_array[] = "The <b>E-mail Address</b> is already present in the membership list.\n";
			}
		}
	}


if ($pre_this_item_error_count != $error_count) //  errors
	$badfields[] =  "#member_join_form_email";






/******** password **********/
$pre_this_item_error_count = $error_count;


if (strlen($password_1) < 8)
	{
	$error_count++;
	$error_text_array[] = "The <b>password</b> must be at least 8 characters.";
	$badfields[] = "#member_join_form_password_1";
	$badfields[] = "#member_join_form_password_2";
	}
else
	{
	$pass_regexp = "\x{20}-\x{7e}"; // basic ascii chars
	$pass_regexp .= "\x{a1}-\x{ff}"; // extended latin 1 chars excluding control chars
	$pass_regexp .= "\x{20ac}\x{201c}\x{201d}"; // euro symbol & left/right double quotation mark (from Word)
	$pass_regexp .= "\x{2018}\x{2019}"; // left/right single quotation mark (from word)

	if (preg_match("/[^$pass_regexp]/u", $password_1))
		{
		$error_count++;
		$error_text_array[] = "The <b>password</b> contains invalid characters, just use what you can see on your Irish/UK/US keyboard, no ALT sequences or pasted text.";
		$badfields[] = "#member_join_form_password_1";
		$badfields[] = "#member_join_form_password_2";
		}
	}




if ($password_1 != $password_2)
	{
	$error_count++;
	$error_text_array[] = "The <b>passwords</b> do not match.";
	$badfields[] = "#member_join_form_password_1";
	$badfields[] = "#member_join_form_password_2";
	}









/************************************************************************/
// check error count and exit if there are any
/************************************************************************/



if ($error_count > 0)
	{
	$extra_fields = array('BADFIELDS'=>$badfields);

	$err_text = "";


	for ($i=0; $i < sizeof($error_text_array); $i++)
		$err_text .= "<img src=$pics/x.gif> $error_text_array[$i]<br>";

	ajax_response_nofile_upload("ERROR", $err_text, $extra_fields);
	exit();
	}







/************************************************************************/
// All inputs OK, create new UNVERIFIED_MBR account if email is not already there
/************************************************************************/

$primary_site = THIS_SITE;







// this is actually in the lib, but just shown here for completness
function hash_password($password)
	{
	$return_array=array();

	$salt = random_bytes(64);
	$hexsalt = bin2hex($salt);

	$pass_hash = hash('sha512', $password . $hexsalt);

	$return_array['SALT'] = $hexsalt;
	$return_array['HASH'] = $pass_hash;
	return $return_array;
	}





$pass_info = hash_password($password_1);
$pass_salt = $pass_info['SALT'];
$pass_hash = $pass_info['HASH'];

if ($mbr_type == 'NEW')
	{


	// CREATE ACCOUNT AS AN UNVERIFIED_MBR
	$insert_query = "insert into $member_table
		(user_id, email, password, pass_hash, pass_salt, loggedin_token, loggedin_token_exp, last_logon, login_fails, firstname, surname, primary_site, fp_token_hash, fp_token_salt, fp_token_expiry, fp_token_usage, last_pass_change, joined, mbr_type )
		values
		(0, :user_email, NULL, :pass_hash, :pass_salt, NULL,  NOW(), NOW(), 0, :forename, :surname, :primary_site, NULL, NULL, NULL, 0,  NOW(), NOW(), 'UNVERIFIED_MBR')";

	$insert_parameters = array(
		array('P' => ':user_email', 'V'=> $user_email, 'T' => 'S'),
		array('P' => ':pass_hash', 'V'=> $pass_hash, 'T' => 'S'),
		array('P' => ':pass_salt', 'V'=> $pass_salt, 'T' => 'S'),
		array('P' => ':forename', 'V'=> $forename, 'T' => 'S'),
		array('P' => ':surname', 'V'=> $surname, 'T' => 'S'),
		array('P' => ':primary_site', 'V'=> $primary_site, 'T' => 'S')
		);


	$insert_result = db_single_prepared_query($insert_query, $insert_parameters);


	$user_id = $insert_result['LASTID'];


	// send email to confirm account (can't login till it's confirmed)
	$token = md5(HASH_SECRET . ":" . $user_id . ":"  . $user_email); // MD5 OK for quick and dirty short link
	$link = $root_url . "/CM/" .  $token . "/" . base_convert ($user_id , 10 , 36) ; // obfuscate user_id



	$message = "We are writing because you joined our site.\n\n";
	$message .= "To activate your account please use the link below.\n\n";
	$message .= $link  .  "\n\n";
	$message .= "Best regards,\n\n";
	$message .= "TheSite.ie ";

	mail($user_email, "TheSite.ie account confirmation", $message, "From: " . $site_email_address . "\nReply-To: " . $site_email_address);


	$success_msg = "Lorem ipsum dolor sit amet, consectetur adipiscing elit...";

	ajax_response_nofile_upload("SUCCESS", $success_msg);
	exit();
	}
























/************************************************************************/
// All inputs OK, promote guest alert users to UNVERIFIED_MBR or MEMBER
/************************************************************************/
if (preg_match("/^(GUEST_ALERT|UNVERIFIED_GST)$/", $mbr_type))
	{
	$user_id = $find_email_result['ROWS'][0]['user_id'];


	if ($mbr_type == 'UNVERIFIED_GST')
		$new_mbr_type = 'UNVERIFIED_MBR';
	else
		$new_mbr_type = 'MEMBER';


	$update_query = "update $member_table set
		pass_hash = :pass_hash,
		pass_salt = :pass_salt,
		firstname = :forename,
		surname = :surname,
		mbr_type = :new_mbr_type
		where user_id=:user_id";

	$update_parameters = array(
		array('P' => ':pass_hash', 'V'=> $pass_hash, 'T' => 'S'),
		array('P' => ':pass_salt', 'V'=> $pass_salt, 'T' => 'S'),
		array('P' => ':forename', 'V'=> $forename, 'T' => 'S'),
		array('P' => ':surname', 'V'=> $surname, 'T' => 'S'),
		array('P' => ':new_mbr_type', 'V'=> $new_mbr_type, 'T' => 'S'),
		array('P' => ':user_id', 'V'=> $user_id, 'T' => 'I')
		);


	db_single_prepared_query($update_query, $update_parameters);



	if ($mbr_type == 'UNVERIFIED_GST')
		{

		// send email to confirm account (can't login till it's confirmed)
		$token = md5(HASH_SECRET . ":" . $user_id . ":"  . $user_email); // MD5 OK for quick and dirty short link
		$link = $root_url . "/CM/" .  $token . "/" . base_convert ($user_id , 10 , 36) ; // obfuscate user_id

		$message = "We are writing because you joined our site.\n\n";
		$message .= "To activate your account please use the link below.\n\n";
		$message .= $link  .  "\n\n";
		$message .= "Best regards,\n\n";
		$message .= "TheSite.ie ";

		mail($user_email, "TheSite.ie account confirmation", $message, "From: " . $site_email_address . "\nReply-To: " . $site_email_address);
		$success_msg = "Lorem ipsum dolor sit amet, consectetur adipiscing elit...";

		}
	else
		{
		$success_msg = "Lorem ipsum dolor sit amet, consectetur adipiscing elit...";
		}

	ajax_response_nofile_upload("SUCCESS", $success_msg);

	exit();
	}







?>