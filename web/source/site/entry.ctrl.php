<?php
/**
 * [WeEngine System] Copyright (c) 2014 WE7.CC
 * WeEngine is NOT a free software, it under the license terms, visited http://www.we7.cc/ for more details.
 */
defined('IN_IA') or exit('Access Denied');

load()->model('module');
load()->model('extension');

$eid = intval($_GPC['eid']);

if (!empty($eid)) {
	$entry = module_entry($eid);
} else {
	$entry = pdo_get('modules_bindings', array('module' => trim($_GPC['m']), 'do' => trim($_GPC['do'])));
	if (empty($entry)) {
		$entry = array(
			'module' => $_GPC['m'],
			'do' => $_GPC['do'],
			'state' => $_GPC['state'],
			'direct' => $_GPC['direct']
		);
	}
}
if (empty($entry) || empty($entry['do'])) {
	message('非法访问.');
}

if (!$entry['direct']) {
	checklogin();
	checkaccount();
	
	$module = module_fetch($entry['module']);
	if (empty($module)) {
		message("访问非法, 没有操作权限. (module: {$entry['module']})");
	}
	
	if ($entry['entry'] == 'menu') {
		$permission = uni_user_module_permission_check($entry['module'] . '_menu_' . $entry['do'], $entry['module']);
	} else {
		$permission = uni_user_module_permission_check($entry['module'] . '_rule', $entry['module']);
	}
	if (!$permission) {
		message('您没有权限进行该操作');
	}
	$_W['page']['title'] = $entry['title'];
	define('ACTIVE_FRAME_URL', url('site/entry/', array('eid' => $entry['eid'])));
}

if (!empty($entry['module']) && !empty($_W['founder'])) {
	if (ext_module_checkupdate($entry['module'])) {
		message('系统检测到该模块有更新，请点击“<a href="' . url('extension/module/upgrade', array('m' => $entry['module'])) . '">更新模块</a>”后继续使用！', '', 'error');
	}
}

$_GPC['__entry'] = $entry['title'];
$_GPC['__state'] = $entry['state'];
$_GPC['state'] = $entry['state'];
$_GPC['m'] = $entry['module'];
$_GPC['do'] = $entry['do'];

$modules = uni_modules();
$_W['current_module'] = $modules[$entry['module']];
$site = WeUtility::createModuleSite($entry['module']);

define('IN_MODULE', $entry['module']);

if (!is_error($site)) {
	if ($_W['role'] == ACCOUNT_MANAGE_NAME_OWNER) {
		$_W['role'] = ACCOUNT_MANAGE_NAME_MANAGER;
	}
	$sysmodule = system_modules();
	if (in_array($m, $sysmodule)) {
		$site_urls = $site->getTabUrls();
	}
	$method = 'doWeb' . ucfirst($entry['do']);
	exit($site->$method());
}
message("访问的方法 {$method} 不存在.", referer(), 'error');