<?
/*******************************************************************************************/
// set of wrapper functions for the PHP MySQL Improved Extension https://www.php.net/manual/en/book.mysqli.php
/*******************************************************************************************/



function db_connect()
	{
	// Define connection as a static variable, to avoid connecting more than once
	static $dbh;

	// Try and connect to the database, if a connection has not been established yet
	if(!isset($dbh))
		{
		try
			{
			// http://stackoverflow.com/questions/4361459/php-pdo-charset-set-names
			//$dsn = 'mysql:dbname=' . MYSQL_DBNAME . ';host=' . MYSQL_HOSTNAME . ';charset=utf8';
			$dsn = 'mysql:dbname=' . MYSQL_DBNAME . ';host=' . MYSQL_HOSTNAME ;
			$dbh = new PDO($dsn, MYSQL_USERNAME, MYSQL_PASSWORD);
			$dbh->exec("set names utf8");
			$dbh->exec("SET sql_mode ='STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION'");


			$dbh->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);//which tells PDO to disable emulated prepared statements and use real prepared statements. This makes sure the statement and the values aren't parsed by PHP before sending it to the MySQL server (giving a possible attacker no chance to inject malicious SQL).
			$dbh->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);



			}
		catch(PDOException $e)
			{
			print "Sorry, there is temporary technical problem loading this page. We have been informed of this issue and will correct it ASAP. (DBC)";
			log_this("db_connect - " . $e->getMessage(), "error.log");
			exit();

			}
		}

	return $dbh;
	}



function db_single_prepared_query($query, $parameters=array())
	{
	// see what type of query this is, use this later to customise return values. Allow space and ( [for UNION] before SQL operation
	if (preg_match('/^ *\(? *(select|update|insert|delete)/i', $query, $matches))
		{
		$crud_op = strtoupper($matches[1]);
		}
	else
		{
		print "Sorry, there is temporary technical problem loading this page. We have been informed of this issue and will correct it ASAP. (DBQ)";
		log_this("db_single_prepared_query() - could not determine CRUD op for " . $query . "\n"  , "error.log");
		exit();
		}


	// re-connect with static $dhb inside of db_connect(), thus allowing us to call this function [db_single_prepared_query()]
	// from within another function without having to worry about passing $dbh
	$dbh = db_connect();

	try
		{
		$start = microtime();
		$stmt = $dbh->prepare($query);

		for ($i=0;$i<sizeof($parameters);$i++)
			{
			switch ($parameters[$i]['T'])
				{
				case "I":
					$stmt->bindValue($parameters[$i]['P'], (int)$parameters[$i]['V'], PDO::PARAM_INT);
					break;
				case "S":
					$stmt->bindValue($parameters[$i]['P'], $parameters[$i]['V'], PDO::PARAM_STR);
					break;
				case "N":
					$stmt->bindValue($parameters[$i]['P'], $parameters[$i]['V'], PDO::PARAM_NULL);
					break;
				}
			}

		$stmt->execute();

		$end = microtime();
		$time_end = getmicrotime($end);
		$time_start = getmicrotime($start);
		$time = $time_end - $time_start;

		$time = (float)number_format($time,3);

		if ($time > 0.2)
			{
			log_this("Long query ($time) - " . $query , "long_query.log");
			}

		$ret = array();

		switch ($crud_op)
			{
			case "SELECT":
				// rowCount not reliable for select http://php.net/manual/en/pdostatement.rowcount.php
				// but http://wiki.hashphp.org/PDO_Tutorial_for_MySQL_Developers says OK to use?? trying both for comparison
				$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
				$ret['ROWS'] = $rows;

				$rowcount = count($rows);

				$ret['ROWCOUNT'] = $rowcount;
				break;
			case "INSERT":
				$ret['ROWCOUNT'] = $stmt->rowCount();
				$ret['LASTID'] = intval($dbh->lastInsertId()); // lastInsertId returns a string
				break;
			case "UPDATE":
			case "DELETE":
				$ret['ROWCOUNT'] = $stmt->rowCount();
			}
		}
	catch(PDOException $e)
		{
		print "Sorry, there is temporary technical problem loading this page. We have been informed of this issue and will correct it ASAP. (DBQ)";
		log_this("db_single_prepared_query() - " . $query . "\n" .  $e->getMessage() , "error.log");
		exit();
		}
	return $ret;
	}





