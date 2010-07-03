<?php

/**
 * Copyright (C) 2008-2010 FluxBB
 * based on code by Rickard Andersson copyright (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

// The FluxBB version this script updates to
define('UPDATE_TO', '1.4.0');

define('UPDATE_TO_DB_REVISION', 8);
define('UPDATE_TO_SI_REVISION', 1);
define('UPDATE_TO_PARSER_REVISION', 1);

define('MIN_PHP_VERSION', '4.3.0');
define('MIN_MYSQL_VERSION', '4.1.2');
define('MIN_PGSQL_VERSION', '7.0.0');
define('PUN_SEARCH_MIN_WORD', 3);
define('PUN_SEARCH_MAX_WORD', 20);

// The MySQL connection character set that was used for FluxBB 1.2 - in 99% of cases this should be detected automatically,
// but can be overridden using the below constant if required.
//define('FORUM_DEFAULT_CHARSET', 'latin1');


// The number of items to process per page view (lower this if the update script times out during UTF-8 conversion)
define('PER_PAGE', 300);

// Don't set to UTF-8 until after we've found out what the default character set is
define('FORUM_NO_SET_NAMES', 1);

// Make sure we are running at least MIN_PHP_VERSION
if (!function_exists('version_compare') || version_compare(PHP_VERSION, MIN_PHP_VERSION, '<'))
	exit('You are running PHP version '.PHP_VERSION.'. FluxBB '.UPDATE_TO.' requires at least PHP '.MIN_PHP_VERSION.' to run properly. You must upgrade your PHP installation before you can continue.');

define('PUN_ROOT', './');

// Attempt to load the configuration file config.php
if (file_exists(PUN_ROOT.'config.php'))
	include PUN_ROOT.'config.php';

// If we have the 1.3-legacy constant defined, define the proper 1.4 constant so we don't get an incorrect "need to install" message
if (defined('FORUM'))
	define('PUN', FORUM);

// If PUN isn't defined, config.php is missing or corrupt or we are outside the root directory
if (!defined('PUN'))
	exit('This file must be run from the forum root directory.');

// Enable debug mode
if (!defined('PUN_DEBUG'))
	define('PUN_DEBUG', 1);

// Load the functions script
require PUN_ROOT.'include/functions.php';

// Load UTF-8 functions
require PUN_ROOT.'include/utf8/utf8.php';

// Strip out "bad" UTF-8 characters
forum_remove_bad_characters();

// Reverse the effect of register_globals
forum_unregister_globals();

// Turn on full PHP error reporting
error_reporting(E_ALL);

// Force POSIX locale (to prevent functions such as strtolower() from messing up UTF-8 strings)
setlocale(LC_CTYPE, 'C');

// Turn off magic_quotes_runtime
if (get_magic_quotes_runtime())
	set_magic_quotes_runtime(0);

// Strip slashes from GET/POST/COOKIE (if magic_quotes_gpc is enabled)
if (get_magic_quotes_gpc())
{
	function stripslashes_array($array)
	{
		return is_array($array) ? array_map('stripslashes_array', $array) : stripslashes($array);
	}

	$_GET = stripslashes_array($_GET);
	$_POST = stripslashes_array($_POST);
	$_COOKIE = stripslashes_array($_COOKIE);
	$_REQUEST = stripslashes_array($_REQUEST);
}

// If a cookie name is not specified in config.php, we use the default (forum_cookie)
if (empty($cookie_name))
	$cookie_name = 'pun_cookie';

// If the cache directory is not specified, we use the default setting
if (!defined('FORUM_CACHE_DIR'))
	define('FORUM_CACHE_DIR', PUN_ROOT.'cache/');

// Turn off PHP time limit
@set_time_limit(0);

// Load DB abstraction layer and try to connect
require PUN_ROOT.'include/dblayer/common_db.php';

// Check what the default character set is - since 1.2 didn't specify any we will use whatever the default was (usually latin1)
$old_connection_charset = defined('FORUM_DEFAULT_CHARSET') ? FORUM_DEFAULT_CHARSET : $db->get_names();

// Set the connection to UTF-8 now
$db->set_names('utf8');

// Check current version
$result = $db->query('SELECT conf_value FROM '.$db->prefix.'config WHERE conf_name=\'o_cur_version\'') or error('Unable to fetch version info.', __FILE__, __LINE__, $db->error());
$cur_version = $db->result($result);

if (version_compare($cur_version, '1.2', '<'))
	exit('Version mismatch. The database \''.$db_name.'\' doesn\'t seem to be running a FluxBB database schema supported by this update script.');

// Do some DB type specific checks
$mysql = false;
switch ($db_type)
{
	case 'mysql':
	case 'mysqli':
	case 'mysql_innodb':
	case 'mysqli_innodb':
		$mysql_info = $db->get_version();
		if (version_compare($mysql_info['version'], MIN_MYSQL_VERSION, '<'))
			error('You are running MySQL version '.$mysql_version.'. FluxBB '.UPDATE_TO.' requires at least MySQL '.MIN_MYSQL_VERSION.' to run properly. You must upgrade your MySQL installation before you can continue.');

		$mysql = true;
		break;

	case 'pgsql':
		$pgsql_info = $db->get_version();
		if (version_compare($pgsql_info['version'], MIN_PGSQL_VERSION, '<'))
			error('You are running PostgreSQL version '.$pgsql_info.'. FluxBB '.UPDATE_TO.' requires at least PostgreSQL '.MIN_PGSQL_VERSION.' to run properly. You must upgrade your PostgreSQL installation before you can continue.');

		break;
}

// Get the forum config
$result = $db->query('SELECT * FROM '.$db->prefix.'config') or error('Unable to fetch config.', __FILE__, __LINE__, $db->error());
while ($cur_config_item = $db->fetch_row($result))
	$pun_config[$cur_config_item[0]] = $cur_config_item[1];

// Check the database revision and the current version
if (isset($pun_config['o_database_revision']) && $pun_config['o_database_revision'] >= UPDATE_TO_DB_REVISION &&
		isset($pun_config['o_searchindex_revision']) && $pun_config['o_searchindex_revision'] >= UPDATE_TO_SI_REVISION &&
		isset($pun_config['o_parser_revision']) && $pun_config['o_parser_revision'] >= UPDATE_TO_PARSER_REVISION &&
		version_compare($pun_config['o_cur_version'], UPDATE_TO, '>='))
	exit('Your database is already as up-to-date as this script can make it.');

$default_style = $pun_config['o_default_style'];
if (!file_exists(PUN_ROOT.'style/'.$default_style.'.css'))
	$default_style = 'Air';

//
// Determines whether $str is UTF-8 encoded or not
//
function seems_utf8($str)
{
	$str_len = strlen($str);
	for ($i = 0; $i < $str_len; ++$i)
	{
		if (ord($str[$i]) < 0x80) continue; # 0bbbbbbb
		else if ((ord($str[$i]) & 0xE0) == 0xC0) $n=1; # 110bbbbb
		else if ((ord($str[$i]) & 0xF0) == 0xE0) $n=2; # 1110bbbb
		else if ((ord($str[$i]) & 0xF8) == 0xF0) $n=3; # 11110bbb
		else if ((ord($str[$i]) & 0xFC) == 0xF8) $n=4; # 111110bb
		else if ((ord($str[$i]) & 0xFE) == 0xFC) $n=5; # 1111110b
		else return false; # Does not match any model

		for ($j = 0; $j < $n; ++$j) # n bytes matching 10bbbbbb follow ?
		{
			if ((++$i == strlen($str)) || ((ord($str[$i]) & 0xC0) != 0x80))
				return false;
		}
	}

	return true;
}


//
// Translates the number from a HTML numeric entity into an UTF-8 character
//
function dcr2utf8($src)
{
	$dest = '';
	if ($src < 0)
		return false;
	else if ($src <= 0x007f)
		$dest .= chr($src);
	else if ($src <= 0x07ff)
	{
		$dest .= chr(0xc0 | ($src >> 6));
		$dest .= chr(0x80 | ($src & 0x003f));
	}
	else if ($src == 0xFEFF)
	{
		// nop -- zap the BOM
	}
	else if ($src >= 0xD800 && $src <= 0xDFFF)
	{
		// found a surrogate
		return false;
	}
	else if ($src <= 0xffff)
	{
		$dest .= chr(0xe0 | ($src >> 12));
		$dest .= chr(0x80 | (($src >> 6) & 0x003f));
		$dest .= chr(0x80 | ($src & 0x003f));
	}
	else if ($src <= 0x10ffff)
	{
		$dest .= chr(0xf0 | ($src >> 18));
		$dest .= chr(0x80 | (($src >> 12) & 0x3f));
		$dest .= chr(0x80 | (($src >> 6) & 0x3f));
		$dest .= chr(0x80 | ($src & 0x3f));
	}
	else
	{
		// out of range
		return false;
	}

	return $dest;
}


//
// Attempts to convert $str from $old_charset to UTF-8. Also converts HTML entities (including numeric entities) to UTF-8 characters
//
function convert_to_utf8(&$str, $old_charset)
{
	if ($str === null || $str == '')
		return false;

	$save = $str;

	// Replace literal entities (for non-UTF-8 compliant html_entity_encode)
	if (version_compare(PHP_VERSION, '5.0.0', '<') && $old_charset == 'ISO-8859-1' || $old_charset == 'ISO-8859-15')
		$str = html_entity_decode($str, ENT_QUOTES, $old_charset);

	if ($old_charset != 'UTF-8' && !seems_utf8($str))
	{
		if (function_exists('iconv'))
			$str = iconv($old_charset == 'ISO-8859-1' ? 'WINDOWS-1252' : 'ISO-8859-1', 'UTF-8', $str);
		else if (function_exists('mb_convert_encoding'))
			$str = mb_convert_encoding($str, 'UTF-8', $old_charset == 'ISO-8859-1' ? 'WINDOWS-1252' : 'ISO-8859-1');
		else if ($old_charset == 'ISO-8859-1')
			$str = utf8_encode($str);
	}

	// Replace literal entities (for UTF-8 compliant html_entity_encode)
	if (version_compare(PHP_VERSION, '5.0.0', '>='))
		$str = html_entity_decode($str, ENT_QUOTES, 'UTF-8');

	// Replace numeric entities
	$str = preg_replace_callback('/&#([0-9]+);/', 'utf8_callback_1', $str);
	$str = preg_replace_callback('/&#x([a-f0-9]+);/i', 'utf8_callback_2', $str);

	// Remove "bad" characters
	$str = remove_bad_characters($str);

	return ($save != $str);
}


function utf8_callback_1($matches)
{
	return dcr2utf8($matches[1]);
}


function utf8_callback_2($matches)
{
	return dcr2utf8(hexdec($matches[1]));
}


//
// Alter a table to be utf8. MySQL only
// Function based on update_convert_table_utf8() from the Drupal project (http://drupal.org/)
//
function alter_table_utf8($table)
{
	global $mysql, $db;
	static $types;

	if (!$mysql)
		return;

	if (!isset($types))
	{
		$types = array(
			'char'			=> 'binary',
			'varchar'		=> 'varbinary',
			'tinytext'		=> 'tinyblob',
			'mediumtext'	=> 'mediumblob',
			'text'			=> 'blob',
			'longtext'		=> 'longblob'
		);
	}

	// Set table default charset to utf8
	$db->query('ALTER TABLE '.$table.' CHARACTER SET utf8 COLLATE utf8_bin') or error('Unable to set table character set', __FILE__, __LINE__, $db->error());

	// Find out which columns need converting and build SQL statements
	$result = $db->query('SHOW FULL COLUMNS FROM '.$table) or error('Unable to fetch column information', __FILE__, __LINE__, $db->error());
	while ($cur_column = $db->fetch_assoc($result))
	{
		if ($cur_column['Collation'] === null)
			continue;

		list($type) = explode('(', $cur_column['Type']);
		if (isset($types[$type]) && strpos($cur_column['Collation'], 'utf8') === false)
		{
			$allow_null = ($cur_column['Null'] == 'YES');
			$collate = (substr($cur_column['Collation'], -3) == 'bin') ? 'utf8_bin' : 'utf8_general_ci';

			$db->alter_field($table, $cur_column['Field'], preg_replace('/'.$type.'/i', $types[$type], $cur_column['Type']), $allow_null, $cur_column['Default'], null, true) or error('Unable to alter field to binary', __FILE__, __LINE__, $db->error());
			$db->alter_field($table, $cur_column['Field'], $cur_column['Type'].' CHARACTER SET utf8 COLLATE '.$collate, $allow_null, $cur_column['Default'], null, true) or error('Unable to alter field to utf8', __FILE__, __LINE__, $db->error());
		}
	}
}

//
// Safely converts text type columns into utf8
// If finished returns true, otherwise returns $end_at
//
function convert_table_utf8($table, $callback, $old_charset, $key = null, $start_at = null)
{
	global $mysql, $db, $old_connection_charset;

	$finished = true;
	$end_at = 0;
	if ($mysql)
	{
		// Only set up the tables if we are doing this in 1 go, or its the first go
		if ($start_at === null || $start_at == 0)
		{
			// Drop any temp table that exists, in-case it's left over from a failed update
			$db->drop_table($table.'_utf8', true) or error('Unable to drop left over temp table', __FILE__, __LINE__, $db->error());

			// Copy the table
			$db->query('CREATE TABLE '.$table.'_utf8 LIKE '.$table) or error('Unable to create new table', __FILE__, __LINE__, $db->error());

			// Set table default charset to utf8
			alter_table_utf8($table.'_utf8');
		}

		// Change to the old character set so MySQL doesn't attempt to perform conversion on the data from the old table
		$db->set_names($old_connection_charset);

		// Move & Convert everything
		$result = $db->query('SELECT * FROM '.$table.($start_at === null ? '' : ' WHERE '.$key.'>'.$start_at).' ORDER BY '.$key.' ASC'.($start_at === null ? '' : ' LIMIT '.PER_PAGE), false) or error('Unable to select from old table', __FILE__, __LINE__, $db->error());

		// Change back to utf8 mode so we can insert it into the new table
		$db->set_names('utf8');

		while ($cur_item = $db->fetch_assoc($result))
		{
			$cur_item = call_user_func($callback, $cur_item, $old_charset);

			$temp = array();
			foreach ($cur_item as $idx => $value)
				$temp[$idx] = $value === null ? 'NULL' : '\''.$db->escape($value).'\'';

			$db->query('INSERT INTO '.$table.'_utf8('.implode(',', array_keys($temp)).') VALUES ('.implode(',', array_values($temp)).')') or error('Unable to insert data to new table', __FILE__, __LINE__, $db->error());

			$end_at = $cur_item[$key];
		}

		// If we aren't doing this all in 1 go and $end_at has a value (i.e. we have processed at least 1 row), figure out if we have more to do or not
		if ($start_at !== null && $end_at > 0)
		{
			$result = $db->query('SELECT 1 FROM '.$table.' WHERE '.$key.'>'.$end_at.' ORDER BY '.$key.' ASC LIMIT 1') or error('Unable to check for next row', __FILE__, __LINE__, $db->error());
			$finished = $db->num_rows($result) == 0;
		}

		// Only swap the tables if we are doing this in 1 go, or its the last go
		if ($finished)
		{
			// Delete old table
			$db->drop_table($table, true) or error('Unable to drop old table', __FILE__, __LINE__, $db->error());

			// Rename table
			$db->query('ALTER TABLE '.$table.'_utf8 RENAME '.$table) or error('Unable to rename new table', __FILE__, __LINE__, $db->error());

			return true;
		}

		return $end_at;
	}
	else
	{
		// Convert everything
		$result = $db->query('SELECT * FROM '.$table.($start_at === null ? '' : ' WHERE '.$key.'>'.$start_at).' ORDER BY '.$key.' ASC'.($start_at === null ? '' : ' LIMIT '.PER_PAGE)) or error('Unable to select from table', __FILE__, __LINE__, $db->error());
		while ($cur_item = $db->fetch_assoc($result))
		{
			$cur_item = call_user_func($callback, $cur_item, $old_charset);

			$temp = array();
			foreach ($cur_item as $idx => $value)
				$temp[] = $idx.'='.($value === null ? 'NULL' : '\''.$db->escape($value).'\'');

			if (!empty($temp))
				$db->query('UPDATE '.$table.' SET '.implode(', ', $temp).' WHERE '.$key.'=\''.$db->escape($cur_item[$key]).'\'') or error('Unable to update data', __FILE__, __LINE__, $db->error());

			$end_at = $cur_item[$key];
		}

		if ($start_at !== null && $end_at > 0)
		{
			$result = $db->query('SELECT 1 FROM '.$table.' WHERE '.$key.'>'.$end_at.' ORDER BY '.$key.' ASC LIMIT 1') or error('Unable to check for next row', __FILE__, __LINE__, $db->error());
			if ($db->num_rows($result) == 0)
				return true;

			return $end_at;
		}

		return true;
	}
}


//
// Converts a MySQL tables collation to binary
//
function convert_table_bin($table, $charset)
{
	global $db;

	// Set table default collation to binary
	$db->query('ALTER TABLE '.$table.' COLLATE '.$charset.'_bin') or error('Unable to set table collation', __FILE__, __LINE__, $db->error());

	// Find out which columns need converting and build SQL statements
	$result = $db->query('SHOW FULL COLUMNS FROM '.$table) or error('Unable to fetch column information', __FILE__, __LINE__, $db->error());
	while ($cur_column = $db->fetch_assoc($result))
	{
		if ($cur_column['Collation'] === null)
			continue;

		// If it isn't binary, it should be!
		if (substr($cur_column['Collation'], -3) != 'bin')
		{
			$allow_null = ($cur_column['Null'] == 'YES');

			$db->alter_field($table, $cur_column['Field'], $cur_column['Type'].' COLLATE '.$charset.'_bin', $allow_null, $cur_column['Default'], null, true) or error('Unable to alter field to binary', __FILE__, __LINE__, $db->error());
		}
	}
}


header('Content-type: text/html; charset=utf-8');

// Empty all output buffers and stop buffering
while (@ob_end_clean());


$stage = isset($_GET['stage']) ? $_GET['stage'] : '';
$old_charset = isset($_GET['req_old_charset']) ? str_replace('ISO8859', 'ISO-8859', strtoupper($_GET['req_old_charset'])) : 'ISO-8859-1';
$start_at = isset($_GET['start_at']) ? intval($_GET['start_at']) : 0;
$query_str = '';

switch ($stage)
{
	// Show form
	case '':

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">

<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en" dir="ltr">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>FluxBB Database Update</title>
<link rel="stylesheet" type="text/css" href="style/<?php echo $default_style ?>.css" />
</head>
<body>

<div id="pundb_update" class="pun">
<div class="top-box"><div><!-- Top Corners --></div></div>
<div class="punwrap">

<div class="blockform">
	<h2><span>FluxBB Update</span></h2>
	<div class="box">
		<form method="get" action="<?php echo pun_htmlspecialchars($_SERVER['REQUEST_URI']) ?>" onsubmit="this.start.disabled=true">
		<input type="hidden" name="stage" value="start" />
			<div class="inform">
				<div class="forminfo">
					<p style="font-size: 1.1em">This script will update your forum database. The update procedure might take anything from a second to hours depending on the speed of the server and the size of the forum database. Don't forget to make a backup of the database before continuing.</p>
					<p style="font-size: 1.1em">Did you read the update instructions in the documentation? If not, start there.</p>
<?php

if (strpos($cur_version, '1.2') === 0)
{
	if (!function_exists('iconv') && !function_exists('mb_convert_encoding'))
	{

?>
					<p style="font-size: 1.1em"><strong>IMPORTANT!</strong> FluxBB has detected that this PHP environment does not have support for the encoding mechanisms required to do UTF-8 conversion from character sets other than ISO-8859-1. What this means is that if the current character set is not ISO-8859-1, FluxBB won't be able to convert your forum database to UTF-8 and you will have to do it manually. Instructions for doing manual charset conversion can be found in the update instructions.</p>
<?php

	}

?>
				</div>
			</div>
			<div class="inform">
				<div class="forminfo">
					<p style="font-size: 1.1em"><strong>Enable conversion:</strong> When enabled this update script will, after it has made the required structural changes to the database, convert all text in the database from the current character set to UTF-8. This conversion is required if you're upgrading from version 1.2.</p>
					<p style="font-size: 1.1em"><strong>Current character set:</strong> If the primary language in your forum is English, you can leave this at the default value. However, if your forum is non-English, you should enter the character set of the primary language pack used in the forum. <i>Getting this wrong can corrupt your database so don't just guess!</i> Note: This is required even if the old database is UTF-8.</p>
				</div>
				<fieldset>
					<legend>Charset conversion</legend>
					<div class="infldset">
						<div class="rbox">
							<label><input type="checkbox" name="convert_charset" value="1" checked="checked" /><strong>Enable conversion</strong> (perform database charset conversion).<br /></label>
						</div>
						<label>
							<strong>Current character set</strong><br />Accept default for English forums otherwise the character set of the primary language pack.<br />
							<input type="text" name="req_old_charset" size="12" maxlength="20" value="<?php echo $old_charset ?>" /><br />
						</label>
					</div>
				</fieldset>
<?php

}
else
	echo "\t\t\t\t".'</div>'."\n";

?>
			</div>
			<p class="buttons"><input type="submit" name="start" value="Start update" /></p>
		</form>
	</div>
</div>

</div>
<div class="end-box"><div><!-- Bottom Corners --></div></div>
</div>

</body>
</html>
<?php

		break;


	// Start by updating the database structure
	case 'start':
		$query_str = '?stage=preparse_posts';

		// If we don't need to update the database, skip this stage
		if (isset($pun_config['o_database_revision']) && $pun_config['o_database_revision'] >= UPDATE_TO_DB_REVISION)
			break;

		// If we are using MySQL we need to make sure the tables have been converted to binary instead of general_ci
		// otherwise it is possible to have multiple usernames that are "indentical", causing an error in upgrading.
		if ($mysql && $pun_config['o_database_revision'] < 8) // This was done in DB revision 8, don't bother attempting it again if it's already done...
		{
			$result = $db->query('SHOW TABLE STATUS LIKE \''.$db->prefix.'%\'') or error('Unable to list tables', __FILE__, __LINE__, $db->error());
			while ($status = $db->fetch_assoc($result))
			{
				// Figure out the existing charset for this table, we don't want to change it yet - only the collation
				list ($charset) = explode('_', $status['Collation']);
				convert_table_bin($status['Name'], $charset);
			}

			// Update the default charset and collation (MySQL only)
			$db->query('ALTER DATABASE '.$db_name.' CHARACTER SET utf8 COLLATE utf8_bin') or error('Unable to update default characterset', __FILE__, __LINE__, $db->error());
		}

		// Make all email fields VARCHAR(80)
		$db->alter_field('bans', 'email', 'VARCHAR(80)', true) or error('Unable to alter email field', __FILE__, __LINE__, $db->error());
		$db->alter_field('posts', 'poster_email', 'VARCHAR(80)', true) or error('Unable to alter poster_email field', __FILE__, __LINE__, $db->error());
		$db->alter_field('users', 'email', 'VARCHAR(80)', false, '') or error('Unable to alter email field', __FILE__, __LINE__, $db->error());
		$db->alter_field('users', 'jabber', 'VARCHAR(80)', true) or error('Unable to alter jabber field', __FILE__, __LINE__, $db->error());
		$db->alter_field('users', 'msn', 'VARCHAR(80)', true) or error('Unable to alter msn field', __FILE__, __LINE__, $db->error());
		$db->alter_field('users', 'activate_string', 'VARCHAR(80)', true) or error('Unable to alter activate_string field', __FILE__, __LINE__, $db->error());

		// Make all IP fields VARCHAR(39) to support IPv6
		$db->alter_field('posts', 'poster_ip', 'VARCHAR(39)', true) or error('Unable to alter poster_ip field', __FILE__, __LINE__, $db->error());
		$db->alter_field('users', 'registration_ip', 'VARCHAR(39)', false, '0.0.0.0') or error('Unable to alter registration_ip field', __FILE__, __LINE__, $db->error());

		// Add the DST option to the users table
		$db->add_field('users', 'dst', 'TINYINT(1)', false, 0, 'timezone') or error('Unable to add dst field', __FILE__, __LINE__, $db->error());

		// Add the last_post field to the online table
		$db->add_field('online', 'last_post', 'INT(10) UNSIGNED', true, null, null) or error('Unable to add last_post field', __FILE__, __LINE__, $db->error());

		// Add the last_search field to the online table
		$db->add_field('online', 'last_search', 'INT(10) UNSIGNED', true, null, null) or error('Unable to add last_search field', __FILE__, __LINE__, $db->error());

		// Add the last_search column to the users table
		$db->add_field('users', 'last_search', 'INT(10) UNSIGNED', true, null, 'last_post') or error('Unable to add last_search field', __FILE__, __LINE__, $db->error());

		// Drop use_avatar column from users table
		$db->drop_field('users', 'use_avatar') or error('Unable to drop use_avatar field', __FILE__, __LINE__, $db->error());

		// Drop save_pass column from users table
		$db->drop_field('users', 'save_pass') or error('Unable to drop save_pass field', __FILE__, __LINE__, $db->error());

		// Drop g_edit_subjects_interval column from groups table
		$db->drop_field('groups', 'g_edit_subjects_interval');

		// Add database revision number
		if (!array_key_exists('o_database_revision', $pun_config))
			$db->query('INSERT INTO '.$db->prefix.'config (conf_name, conf_value) VALUES (\'o_database_revision\', \'0\')') or error('Unable to insert config value \'o_database_revision\'', __FILE__, __LINE__, $db->error());

		// Add search index revision number
		if (!array_key_exists('o_searchindex_revision', $pun_config))
			$db->query('INSERT INTO '.$db->prefix.'config (conf_name, conf_value) VALUES (\'o_searchindex_revision\', \'0\')') or error('Unable to insert config value \'o_searchindex_revision\'', __FILE__, __LINE__, $db->error());

		// Add parser revision number
		if (!array_key_exists('o_parser_revision', $pun_config))
			$db->query('INSERT INTO '.$db->prefix.'config (conf_name, conf_value) VALUES (\'o_parser_revision\', \'0\')') or error('Unable to insert config value \'o_parser_revision\'', __FILE__, __LINE__, $db->error());

		// Add default email setting option
		if (!array_key_exists('o_default_email_setting', $pun_config))
			$db->query('INSERT INTO '.$db->prefix.'config (conf_name, conf_value) VALUES (\'o_default_email_setting\', \'1\')') or error('Unable to insert config value \'o_default_email_setting\'', __FILE__, __LINE__, $db->error());

		// Make sure we have o_additional_navlinks (was added in 1.2.1)
		if (!array_key_exists('o_additional_navlinks', $pun_config))
			$db->query('INSERT INTO '.$db->prefix.'config (conf_name, conf_value) VALUES (\'o_additional_navlinks\', \'\')') or error('Unable to insert config value \'o_additional_navlinks\'', __FILE__, __LINE__, $db->error());

		// Insert new config option o_topic_views
		if (!array_key_exists('o_topic_views', $pun_config))
			$db->query('INSERT INTO '.$db->prefix.'config (conf_name, conf_value) VALUES (\'o_topic_views\', \'1\')') or error('Unable to insert config value \'o_topic_views\'', __FILE__, __LINE__, $db->error());

		// Insert new config option o_signatures
		if (!array_key_exists('o_signatures', $pun_config))
			$db->query('INSERT INTO '.$db->prefix.'config (conf_name, conf_value) VALUES (\'o_signatures\', \'1\')') or error('Unable to insert config value \'o_signatures\'', __FILE__, __LINE__, $db->error());

		// Insert new config option o_smtp_ssl
		if (!array_key_exists('o_smtp_ssl', $pun_config))
			$db->query('INSERT INTO '.$db->prefix.'config (conf_name, conf_value) VALUES (\'o_smtp_ssl\', \'0\')') or error('Unable to insert config value \'o_smtp_ssl\'', __FILE__, __LINE__, $db->error());

		// Insert new config option o_default_dst
		if (!array_key_exists('o_default_dst', $pun_config))
			$db->query('INSERT INTO '.$db->prefix.'config (conf_name, conf_value) VALUES (\'o_default_dst\', \'0\')') or error('Unable to insert config value \'o_default_dst\'', __FILE__, __LINE__, $db->error());

		// Insert new config option o_quote_depth
		if (!array_key_exists('o_quote_depth', $pun_config))
			$db->query('INSERT INTO '.$db->prefix.'config (conf_name, conf_value) VALUES (\'o_quote_depth\', \'3\')') or error('Unable to insert config value \'o_quote_depth\'', __FILE__, __LINE__, $db->error());

		// Insert new config option o_feed_type
		if (!array_key_exists('o_feed_type', $pun_config))
			$db->query('INSERT INTO '.$db->prefix.'config (conf_name, conf_value) VALUES (\'o_feed_type\', \'2\')') or error('Unable to insert config value \'o_feed_type\'', __FILE__, __LINE__, $db->error());

		// Insert config option o_base_url which was removed in 1.3
		if (!array_key_exists('o_base_url', $pun_config))
		{
			// If it isn't in $pun_config['o_base_url'] it should be in $base_url, but just in-case it isn't we can make a guess at it
			if (!isset($base_url))
			{
				// Make an educated guess regarding base_url
				$base_url  = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') ? 'https://' : 'http://';	// protocol
				$base_url .= preg_replace('/:(80|443)$/', '', $_SERVER['HTTP_HOST']);							// host[:port]
				$base_url .= str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));							// path
			}

			if (substr($base_url, -1) == '/')
				$base_url = substr($base_url, 0, -1);

			$db->query('INSERT INTO '.$db->prefix.'config (conf_name, conf_value) VALUES (\'o_base_url\', \''.$db->escape($base_url).'\')') or error('Unable to insert config value \'o_quote_depth\'', __FILE__, __LINE__, $db->error());
		}

		if (strpos($cur_version, '1.2') === 0)
		{
			// Groups are almost the same as 1.2:
			// unverified:	32000 -> 0

			$db->query('UPDATE '.$db->prefix.'users SET group_id = 0 WHERE group_id = 32000') or error('Unable to update unverified users', __FILE__, __LINE__, $db->error());
		}
		else if (strpos($cur_version, '1.3') === 0)
		{
			// Groups have changed quite a lot from 1.3:
			// unverified:	0 -> 0
			// admin:		1 -> 1
			// mod:			? -> 2
			// guest:		2 -> 3
			// member:		? -> 4

			$result = $db->query('SELECT MAX(g_id) + 1 FROM '.$db->prefix.'groups') or error('Unable to select temp group ID', __FILE__, __LINE__, $db->error());
			$temp_id = $db->result($result);

			$result = $db->query('SELECT g_id FROM '.$db->prefix.'groups WHERE g_moderator = 1 AND g_id > 1 LIMIT 1') or error('Unable to select moderator group', __FILE__, __LINE__, $db->error());
			if ($db->num_rows($result))
				$mod_gid = $db->result($result);
			else
			{
				$db->query('INSERT INTO '.$db->prefix.'groups (g_title, g_user_title, g_moderator, g_mod_edit_users, g_mod_rename_users, g_mod_change_passwords, g_mod_ban_users, g_read_board, g_view_users, g_post_replies, g_post_topics, g_edit_posts, g_delete_posts, g_delete_topics, g_set_title, g_search, g_search_users, g_send_email, g_post_flood, g_search_flood, g_email_flood) VALUES('."'Moderators', 'Moderator', 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 0, 0, 0)") or error('Unable to add group', __FILE__, __LINE__, $db->error());
				$mod_gid = $db->insert_id();
			}

			$member_gid = $pun_config['o_default_user_group'];

			// move the mod group to a temp place
			$db->query('UPDATE '.$db->prefix.'groups SET g_id = '.$temp_id.' WHERE g_id = '.$mod_gid) or error('Unable to update group ID', __FILE__, __LINE__, $db->error());
			$db->query('UPDATE '.$db->prefix.'users SET group_id = '.$temp_id.' WHERE group_id = '.$mod_gid) or error('Unable to update users group ID', __FILE__, __LINE__, $db->error());
			$db->query('UPDATE '.$db->prefix.'forum_perms SET group_id = '.$temp_id.' WHERE group_id = '.$mod_gid) or error('Unable to forum_perms group ID', __FILE__, __LINE__, $db->error());
			if ($member_gid == $mod_gid) $member_gid = $temp_id;

			// move whoever is in 3 to a spare slot
			$db->query('UPDATE '.$db->prefix.'groups SET g_id = '.$mod_gid.' WHERE g_id = 3') or error('Unable to update group ID', __FILE__, __LINE__, $db->error());
			$db->query('UPDATE '.$db->prefix.'users SET group_id = '.$mod_gid.' WHERE group_id = 3') or error('Unable to update users group ID', __FILE__, __LINE__, $db->error());
			$db->query('UPDATE '.$db->prefix.'forum_perms SET group_id = '.$mod_gid.' WHERE group_id = 3') or error('Unable to forum_perms group ID', __FILE__, __LINE__, $db->error());
			if ($member_gid == 3) $member_gid = $mod_gid;

			// move guest to 3
			$db->query('UPDATE '.$db->prefix.'groups SET g_id = 3 WHERE g_id = 2') or error('Unable to update group ID', __FILE__, __LINE__, $db->error());
			$db->query('UPDATE '.$db->prefix.'users SET group_id = 3 WHERE group_id = 2') or error('Unable to update users group ID', __FILE__, __LINE__, $db->error());
			$db->query('UPDATE '.$db->prefix.'forum_perms SET group_id = 3 WHERE group_id = 2') or error('Unable to forum_perms group ID', __FILE__, __LINE__, $db->error());
			if ($member_gid == 2) $member_gid = 3;

			// move mod group in temp place to 2
			$db->query('UPDATE '.$db->prefix.'groups SET g_id = 2 WHERE g_id = '.$temp_id) or error('Unable to update group ID', __FILE__, __LINE__, $db->error());
			$db->query('UPDATE '.$db->prefix.'users SET group_id = 2 WHERE group_id = '.$temp_id) or error('Unable to update users group ID', __FILE__, __LINE__, $db->error());
			$db->query('UPDATE '.$db->prefix.'forum_perms SET group_id = 2 WHERE group_id = '.$temp_id) or error('Unable to forum_perms group ID', __FILE__, __LINE__, $db->error());
			if ($member_gid == $temp_id) $member_gid = 2;

			// Only move stuff around if it isn't already in the right place
			if ($member_gid != $mod_gid || $member_gid != 4)
			{
				// move members to temp place
				$db->query('UPDATE '.$db->prefix.'groups SET g_id = '.$temp_id.' WHERE g_id = '.$member_gid) or error('Unable to update group ID', __FILE__, __LINE__, $db->error());
				$db->query('UPDATE '.$db->prefix.'users SET group_id = '.$temp_id.' WHERE group_id = '.$member_gid) or error('Unable to update users group ID', __FILE__, __LINE__, $db->error());
				$db->query('UPDATE '.$db->prefix.'forum_perms SET group_id = '.$temp_id.' WHERE group_id = '.$member_gid) or error('Unable to forum_perms group ID', __FILE__, __LINE__, $db->error());

				// move whoever is in 4 to members place
				$db->query('UPDATE '.$db->prefix.'groups SET g_id = '.$member_gid.' WHERE g_id = 4') or error('Unable to update group ID', __FILE__, __LINE__, $db->error());
				$db->query('UPDATE '.$db->prefix.'users SET group_id = '.$member_gid.' WHERE group_id = 4') or error('Unable to update users group ID', __FILE__, __LINE__, $db->error());
				$db->query('UPDATE '.$db->prefix.'forum_perms SET group_id = '.$member_gid.' WHERE group_id = 4') or error('Unable to forum_perms group ID', __FILE__, __LINE__, $db->error());

				// move members in temp place to 4
				$db->query('UPDATE '.$db->prefix.'groups SET g_id = 4 WHERE g_id = '.$temp_id) or error('Unable to update group ID', __FILE__, __LINE__, $db->error());
				$db->query('UPDATE '.$db->prefix.'users SET group_id = 4 WHERE group_id = '.$temp_id) or error('Unable to update users group ID', __FILE__, __LINE__, $db->error());
				$db->query('UPDATE '.$db->prefix.'forum_perms SET group_id = 4 WHERE group_id = '.$temp_id) or error('Unable to forum_perms group ID', __FILE__, __LINE__, $db->error());
			}

			$db->query('UPDATE '.$db->prefix.'config SET conf_value=\''.$member_gid.'\' WHERE conf_name=\'o_default_user_group\'') or error('Unable to update default user group ID', __FILE__, __LINE__, $db->error());
		}

		// Server time zone is now simply the default time zone
		if (!array_key_exists('o_default_timezone', $pun_config))
			$db->query('UPDATE '.$db->prefix.'config SET conf_name = \'o_default_timezone\' WHERE conf_name = \'o_server_timezone\'') or error('Unable to update time zone config', __FILE__, __LINE__, $db->error());

		// Increase visit timeout to 30 minutes (only if it hasn't been changed from the default)
		if (!array_key_exists('o_database_revision', $pun_config) && $pun_config['o_timeout_visit'] == '600')
			$db->query('UPDATE '.$db->prefix.'config SET conf_value = \'1800\' WHERE conf_name = \'o_timeout_visit\'') or error('Unable to update visit timeout config', __FILE__, __LINE__, $db->error());

		// Remove obsolete g_post_polls permission from groups table
		$db->drop_field('groups', 'g_post_polls');

		// Make room for multiple moderator groups
		if (!$db->field_exists('groups', 'g_moderator'))
		{
			// Add g_moderator column to groups table
			$db->add_field('groups', 'g_moderator', 'TINYINT(1)', false, 0, 'g_user_title') or error('Unable to add g_moderator field', __FILE__, __LINE__, $db->error());

			// Give the moderator group moderator privileges
			$db->query('UPDATE '.$db->prefix.'groups SET g_moderator = 1 WHERE g_id = 2') or error('Unable to update moderator powers', __FILE__, __LINE__, $db->error());
		}

		// Replace obsolete p_mod_edit_users config setting with new per-group permission
		if (array_key_exists('p_mod_edit_users', $pun_config))
		{
			$db->query('DELETE FROM '.$db->prefix.'config WHERE conf_name = \'p_mod_edit_users\'') or error('Unable to update moderator powers', __FILE__, __LINE__, $db->error());

			$db->add_field('groups', 'g_mod_edit_users', 'TINYINT(1)', false, 0, 'g_moderator') or error('Unable to add g_mod_edit_users field', __FILE__, __LINE__, $db->error());

			$db->query('UPDATE '.$db->prefix.'groups SET g_mod_edit_users = '.$pun_config['p_mod_edit_users'].' WHERE g_moderator = 1') or error('Unable to update moderator powers', __FILE__, __LINE__, $db->error());
		}

		// Replace obsolete p_mod_rename_users config setting with new per-group permission
		if (array_key_exists('p_mod_rename_users', $pun_config))
		{
			$db->query('DELETE FROM '.$db->prefix.'config WHERE conf_name = \'p_mod_rename_users\'') or error('Unable to update moderator powers', __FILE__, __LINE__, $db->error());

			$db->add_field('groups', 'g_mod_rename_users', 'TINYINT(1)', false, 0, 'g_mod_edit_users') or error('Unable to add g_mod_rename_users field', __FILE__, __LINE__, $db->error());

			$db->query('UPDATE '.$db->prefix.'groups SET g_mod_rename_users = '.$pun_config['p_mod_rename_users'].' WHERE g_moderator = 1') or error('Unable to update moderator powers', __FILE__, __LINE__, $db->error());
		}

		// Replace obsolete p_mod_change_passwords config setting with new per-group permission
		if (array_key_exists('p_mod_change_passwords', $pun_config))
		{
			$db->query('DELETE FROM '.$db->prefix.'config WHERE conf_name = \'p_mod_change_passwords\'') or error('Unable to update moderator powers', __FILE__, __LINE__, $db->error());

			$db->add_field('groups', 'g_mod_change_passwords', 'TINYINT(1)', false, 0, 'g_mod_rename_users') or error('Unable to add g_mod_change_passwords field', __FILE__, __LINE__, $db->error());

			$db->query('UPDATE '.$db->prefix.'groups SET g_mod_change_passwords = '.$pun_config['p_mod_change_passwords'].' WHERE g_moderator = 1') or error('Unable to update moderator powers', __FILE__, __LINE__, $db->error());
		}

		// Replace obsolete p_mod_ban_users config setting with new per-group permission
		if (array_key_exists('p_mod_ban_users', $pun_config))
		{
			$db->query('DELETE FROM '.$db->prefix.'config WHERE conf_name = \'p_mod_ban_users\'') or error('Unable to update moderator powers', __FILE__, __LINE__, $db->error());

			$db->add_field('groups', 'g_mod_ban_users', 'TINYINT(1)', false, 0, 'g_mod_change_passwords') or error('Unable to add g_mod_ban_users field', __FILE__, __LINE__, $db->error());

			$db->query('UPDATE '.$db->prefix.'groups SET g_mod_ban_users = '.$pun_config['p_mod_ban_users'].' WHERE g_moderator = 1') or error('Unable to update moderator powers', __FILE__, __LINE__, $db->error());
		}

		// We need to add a unique index to avoid users having multiple rows in the online table
		if (!$db->index_exists('online', 'user_id_ident_idx'))
		{
			$db->truncate_table('online') or error('Unable to clear online table', __FILE__, __LINE__, $db->error());

			if ($mysql)
				$db->add_index('online', 'user_id_ident_idx', array('user_id', 'ident(25)'), true) or error('Unable to add user_id_ident_idx index', __FILE__, __LINE__, $db->error());
			else
				$db->add_index('online', 'user_id_ident_idx', array('user_id', 'ident'), true) or error('Unable to add user_id_ident_idx index', __FILE__, __LINE__, $db->error());
		}

		// Remove the redundant user_id_idx on the online table
		$db->drop_index('online', 'user_id_idx') or error('Unable to drop user_id_idx index', __FILE__, __LINE__, $db->error());

		// Add an index to ident on the online table
		if ($mysql)
			$db->add_index('online', 'ident_idx', array('ident(25)')) or error('Unable to add ident_idx index', __FILE__, __LINE__, $db->error());
		else
			$db->add_index('online', 'ident_idx', array('ident')) or error('Unable to add ident_idx index', __FILE__, __LINE__, $db->error());

		// Add an index to logged in the online table
		$db->add_index('online', 'logged_idx', array('logged')) or error('Unable to add logged_idx index', __FILE__, __LINE__, $db->error());

		// Add an index to last_post in the topics table
		$db->add_index('topics', 'last_post_idx', array('last_post')) or error('Unable to add last_post_idx index', __FILE__, __LINE__, $db->error());

		// Add an index to username on the bans table
		if ($mysql)
			$db->add_index('bans', 'username_idx', array('username(25)')) or error('Unable to add username_idx index', __FILE__, __LINE__, $db->error());
		else
			$db->add_index('bans', 'username_idx', array('username')) or error('Unable to add username_idx index', __FILE__, __LINE__, $db->error());

		// Change the username_idx on users to a unique index of max size 25
		$db->drop_index('users', 'username_idx') or error('Unable to drop old username_idx index', __FILE__, __LINE__, $db->error());
		$field = $mysql ? 'username(25)' : 'username';

		// Attempt to add a unique index. If the user doesn't use a transactional database this can fail due to multiple matching usernames in the
		// users table. This is bad, but just giving up if it happens is even worse! If it fails just add a regular non-unique index.
		if (!$db->add_index('users', 'username_idx', array($field), true))
			$db->add_index('users', 'username_idx', array($field)) or error('Unable to add username_idx field', __FILE__, __LINE__, $db->error());

		// Add g_view_users field to groups table
		$db->add_field('groups', 'g_view_users', 'TINYINT(1)', false, 1, 'g_read_board') or error('Unable to add g_view_users field', __FILE__, __LINE__, $db->error());

		// Add the last_email_sent column to the users table and the g_send_email and
		// g_email_flood columns to the groups table
		$db->add_field('users', 'last_email_sent', 'INT(10) UNSIGNED', true, null, 'last_search') or error('Unable to add last_email_sent field', __FILE__, __LINE__, $db->error());
		$db->add_field('groups', 'g_send_email', 'TINYINT(1)', false, 1, 'g_search_users') or error('Unable to add g_send_email field', __FILE__, __LINE__, $db->error());
		$db->add_field('groups', 'g_email_flood', 'SMALLINT(6)', false, 60, 'g_search_flood') or error('Unable to add g_email_flood field', __FILE__, __LINE__, $db->error());

		// Set non-default g_send_email and g_flood_email values properly
		$db->query('UPDATE '.$db->prefix.'groups SET g_send_email = 0 WHERE g_id = 3') or error('Unable to update group email permissions', __FILE__, __LINE__, $db->error());
		$db->query('UPDATE '.$db->prefix.'groups SET g_email_flood = 0 WHERE g_id IN (1,2,3)') or error('Unable to update group email permissions', __FILE__, __LINE__, $db->error());

		// Add the auto notify/subscription option to the users table
		$db->add_field('users', 'auto_notify', 'TINYINT(1)', false, 0, 'notify_with_post') or error('Unable to add auto_notify field', __FILE__, __LINE__, $db->error());

		// Add the first_post_id column to the topics table
		if (!$db->field_exists('topics', 'first_post_id'))
		{
			$db->add_field('topics', 'first_post_id', 'INT(10) UNSIGNED', false, 0, 'posted') or error('Unable to add first_post_id field', __FILE__, __LINE__, $db->error());
			$db->add_index('topics', 'first_post_id_idx', array('first_post_id')) or error('Unable to add first_post_id_idx index', __FILE__, __LINE__, $db->error());

			// Now that we've added the column and indexed it, we need to give it correct data
			$result = $db->query('SELECT MIN(id) AS first_post, topic_id FROM '.$db->prefix.'posts GROUP BY topic_id') or error('Unable to fetch first_post_id', __FILE__, __LINE__, $db->error());

			while ($cur_post = $db->fetch_assoc($result))
				$db->query('UPDATE '.$db->prefix.'topics SET first_post_id = '.$cur_post['first_post'].' WHERE id = '.$cur_post['topic_id']) or error('Unable to update first_post_id', __FILE__, __LINE__, $db->error());
		}

		// Move any users with the old unverified status to their new group
		$db->query('UPDATE '.$db->prefix.'users SET group_id=0 WHERE group_id=32000') or error('Unable to move unverified users', __FILE__, __LINE__, $db->error());

		// Add the ban_creator column to the bans table
		$db->add_field('bans', 'ban_creator', 'INT(10) UNSIGNED', false, 0) or error('Unable to add ban_creator field', __FILE__, __LINE__, $db->error());

		// Add the time/date format settings to the user table
		$db->add_field('users', 'time_format', 'TINYINT(1)', false, 0, 'dst') or error('Unable to add time_format field', __FILE__, __LINE__, $db->error());
		$db->add_field('users', 'date_format', 'TINYINT(1)', false, 0, 'dst') or error('Unable to add date_format field', __FILE__, __LINE__, $db->error());

		// Change the search_data field to mediumtext
		$db->alter_field('search_cache', 'search_data', 'MEDIUMTEXT', true) or error('Unable to alter search_data field', __FILE__, __LINE__, $db->error());

		// Incase we had the fulltext search extension installed (1.3-legacy), remove it
		$db->drop_index('topics', 'subject_idx') or error('Unable to drop subject_idx index', __FILE__, __LINE__, $db->error());
		$db->drop_index('posts', 'message_idx') or error('Unable to drop message_idx index', __FILE__, __LINE__, $db->error());
		// Incase we had the fulltext search mod installed (1.2), remove it
		$db->drop_index('topics', 'subject_fulltext_search') or error('Unable to drop subject_fulltext_search index', __FILE__, __LINE__, $db->error());
		$db->drop_index('posts', 'message_fulltext_search') or error('Unable to drop message_fulltext_search index', __FILE__, __LINE__, $db->error());

		// If the search_cache table has been dropped by the fulltext search extension, recreate it
		if (!$db->table_exists('search_cache'))
		{
			$schema = array(
				'FIELDS'		=> array(
					'id'			=> array(
						'datatype'		=> 'INT(10) UNSIGNED',
						'allow_null'	=> false,
						'default'		=> '0'
					),
					'ident'			=> array(
						'datatype'		=> 'VARCHAR(200)',
						'allow_null'	=> false,
						'default'		=> '\'\''
					),
					'search_data'	=> array(
						'datatype'		=> 'MEDIUMTEXT',
						'allow_null'	=> true
					)
				),
				'PRIMARY KEY'	=> array('id'),
				'INDEXES'		=> array(
					'ident_idx'	=> array('ident')
				)
			);

			if ($mysql)
				$schema['INDEXES']['ident_idx'] = array('ident(8)');

			$db->create_table('search_cache', $schema);
		}

		// If the search_matches table has been dropped by the fulltext search extension, recreate it
		if (!$db->table_exists('search_matches'))
		{
			$schema = array(
				'FIELDS'		=> array(
					'post_id'		=> array(
						'datatype'		=> 'INT(10) UNSIGNED',
						'allow_null'	=> false,
						'default'		=> '0'
					),
					'word_id'		=> array(
						'datatype'		=> 'INT(10) UNSIGNED',
						'allow_null'	=> false,
						'default'		=> '0'
					),
					'subject_match'	=> array(
						'datatype'		=> 'TINYINT(1)',
						'allow_null'	=> false,
						'default'		=> '0'
					)
				),
				'INDEXES'		=> array(
					'word_id_idx'	=> array('word_id'),
					'post_id_idx'	=> array('post_id')
				)
			);

			$db->create_table('search_matches', $schema);
		}

		// If the search_words table has been dropped by the fulltext search extension, recreate it
		if (!$db->table_exists('search_words'))
		{
			$schema = array(
				'FIELDS'		=> array(
					'id'			=> array(
						'datatype'		=> 'SERIAL',
						'allow_null'	=> false
					),
					'word'			=> array(
						'datatype'		=> 'VARCHAR(20)',
						'allow_null'	=> false,
						'default'		=> '\'\'',
						'collation'		=> 'bin'
					)
				),
				'PRIMARY KEY'	=> array('word'),
				'INDEXES'		=> array(
					'id_idx'	=> array('id')
				)
			);

			if ($db_type == 'sqlite')
			{
				$schema['PRIMARY KEY'] = array('id');
				$schema['UNIQUE KEYS'] = array('word_idx'	=> array('word'));
			}

			$db->create_table('search_words', $schema);
		}

		// Change the default style if the old doesn't exist anymore
		if ($pun_config['o_default_style'] != $default_style)
			$db->query('UPDATE '.$db->prefix.'config SET conf_value = \''.$db->escape($default_style).'\' WHERE conf_name = \'o_default_style\'') or error('Unable to update default style config', __FILE__, __LINE__, $db->error());

		// Should we do charset conversion or not?
		if (strpos($cur_version, '1.2') === 0 && isset($_GET['convert_charset']))
			$query_str = '?stage=conv_bans&req_old_charset='.$old_charset;

		break;


	// Convert bans
	case 'conv_bans':
		$query_str = '?stage=conv_categories&req_old_charset='.$old_charset;

		function _conv_bans($cur_item, $old_charset)
		{
			echo 'Converting ban '.$cur_item['id'].' …<br />'."\n";

			convert_to_utf8($cur_item['username'], $old_charset);
			convert_to_utf8($cur_item['message'], $old_charset);

			return $cur_item;
		}

		$end_at = convert_table_utf8($db->prefix.'bans', '_conv_bans', $old_charset, 'id', $start_at);

		if ($end_at !== true)
			$query_str = '?stage=conv_bans&req_old_charset='.$old_charset.'&start_at='.$end_at;

		break;


	// Convert categories
	case 'conv_categories':
		$query_str = '?stage=conv_censors&req_old_charset='.$old_charset;

		echo 'Converting categories …'."<br />\n";

		function _conv_categories($cur_item, $old_charset)
		{
			convert_to_utf8($cur_item['cat_name'], $old_charset);

			return $cur_item;
		}

		convert_table_utf8($db->prefix.'categories', '_conv_categories', $old_charset, 'id');

		break;


	// Convert censor words
	case 'conv_censors':
		$query_str = '?stage=conv_config&req_old_charset='.$old_charset;

		echo 'Converting censor words …'."<br />\n";

		function _conv_censoring($cur_item, $old_charset)
		{
			convert_to_utf8($cur_item['search_for'], $old_charset);
			convert_to_utf8($cur_item['replace_with'], $old_charset);

			return $cur_item;
		}

		convert_table_utf8($db->prefix.'censoring', '_conv_censoring', $old_charset, 'id');

		break;


	// Convert config
	case 'conv_config':
		$query_str = '?stage=conv_forums&req_old_charset='.$old_charset;

		echo 'Converting configuration …'."<br />\n";

		function _conv_config($cur_item, $old_charset)
		{
			convert_to_utf8($cur_item['conf_value'], $old_charset);

			return $cur_item;
		}

		convert_table_utf8($db->prefix.'config', '_conv_config', $old_charset, 'conf_name');

		break;


	// Convert forums
	case 'conv_forums':
		$query_str = '?stage=conv_perms&req_old_charset='.$old_charset;

		echo 'Converting forums …'."<br />\n";

		function _conv_forums($cur_item, $old_charset)
		{
			$moderators = ($cur_item['moderators'] != '') ? unserialize($cur_item['moderators']) : array();
			$moderators_utf8 = array();
			foreach ($moderators as $mod_username => $mod_user_id)
			{
				convert_to_utf8($mod_username, $old_charset);
				$moderators_utf8[$mod_username] = $mod_user_id;
			}

			convert_to_utf8($cur_item['forum_name'], $old_charset);
			convert_to_utf8($cur_item['forum_desc'], $old_charset);

			if (!empty($moderators_utf8))
				$cur_item['moderators'] = serialize($moderators_utf8);

			return $cur_item;
		}

		convert_table_utf8($db->prefix.'forums', '_conv_forums', $old_charset, 'id');

		break;


	// Convert forum permissions
	case 'conv_perms':
		$query_str = '?stage=conv_groups&req_old_charset='.$old_charset;

		alter_table_utf8($db->prefix.'forum_perms');

		break;


	// Convert groups
	case 'conv_groups':
		$query_str = '?stage=conv_online&req_old_charset='.$old_charset;

		echo 'Converting groups …'."<br />\n";

		function _conv_groups($cur_item, $old_charset)
		{
			convert_to_utf8($cur_item['g_title'], $old_charset);
			convert_to_utf8($cur_item['g_user_title'], $old_charset);

			return $cur_item;
		}

		convert_table_utf8($db->prefix.'groups', '_conv_groups', $old_charset, 'g_id');

		break;


	// Convert online
	case 'conv_online':
		$query_str = '?stage=conv_posts&req_old_charset='.$old_charset;

		// Truncate the table
		$db->truncate_table('online') or error('Unable to empty online table', __FILE__, __LINE__, $db->error());

		alter_table_utf8($db->prefix.'online');

		break;


	// Convert posts
	case 'conv_posts':
		$query_str = '?stage=conv_ranks&req_old_charset='.$old_charset;

		function _conv_posts($cur_item, $old_charset)
		{
			echo 'Converting post '.$cur_item['id'].' …<br />'."\n";

			convert_to_utf8($cur_item['poster'], $old_charset);
			convert_to_utf8($cur_item['message'], $old_charset);
			convert_to_utf8($cur_item['edited_by'], $old_charset);

			return $cur_item;
		}

		$end_at = convert_table_utf8($db->prefix.'posts', '_conv_posts', $old_charset, 'id', $start_at);

		if ($end_at !== true)
			$query_str = '?stage=conv_posts&req_old_charset='.$old_charset.'&start_at='.$end_at;

		break;


	// Convert ranks
	case 'conv_ranks':
		$query_str = '?stage=conv_reports&req_old_charset='.$old_charset;

		echo 'Converting ranks …'."<br />\n";

		function _conv_ranks($cur_item, $old_charset)
		{
			convert_to_utf8($cur_item['rank'], $old_charset);

			return $cur_item;
		}

		convert_table_utf8($db->prefix.'ranks', '_conv_ranks', $old_charset, 'id');

		break;


	// Convert reports
	case 'conv_reports':
		$query_str = '?stage=conv_search_cache&req_old_charset='.$old_charset;

		function _conv_reports($cur_item, $old_charset)
		{
			echo 'Converting report '.$cur_item['id'].' …<br />'."\n";

			convert_to_utf8($cur_item['message'], $old_charset);

			return $cur_item;
		}

		$end_at = convert_table_utf8($db->prefix.'reports', '_conv_reports', $old_charset, 'id', $start_at);

		if ($end_at !== true)
			$query_str = '?stage=conv_reports&req_old_charset='.$old_charset.'&start_at='.$end_at;

		break;


	// Convert search cache
	case 'conv_search_cache':
		$query_str = '?stage=conv_search_matches&req_old_charset='.$old_charset;

		// Truncate the table
		$db->truncate_table('search_cache') or error('Unable to empty search cache table', __FILE__, __LINE__, $db->error());

		alter_table_utf8($db->prefix.'search_cache');

		break;


	// Convert search matches
	case 'conv_search_matches':
		$query_str = '?stage=conv_search_words&req_old_charset='.$old_charset;

		// Truncate the table
		$db->truncate_table('search_matches') or error('Unable to empty search index match table', __FILE__, __LINE__, $db->error());

		alter_table_utf8($db->prefix.'search_matches');

		break;


	// Convert search words
	case 'conv_search_words':
		$query_str = '?stage=conv_subscriptions&req_old_charset='.$old_charset;

		// Truncate the table
		$db->truncate_table('search_words') or error('Unable to empty search index words table', __FILE__, __LINE__, $db->error());

		// Reset the sequence for the search words (not needed for SQLite)
		switch ($db_type)
		{
			case 'mysql':
			case 'mysqli':
			case 'mysql_innodb':
			case 'mysqli_innodb':
				$db->query('ALTER TABLE '.$db->prefix.'search_words auto_increment=1') or error('Unable to update table auto_increment', __FILE__, __LINE__, $db->error());
				break;

			case 'pgsql';
				$db->query('SELECT setval(\''.$db->prefix.'search_words_id_seq\', 1, false)') or error('Unable to update sequence', __FILE__, __LINE__, $db->error());
				break;
		}

		alter_table_utf8($db->prefix.'search_words');

		break;


	// Convert subscriptions
	case 'conv_subscriptions':
		$query_str = '?stage=conv_topics&req_old_charset='.$old_charset;

		alter_table_utf8($db->prefix.'subscriptions');

		break;


	// Convert topics
	case 'conv_topics':
		$query_str = '?stage=conv_users&req_old_charset='.$old_charset;

		function _conv_topics($cur_item, $old_charset)
		{
			echo 'Converting topic '.$cur_item['id'].' …<br />'."\n";

			convert_to_utf8($cur_item['poster'], $old_charset);
			convert_to_utf8($cur_item['subject'], $old_charset);
			convert_to_utf8($cur_item['last_poster'], $old_charset);

			return $cur_item;
		}

		$end_at = convert_table_utf8($db->prefix.'topics', '_conv_topics', $old_charset, 'id', $start_at);

		if ($end_at !== true)
			$query_str = '?stage=conv_topics&req_old_charset='.$old_charset.'&start_at='.$end_at;

		break;


	// Convert users
	case 'conv_users':
		$query_str = '?stage=preparse_posts';

		function _conv_users($cur_item, $old_charset)
		{
			echo 'Converting user '.$cur_item['id'].' …<br />'."\n";

			convert_to_utf8($cur_item['username'], $old_charset);
			convert_to_utf8($cur_item['title'], $old_charset);
			convert_to_utf8($cur_item['realname'], $old_charset);
			convert_to_utf8($cur_item['location'], $old_charset);
			convert_to_utf8($cur_item['signature'], $old_charset);
			convert_to_utf8($cur_item['admin_note'], $old_charset);

			return $cur_item;
		}

		$end_at = convert_table_utf8($db->prefix.'users', '_conv_users', $old_charset, 'id', $start_at);

		if ($end_at !== true)
			$query_str = '?stage=conv_users&req_old_charset='.$old_charset.'&start_at='.$end_at;

		break;


	// Preparse posts
	case 'preparse_posts':
		$query_str = '?stage=preparse_sigs';

		// If we don't need to parse the posts, skip this stage
		if (isset($pun_config['o_parser_revision']) && $pun_config['o_parser_revision'] >= UPDATE_TO_PARSER_REVISION)
			break;

		require PUN_ROOT.'include/parser.php';

		// Fetch posts to process this cycle
		$result = $db->query('SELECT id, message FROM '.$db->prefix.'posts WHERE id > '.$start_at.' ORDER BY id ASC LIMIT '.PER_PAGE) or error('Unable to fetch posts', __FILE__, __LINE__, $db->error());

		$temp = array();
		$end_at = 0;
		while ($cur_item = $db->fetch_assoc($result))
		{
			echo 'Preparsing post '.$cur_item['id'].' …<br />'."\n";
			$db->query('UPDATE '.$db->prefix.'posts SET message = \''.$db->escape(preparse_bbcode($cur_item['message'], $temp)).'\' WHERE id = '.$cur_item['id']) or error('Unable to update post', __FILE__, __LINE__, $db->error());

			$end_at = $cur_item['id'];
		}

		// Check if there is more work to do
		if ($end_at > 0)
		{
			$result = $db->query('SELECT 1 FROM '.$db->prefix.'posts WHERE id > '.$end_at.' ORDER BY id ASC LIMIT 1') or error('Unable to fetch next ID', __FILE__, __LINE__, $db->error());

			if ($db->num_rows($result) > 0)
				$query_str = '?stage=preparse_posts&start_at='.$end_at;
		}

		break;


	// Preparse signatures
	case 'preparse_sigs':
		$query_str = '?stage=rebuild_idx';

		// If we don't need to parse the sigs, skip this stage
		if (isset($pun_config['o_parser_revision']) && $pun_config['o_parser_revision'] >= UPDATE_TO_PARSER_REVISION)
			break;

		require PUN_ROOT.'include/parser.php';

		// Fetch users to process this cycle
		$result = $db->query('SELECT id, signature FROM '.$db->prefix.'users WHERE id > '.$start_at.' ORDER BY id ASC LIMIT '.PER_PAGE) or error('Unable to fetch users', __FILE__, __LINE__, $db->error());

		$temp = array();
		$end_at = 0;
		while ($cur_item = $db->fetch_assoc($result))
		{
			echo 'Preparsing signature '.$cur_item['id'].' …<br />'."\n";
			$db->query('UPDATE '.$db->prefix.'users SET signature = \''.$db->escape(preparse_bbcode($cur_item['signature'], $temp, true)).'\' WHERE id = '.$cur_item['id']) or error('Unable to update user', __FILE__, __LINE__, $db->error());

			$end_at = $cur_item['id'];
		}

		// Check if there is more work to do
		if ($end_at > 0)
		{
			$result = $db->query('SELECT 1 FROM '.$db->prefix.'users WHERE id > '.$end_at.' ORDER BY id ASC LIMIT 1') or error('Unable to fetch next ID', __FILE__, __LINE__, $db->error());
			if ($db->num_rows($result) > 0)
				$query_str = '?stage=preparse_sigs&start_at='.$end_at;
		}

		break;


	// Rebuild the search index
	case 'rebuild_idx':
		$query_str = '?stage=finish';

		// If we don't need to update the search index, skip this stage
		if (isset($pun_config['o_searchindex_revision']) && $pun_config['o_searchindex_revision'] >= UPDATE_TO_SI_REVISION)
			break;

		if ($start_at == 0)
		{
			// Truncate the tables just in-case we didn't already (if we are coming directly here without converting the tables)
			$db->truncate_table('search_cache') or error('Unable to empty search cache table', __FILE__, __LINE__, $db->error());
			$db->truncate_table('search_matches') or error('Unable to empty search index match table', __FILE__, __LINE__, $db->error());
			$db->truncate_table('search_words') or error('Unable to empty search index words table', __FILE__, __LINE__, $db->error());

			// Reset the sequence for the search words (not needed for SQLite)
			switch ($db_type)
			{
				case 'mysql':
				case 'mysqli':
				case 'mysql_innodb':
				case 'mysqli_innodb':
					$db->query('ALTER TABLE '.$db->prefix.'search_words auto_increment=1') or error('Unable to update table auto_increment', __FILE__, __LINE__, $db->error());
					break;

				case 'pgsql';
					$db->query('SELECT setval(\''.$db->prefix.'search_words_id_seq\', 1, false)') or error('Unable to update sequence', __FILE__, __LINE__, $db->error());
					break;
			}
		}

		require PUN_ROOT.'include/search_idx.php';

		// Fetch posts to process this cycle
		$result = $db->query('SELECT p.id, p.message, t.subject, t.first_post_id FROM '.$db->prefix.'posts AS p INNER JOIN '.$db->prefix.'topics AS t ON t.id=p.topic_id WHERE p.id > '.$start_at.' ORDER BY p.id ASC LIMIT '.PER_PAGE) or error('Unable to fetch posts', __FILE__, __LINE__, $db->error());

		$end_at = 0;
		while ($cur_item = $db->fetch_assoc($result))
		{
			echo 'Rebuilding index for post '.$cur_item['id'].' …<br />'."\n";

			if ($cur_item['id'] == $cur_item['first_post_id'])
				update_search_index('post', $cur_item['id'], $cur_item['message'], $cur_item['subject']);
			else
				update_search_index('post', $cur_item['id'], $cur_item['message']);

			$end_at = $cur_item['id'];
		}

		// Check if there is more work to do
		if ($end_at > 0)
		{
			$result = $db->query('SELECT 1 FROM '.$db->prefix.'posts WHERE id > '.$end_at.' ORDER BY id ASC LIMIT 1') or error('Unable to fetch next ID', __FILE__, __LINE__, $db->error());

			if ($db->num_rows($result) > 0)
				$query_str = '?stage=rebuild_idx&start_at='.$end_at;
		}

		break;


	// Show results page
	case 'finish':
		// We update the version number
		$db->query('UPDATE '.$db->prefix.'config SET conf_value = \''.UPDATE_TO.'\' WHERE conf_name = \'o_cur_version\'') or error('Unable to update version', __FILE__, __LINE__, $db->error());

		// And the database revision number
		$db->query('UPDATE '.$db->prefix.'config SET conf_value = \''.UPDATE_TO_DB_REVISION.'\' WHERE conf_name = \'o_database_revision\'') or error('Unable to update database revision number', __FILE__, __LINE__, $db->error());

		// And the search index revision number
		$db->query('UPDATE '.$db->prefix.'config SET conf_value = \''.UPDATE_TO_SI_REVISION.'\' WHERE conf_name = \'o_searchindex_revision\'') or error('Unable to update search index revision number', __FILE__, __LINE__, $db->error());

		// And the parser revision number
		$db->query('UPDATE '.$db->prefix.'config SET conf_value = \''.UPDATE_TO_PARSER_REVISION.'\' WHERE conf_name = \'o_parser_revision\'') or error('Unable to update parser revision number', __FILE__, __LINE__, $db->error());

		// Check the default language still exists!
		if (!file_exists(PUN_ROOT.'lang/'.$pun_config['o_default_lang'].'/common.php'))
			$db->query('UPDATE '.$db->prefix.'config SET conf_value = \'English\' WHERE conf_name = \'o_default_lang\'') or error('Unable to update default language', __FILE__, __LINE__, $db->error());

		// Check the default style still exists!
		if (!file_exists(PUN_ROOT.'style/'.$pun_config['o_default_style'].'.css'))
			$db->query('UPDATE '.$db->prefix.'config SET conf_value = \'Air\' WHERE conf_name = \'o_default_style\'') or error('Unable to update default style', __FILE__, __LINE__, $db->error());

		// This feels like a good time to synchronize the forums
		$result = $db->query('SELECT id FROM '.$db->prefix.'forums') or error('Unable to fetch forum IDs', __FILE__, __LINE__, $db->error());

		while ($row = $db->fetch_row($result))
			update_forum($row[0]);

		// Empty the PHP cache
		forum_clear_cache();

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">

<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en" dir="ltr">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>FluxBB Database Update</title>
<link rel="stylesheet" type="text/css" href="style/<?php echo $default_style ?>.css" />
</head>
<body>

<div id="pundb_update" class="pun">
<div class="top-box"><div><!-- Top Corners --></div></div>
<div class="punwrap">

<div class="blockform">
	<h2><span>FluxBB Update</span></h2>
	<div class="box">
		<div class="fakeform">
			<div class="inform">
				<div class="forminfo">
					<p style="font-size: 1.1em">Your forum database was successfully updated. You may now <a href="<?php echo PUN_ROOT ?>index.php">go to the forum index</a>.</p>
				</div>
			</div>
		</div>
	</div>
</div>

</div>
<div class="end-box"><div><!-- Bottom Corners --></div></div>
</div>

</body>
</html>
<?php

		break;
}

$db->end_transaction();
$db->close();

if ($query_str != '')
	exit('<script type="text/javascript">window.location="db_update.php'.$query_str.'"</script><noscript>JavaScript seems to be disabled. <a href="db_update.php'.$query_str.'">Click here to continue</a>.</noscript>');