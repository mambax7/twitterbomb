<?php

	require dirname(__FILE__, 3) . '/mainfile.php';
	
	if (!defined('_CHARSET'))
		define('_CHARSET', 'UTF-8');
	if (!defined('_CHARSET_ISO'))
		define('_CHARSET_ISO', 'ISO-8859-1');
		
	$GLOBALS['myts'] = MyTextSanitizer::getInstance();
	
	$module_handler = xoops_getHandler('module');
	$config_handler = xoops_getHandler('config');
	$GLOBALS['twitterbombModule'] = $module_handler->getByDirname('twitterbomb');
	$GLOBALS['twitterbombModuleConfig'] = $config_handler->getConfigList($GLOBALS['twitterbombModule']->getVar('mid')); 
	
	include(__DIR__ . '/include/functions.php');
	include(__DIR__ . '/include/formobjects.twitterbomb.php');
	include(__DIR__ . '/include/forms.twitterbomb.php');
	
?>
