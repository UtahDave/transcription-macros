<?php  if (!defined('BASEPATH')) exit('No direct script access allowed');
/**
 * Code Igniter
 *
 * An open source application development framework for PHP 4.3.2 or newer
 *
 * @package		CodeIgniter
 * @author		Rick Ellis
 * @copyright	Copyright (c) 2006, pMachine, Inc.
 * @license		http://www.codeignitor.com/user_guide/license.html 
 * @link		http://www.codeigniter.com
 * @since		Version 1.0
 * @filesource
 */
 
// ------------------------------------------------------------------------

/**
 * System Front Controller
 *
 * Loads the base classes and executes the request. 
 * 
 * @package		CodeIgniter
 * @subpackage	codeigniter
 * @category	Front-controller
 * @author		Rick Ellis
 * @link		http://www.codeigniter.com/user_guide/
 */
 
define('APPVER', '1.3.3');

if ( ! defined('E_STRICT')) 
	define('E_STRICT', 2048);

/*
 * ------------------------------------------------------
 *  Start the timer... tick tock tick tock...
 * ------------------------------------------------------
 */
require(BASEPATH.'libraries/Benchmark'.EXT);	
$BM = new CI_Benchmark('code_igniter_start');

/*
 * ------------------------------------------------------
 *  Define a custom error handler so we can log errors
 * ------------------------------------------------------
 */
set_error_handler('_exception_handler');
set_magic_quotes_runtime(0); // Kill magic quotes

/*
 * ------------------------------------------------------
 *  Load the main config file
 * ------------------------------------------------------
 */
require(APPPATH.'config/config'.EXT);

if ( ! isset($config) OR ! is_array($config))
{
	show_error('Your config file does not appear to be formatted correctly.');
}

/*
 * ------------------------------------------------------
 *  Load and instantiate the base classes
 * ------------------------------------------------------
 */
require(BASEPATH.'libraries/Config'.EXT);	
require(BASEPATH.'libraries/Router'.EXT);	
require(BASEPATH.'libraries/Output'.EXT);

$CFG = new CI_Config($config);
$RTR = new CI_Router($CFG);
$OUT = new CI_Output();

/*
 * ------------------------------------------------------
 *	Is there a valid cache file?  If so, we're done...
 * ------------------------------------------------------
 */
if ($OUT->_display_cache($CFG, $RTR) == TRUE)
{
	exit;
}

/*
 * ------------------------------------------------------
 *  Does the requested controller exist?
 * ------------------------------------------------------
 */
if ( ! file_exists(APPPATH.'controllers/'.$RTR->fetch_class().EXT))
{
	show_404();
}

/*
 * ------------------------------------------------------
 *  Load and instantiate the remaining base classes
 * ------------------------------------------------------
 */
require(BASEPATH.'libraries/Loader'.EXT);
require(BASEPATH.'libraries/Input'.EXT);	
require(BASEPATH.'libraries/URI'.EXT);
require(BASEPATH.'libraries/Language'.EXT);	

$IN		= new CI_Input($CFG);	
$URI	= new CI_URI();
$LANG	= new CI_Language();

/*
 * ------------------------------------------------------
 *  Load the app controller and local controller
 * ------------------------------------------------------
 * 
 *  Note: Due to the poor object handling in PHP 4 we'll
 *  contditionally load different versions of the base
 *  class.  Retaining PHP 4 compatibility requires a bit of a hack.
 * 
 */
if (floor(phpversion()) < 5)
{
	require(BASEPATH.'codeigniter/Base4'.EXT);
}
else
{
	require(BASEPATH.'codeigniter/Base5'.EXT);
}

require(BASEPATH.'libraries/Controller'.EXT);
require(APPPATH.'controllers/'.$RTR->fetch_class().EXT);

/*
 * ------------------------------------------------------
 *  Security check
 * ------------------------------------------------------
 * 
 *  None of the functions in the app controller or the
 *  loader class can be called via the URI, nor can 
 *  controller functions that begin with an underscore
 */
$class  = $RTR->fetch_class();
$method = $RTR->fetch_method();

