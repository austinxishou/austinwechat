<?php
/**
 * [WeEngine System] Copyright (c) 2014 WE7.CC
 * WeEngine is NOT a free software, it under the license terms, visited http://www.we7.cc/ for more details.
 */
 
defined('IN_IA') or exit('Access Denied');

$site = WeUtility::createModuleSite($entry['module']);
if(!is_error($site)) {
	$method = 'doMobile' . ucfirst($entry['do']);
    logging_run($method, "功能模块方法为");
	exit($site->$method());
}
exit();