<?php
set_include_path(implode(PATH_SEPARATOR, array(
    realpath('library'), realpath('model'),
    get_include_path(),
)));

require_once 'Zend/Date.php';
require_once 'Zend/Config/Ini.php';

// DEFINE DATE
Zend_Date::setOptions(array('format_type' => 'php'));

//=======================================================================	
// ENGINE OF TIME LIMIT CHECKER
//=======================================================================	
try {
	$config = new Zend_Config_Ini('config/gtass.ini', 'development');
} catch (Exception $e) {
	echo $e->getMesssage(); exit();
}

$params = array (	
	'url'		=> $config->gtass->url,
	'username'	=> $config->gtass->username,
	'password'	=> $config->gtass->password,
);