if ( ! class_exists($class)
	OR $method == 'controller'
	OR substr($method, 0, 1) == '_' 
	OR in_array($method, get_class_methods('Controller'))
	)
{
	show_404();
}

/*
 * ------------------------------------------------------
 *  Instantiate the controller and call requested method
 * ------------------------------------------------------
 */
$CI  = new $class();

if ($RTR->scaffolding_request === TRUE)
{
	$CI->_ci_scaffolding();
}
else
{
	if ( ! method_exists($CI, $method))
	{
		show_404();
	}
	
	$CI->$method();
}
/*
 * ------------------------------------------------------
 *  Send the final rendered output to the browser
 * ------------------------------------------------------
 */
$OUT->_display();

/*
 * ------------------------------------------------------
 *  Close the DB connection of one exists
 * ------------------------------------------------------
 */
if ($CI->_ci_is_loaded('db'))
{
	$CI->db->close();
}


// END OF SYSTEM EXECUTION ------------------------------

// Below are some globally accessible helper functions


/**
* Error Handler
*
* This function lets us invoke the exception class and
* display errors using the standard error template located 
* in application/errors/errors.php
* This function will send the error page directly to the 
* browser and exit.
*
* @access	public
* @return	void
*/
function show_error($message)
{
	if ( ! class_exists('CI_Exceptions'))
	{
		include_once(BASEPATH.'libraries/Exceptions.php');
	}
	
	$error = new CI_Exceptions();
	echo $error->show_error('An Error Was Encountered', $message);
	exit;
}


/**
* 404 Page Handler
*
* This function is similar to the show_error() function above
* However, instead of the standard error template it displays
* 404 errors.
*
* @access	public
* @return	void
*/
function show_404($page = '')
{
	if ( ! class_exists('CI_Exceptions'))
	{
		include_once(BASEPATH.'libraries/Exceptions.php');
	}
	
	$error = new CI_Exceptions();
	$error->show_404($page);
	exit;
}


/**
* Error Logging Interface 
*
* We use this as a simple mechanism to access the logging
* class and send messages to be logged.
*
* @access	public
* @return	void
*/
function log_message($level = 2, $message, $php_error = FALSE)
{
	global $config;
	
	if ($config['log_errors'] === FALSE)
	{
		return;
	}

	if ( ! class_exists('CI_Log'))
	{
		include_once(BASEPATH.'libraries/Log.php');		
	}
	
	if ( ! isset($LOG))
	{
		$LOG = new CI_Log(
							$config['log_path'], 
							$config['log_threshold'], 
							$config['log_date_format']
						);
	}
	
	$LOG->write_log($level, $message, $php_error);
}


/**
* Exception Handler
*
* This is the custom exception handler we defined at the
* top of this file. The main reason we use this is permit 
* PHP errors to be logged in our own log files since we may 
* not have access to server logs. Since this function
* effectively intercepts PHP errors, however, we also need
* to display errors based on the current error_reporting level.
* We do that with the use of a PHP error template.
*
* @access	private
* @return	void
*/
function _exception_handler($severity, $message, $filepath, $line)
{
	global $config;
	
	 // We don't bother with "strict" notices since they will fill up
	 // the log file with information that isn't normally very
	 // helpful.  For example, if you are running PHP 5 and you
	 // use version 4 style class functions (without prefixes
	 // like "public", "private", etc.) you'll get notices telling
	 // you that these have been deprecated.
	 
	if ($severity == E_STRICT)
	{
		return;
	}

	// Send the PHP error to the log file...
	if ( ! class_exists('CI_Exceptions'))
	{
		include_once(BASEPATH.'libraries/Exceptions.php');
	}
	$error = new CI_Exceptions();

	// Should we display the error?  
	// We'll get the current error_reporting level and add its bits
	// with the severity bits to find out.
	
	if (($severity & error_reporting()) == $severity)
	{
		$error->show_php_error($severity, $message, $filepath, $line);
	}
	
	// Should we log the error?  No?  We're done...
	if ($config['log_errors'] === FALSE)
	{
		return;
	}

	$error->log_exception($severity, $message, $filepath, $line);
}

?>