/*******************************************************************************************/
function db_looped_prepared_query($query,$object_name, $parameters=array(), $do_prepare = false)
/*******************************************************************************************/
	{
	if (preg_match('/^ *\(? *(select|update|insert|delete)/i', $query, $matches))
		{
		$crud_op = strtoupper($matches[1]);
		}
	else
		{
		print "Sorry, there is temporary technical problem loading this page. We have been informed of this issue and will correct it ASAP. (DBQ)";
		log_this("db_single_prepared_query() - could not determine CRUD op for " . $query . "\n"  , "error.log");
		exit();
		}


	$dbh = db_connect();

	// ensure PDOStatement object is available to this function for calling multi times (preopare x 1, execute x n)
	// store it in an array, using $object_name as an index and make that static. $$object_name was not working
	static $object_holder = array();

	try
		{
		$start = microtime();

		if ($do_prepare)
			{
			$object_holder[$object_name] = $dbh->prepare($query);
			}
		else
			{
			for ($i=0;$i<sizeof($parameters);$i++)
				{
				switch ($parameters[$i]['T'])
					{
					case "I":
						$object_holder[$object_name]->bindValue($parameters[$i]['P'], (int)$parameters[$i]['V'], PDO::PARAM_INT);
						break;
					case "S":
						$object_holder[$object_name]->bindValue($parameters[$i]['P'], $parameters[$i]['V'], PDO::PARAM_STR);
						break;
					case "N":
						$object_holder[$object_name]->bindValue($parameters[$i]['P'], $parameters[$i]['V'], PDO::PARAM_NULL);
						break;
					}
				}

			$object_holder[$object_name]->execute();
			$ret = array();

			switch ($crud_op)
				{
				case "SELECT":
					// rowCount not reliable for select http://php.net/manual/en/pdostatement.rowcount.php
					// but http://wiki.hashphp.org/PDO_Tutorial_for_MySQL_Developers says OK to use?? trying both for comparison
					$rows = $object_holder[$object_name]->fetchAll(PDO::FETCH_ASSOC);
					$ret['ROWS'] = $rows;

					$rowcount = count($rows);
					$rowcountvia_rowcount = $object_holder[$object_name]->rowCount();
					//log_this("$rowcount / $rowcountvia_rowcount; \$rowcount / \$rowcountvia_rowcount", "pdo_rows_count.log");

					$ret['ROWCOUNT'] = $rowcount;
					break;
				case "INSERT":
					$ret['ROWCOUNT'] = $object_holder[$object_name]->rowCount();
					$ret['LASTID'] = intval($dbh->lastInsertId()); // lastInsertId returns a string
					break;
				case "UPDATE":
				case "DELETE":
					$ret['ROWCOUNT'] = $object_holder[$object_name]->rowCount();
				}


			return $ret;
			}

		$end = microtime();
		$time_end = getmicrotime($end);
		$time_start = getmicrotime($start);
		$time = $time_end - $time_start;

		$time = (float)number_format($time,3);

		if ($time > 0.2)
			{
			log_this("Long LOOPED query ($time) - " . $query , "long_query.log");
			}
		}
	catch(PDOException $e)
		{
		print "Sorry, there is temporary technical problem loading this page. We have been informed of this issue and will correct it ASAP. (DBQ)";
		log_this("db_single_prepared_query() - " . $query . "\n" .  $e->getMessage() , "error.log");
		exit();
		}
	}


/*******************************************************************************************/
// END function db_looped_prepared_query($query, $parameters=array(), $do_prepare = false)
/*******************************************************************************************/




function pdo_in_clause_bindings_ints(&$parameters_array,$parameter_prefix, $int_values)
	{
	if (is_array($int_values))
		$ints_array = $int_values;
	else
		$ints_array = preg_split("/,/", $int_values);


	$placeholders_array = array();

	for ($p=0;$p<count($ints_array);$p++)
		{
		$placeholder =  ":" . $parameter_prefix . "_" . $p;
		$placeholders_array[] =  $placeholder;

		$intvalue = $ints_array[$p];
		$parameters_array[] = array('P' => $placeholder, 'V'=> (int)$intvalue, 'T' => 'I');
		}

	return implode ("," , $placeholders_array);

	}



function getmicrotime($microtime){
	list($usec, $sec) = explode(" ",$microtime);
	return ((float)$usec + (float)$sec);
	}



function log_this($message, $logfile, $mailerror=0)
	{
	global $log_files, $send_errors_to,$site_email_address;
	if (preg_match("/apache/", PHP_SAPI))
		$logto = $log_files . "/" . $logfile;
	else
		$logto = $log_files . "/CLI_" . $logfile;
	$calling_script=$_SERVER["SCRIPT_NAME"];
	$logtime=date("H:i:s d/m/Y");
	error_log("$logtime $calling_script\n$message\n\n", 3, $logto);
	if ($mailerror)
		mail("$send_errors_to","D/b error", "$calling_script\n$message\n","From: $site_email_address\nReply-To: $site_email_address");

	}


?>