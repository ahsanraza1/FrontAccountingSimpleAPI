<?php
/**********************************************************************
	Copyright (C) FrontAccounting, LLC.
	Released under the terms of the GNU General Public License, GPL,
	as published by the Free Software Foundation, either version 3
	of the License, or (at your option) any later version.
	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
	See the License here <http://www.gnu.org/licenses/gpl-3.0.html>.
***********************************************************************/
define('VARLIB_PATH', $path_to_root.'/tmp');
define('VARLOG_PATH', $path_to_root.'/tmp');

class SessionManager
{
   	function sessionStart($name, $limit = 0, $path = '/', $domain = null, $secure = null)
	{
		// Set the cookie name
		session_name($name);

		// Set SSL level
		$https = isset($secure) ? $secure : (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

		// Set session cookie options
		if (version_compare(PHP_VERSION, '5.2', '<')) // avoid failure on older php versions
			session_set_cookie_params($limit, $path, $domain, $https);
		else
			session_set_cookie_params($limit, $path, $domain, $https, true);

		session_start();

		// Make sure the session hasn't expired, and destroy it if it has
		if ($this->validateSession())
		{
			// Check to see if the session is new or a hijacking attempt
			if(!$this->preventHijacking())
			{
				// Reset session data and regenerate id
				$_SESSION = array();
				$_SESSION['IPaddress'] = $_SERVER['REMOTE_ADDR'];
				$_SESSION['userAgent'] = @$_SERVER['HTTP_USER_AGENT'];
				$this->regenerateSession();

			// Give a 5% chance of the session id changing on any request
			}
			elseif (rand(1, 100) <= 5)
			{
				$this->regenerateSession();
			}
		}
		else
		{
			$_SESSION = array();
			session_destroy();
			session_start();
		}
	}

	function preventHijacking()
	{
		if (!isset($_SESSION['IPaddress']) || !isset($_SESSION['userAgent']))
			return false;

		if ($_SESSION['IPaddress'] != $_SERVER['REMOTE_ADDR'])
			return false;

		if ( $_SESSION['userAgent'] != @$_SERVER['HTTP_USER_AGENT'])
			return false;

		return true;
	}

	function regenerateSession()
	{
		// If this session is obsolete it means there already is a new id
		if (isset($_SESSION['OBSOLETE']) && ($_SESSION['OBSOLETE'] == true))
			return;

		// Set current session to expire in 10 seconds
		$_SESSION['OBSOLETE'] = true;
		$_SESSION['EXPIRES'] = time() + 10;

		// Create new session without destroying the old one
		session_regenerate_id();
		// Grab current session ID and close both sessions to allow other scripts to use them
		$newSession = session_id();
		session_write_close();
		// Set session ID to the new one, and start it back up again

		session_id($newSession);
		session_start();
		
		// Now we unset the obsolete and expiration values for the session we want to keep
		unset($_SESSION['OBSOLETE']);
		unset($_SESSION['EXPIRES']);
	}

	function validateSession()
	{
		if (isset($_SESSION['OBSOLETE']) && !isset($_SESSION['EXPIRES']) )
			return false;

		if (isset($_SESSION['EXPIRES']) && $_SESSION['EXPIRES'] < time())
			return false;

		return true;
	}
}

function output_html($text)
{
	global $before_box, $Ajax, $messages;
	// Fatal errors are not send to error_handler,
	// so we must check the output
	if ($text && preg_match('/\bFatal error(<.*?>)?:(.*)/i', $text, $m)) {
		$Ajax->aCommands = array();  // Don't update page via ajax on errors
		$text = preg_replace('/\bFatal error(<.*?>)?:(.*)/i','', $text);
		$messages[] = array(E_ERROR, $m[2], null, null);
	}
	$Ajax->run();
	return  in_ajax() ? fmt_errors() : ($before_box.fmt_errors().$text);
}
//----------------------------------------------------------------------------------------

function kill_login()
{
	session_unset();
	session_destroy();
}
//----------------------------------------------------------------------------------------

function login_fail()
{
	global $path_to_root;
	
	header("HTTP/1.1 401 Authorization Required");
	echo "<center><br><br><font size='5' color='red'><b>" . _("Incorrect Password") . "<b></font><br><br>";
	echo "<b>" . _("The user and password combination is not valid for the system.") . "<b><br><br>";

	echo _("If you are not an authorized user, please contact your system administrator to obtain an account to enable you to use the system.");
	echo "<br><a href='$path_to_root/index.php'>" . _("Try again") . "</a>";
	echo "</center>";

	kill_login();
	die();
}

function password_reset_fail()
{
	global $path_to_root;
	
  	echo "<center><br><br><font size='5' color='red'><b>" . _("Incorrect Email") . "<b></font><br><br>";
  	echo "<b>" . _("The email address does not exist in the system, or is used by more than one user.") . "<b><br><br>";

  	echo _("Plase try again or contact your system administrator to obtain new password.");
  	echo "<br><a href='$path_to_root/index.php?reset=1'>" . _("Try again") . "</a>";
  	echo "</center>";

	kill_login();
	die();
}

function password_reset_success()
{
	global $path_to_root;

  	echo "<center><br><br><font size='5' color='green'><b>" . _("New password sent") . "<b></font><br><br>";
  	echo "<b>" . _("A new password has been sent to your mailbox.") . "<b><br><br>";

  	echo "<br><a href='$path_to_root/index.php'>" . _("Login here") . "</a>";
  	echo "</center>";
	
	kill_login();
	die();
}

function check_faillog()
{
	global $SysPrefs, $login_faillog;

	$user = $_SESSION["wa_current_user"]->user;

	if (@$SysPrefs->login_delay && (@$login_faillog[$user][$_SERVER['REMOTE_ADDR']] >= @$SysPrefs->login_max_attempts) && (time() < $login_faillog[$user]['last'] + $SysPrefs->login_delay))
		return true;

	return false;
}

/*
	Ensure file is re-read on next request if php caching is active
*/
function cache_invalidate($filename)
{
	if (function_exists('opcache_invalidate'))	// OpCode extension
		opcache_invalidate($filename);
}

/*
	Simple brute force attack detection is performed before connection to company database is open. Therefore access counters have to be stored in file.
	Login attempts counter is created for every new user IP, which partialy prevent DOS attacks.
*/
function write_login_filelog($login, $result)
{
	global $login_faillog, $SysPrefs, $path_to_root;

	$user = $_SESSION["wa_current_user"]->user;

	$ip = $_SERVER['REMOTE_ADDR'];

	if (!isset($login_faillog[$user][$ip]) || $result) // init or reset on successfull login
		$login_faillog[$user] = array($ip => 0, 'last' => '');

 	if (!$result)
	{
		if ($login_faillog[$user][$ip] < @$SysPrefs->login_max_attempts) {

 			$login_faillog[$user][$ip]++;
 		} else {
 			$login_faillog[$user][$ip] = 0; // comment out to restart counter only after successfull login.
	 		error_log(sprintf(_("Brute force attack on account '%s' detected. Access for non-logged users temporarily blocked."	), $login));
	 	}
 		$login_faillog[$user]['last'] = time();
	}

	$msg = "<?php\n";
	$msg .= "/*\n";
	$msg .= "Login attempts info.\n";
	$msg .= "*/\n";
	$msg .= "\$login_faillog = " .var_export($login_faillog, true). ";\n";

	$filename = VARLIB_PATH."faillog.php";

	if ((!file_exists($filename) && is_writable(VARLIB_PATH)) || is_writable($filename))
	{
		file_put_contents($filename, $msg);
		cache_invalidate($filename);
	}
}

//----------------------------------------------------------------------------------------

function check_page_security($page_security)
{
	global $SysPrefs;
	
	$msg = '';
	
	if (!$_SESSION["wa_current_user"]->check_user_access())
	{
		// notification after upgrade from pre-2.2 version
		$msg = $_SESSION["wa_current_user"]->old_db ?
			 _("Security settings have not been defined for your user account.")
				. "<br>" . _("Please contact your system administrator.")	
			: _("Please remove \$security_groups and \$security_headings arrays from config.php file!");
	} elseif (!$SysPrefs->db_ok && !$_SESSION["wa_current_user"]->can_access('SA_SOFTWAREUPGRADE')) 
	{
		$msg = _('Access to application has been blocked until database upgrade is completed by system administrator.');
	}
	
	if ($msg){
		display_error($msg);
		end_page(@$_REQUEST['popup']);
		kill_login();
		exit;
	}

	if (!$_SESSION["wa_current_user"]->can_access_page($page_security))
	{

		echo "<center><br><br><br><b>";
		echo _("The security settings on your account do not permit you to access this function");
		echo "</b>";
		echo "<br><br><br><br></center>";
		end_page(@$_REQUEST['popup']);
		exit;
	}
	if (!$SysPrefs->db_ok 
		&& !in_array($page_security, array('SA_SOFTWAREUPGRADE', 'SA_OPEN', 'SA_BACKUP')))
	{
		display_error(_('System is blocked after source upgrade until database is updated on System/Software Upgrade page'));
		end_page();
		exit;
	}

}
/*
	Helper function for setting page security level depeding on 
	GET start variable and/or some value stored in session variable.
	Before the call $page_security should be set to default page_security value.
*/
function set_page_security($value=null, $trans = array(), $gtrans = array())
{
	global $page_security;

	// first check is this is not start page call
	foreach($gtrans as $key => $area)
		if (isset($_GET[$key])) {
			$page_security = $area;
			return;
		}

	// then check session value
	if (isset($trans[$value])) {
		$page_security = $trans[$value];
		return;
	}
}

//-----------------------------------------------------------------------------
//	Removing magic quotes from nested arrays/variables
//
function strip_quotes($data)
{
	//if(get_magic_quotes_gpc()) {
		if(is_array($data)) {
			foreach($data as $k => $v) {
				$data[$k] = strip_quotes($data[$k]);
			}
		} else{
			return stripslashes($data);
		}
	//}
	return $data;
}

/*
	htmlspecialchars does not support certain encodings.
	ISO-8859-2 fortunately has the same special characters positions as 
	ISO-8859-1, so fix is easy. If any other unsupported encoding is used,
	add workaround here.
*/
function html_specials_encode($str)
{
	return htmlspecialchars($str, ENT_QUOTES, $_SESSION['language']->encoding=='iso-8859-2' ?
		 'ISO-8859-1' : $_SESSION['language']->encoding);
}

function html_cleanup(&$parms)
{
	foreach($parms as $name => $value) {
		if (is_array($value))
			html_cleanup($parms[$name]);
		else
			$parms[$name] = html_specials_encode($value);
	}
	reset($parms); // needed for direct key() usage later throughout the sources
}

//============================================================================
//
//
function login_timeout()
{
	// skip timeout on logout page
	if ($_SESSION["wa_current_user"]->logged) {
		$tout = $_SESSION["wa_current_user"]->timeout;
		if ($tout && (time() > $_SESSION["wa_current_user"]->last_act + $tout))
		{
			$_SESSION["wa_current_user"]->logged = false;
		}
		$_SESSION["wa_current_user"]->last_act = time();
	}
}
