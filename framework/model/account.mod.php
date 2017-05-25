<?php
/**
 * [WeEngine System] Copyright (c) 2014 WE7.CC
 * WeEngine is NOT a free software, it under the license terms, visited http://www.we7.cc/ for more details.
 */
defined('IN_IA') or exit('Access Denied');


function uni_create_permission($uid, $type = ACCOUNT_TYPE_OFFCIAL_NORMAL) {
	$groupid = pdo_fetchcolumn('SELECT groupid FROM ' . tablename('users') . ' WHERE uid = :uid', array(':uid' => $uid));
	$groupdata = pdo_fetch('SELECT maxaccount, maxsubaccount, maxwxapp FROM ' . tablename('users_group') . ' WHERE id = :id', array(':id' => $groupid));
	$list = pdo_fetchall('SELECT d.type, count(*) AS count FROM (SELECT u.uniacid, a.default_acid FROM ' . tablename('uni_account_users') . ' as u RIGHT JOIN '. tablename('uni_account').' as a  ON a.uniacid = u.uniacid  WHERE u.uid = :uid AND u.role = :role ) AS c LEFT JOIN '.tablename('account').' as d ON c.default_acid = d.acid WHERE d.isdeleted = 0 GROUP BY d.type', array(':uid' => $uid, ':role' => 'owner'));
	foreach ($list as $item) {
		if ($item['type'] == ACCOUNT_TYPE_APP_NORMAL) {
			$wxapp_num = $item['count'];
		} else {
			$account_num = $item['count'];
		}
	}
		if ($type == ACCOUNT_TYPE_OFFCIAL_NORMAL || $type == ACCOUNT_TYPE_OFFCIAL_AUTH) {
		if ($account_num >= $groupdata['maxaccount']) {
			return error('-1', '您所在的用户组最多只能创建' . $groupdata['maxaccount'] . '个主公众号');
		}
	} elseif ($type == ACCOUNT_TYPE_APP_NORMAL) {
		if ($wxapp_num >= $groupdata['maxwxapp']) {
			return error('-1', '您所在的用户组最多只能创建' . $groupdata['maxwxapp'] . '个小程序');
		}
	}
	return true;
}


function uni_owned($uid = 0) {
	global $_W;
	$uid = empty($uid) ? $_W['uid'] : intval($uid);
	$uniaccounts = array();
	$founders = explode(',', $_W['config']['setting']['founder']);
	if (in_array($uid, $founders)) {
		$uniaccounts = pdo_fetchall("SELECT * FROM " . tablename('uni_account') . " ORDER BY `uniacid` DESC", array(), 'uniacid');
	} else {
		$uniacids = pdo_fetchall("SELECT uniacid FROM " . tablename('uni_account_users') . " WHERE uid = :uid", array(':uid' => $uid), 'uniacid');
		if (!empty($uniacids)) {
			$uniaccounts = pdo_fetchall("SELECT * FROM " . tablename('uni_account') . " WHERE uniacid IN (" . implode(',', array_keys($uniacids)) . ") ORDER BY `uniacid` DESC", array(), 'uniacid');
		}
	}
	
	return $uniaccounts;
}


function uni_accounts($uniacid = 0) {
	global $_W;
	$uniacid = empty($uniacid) ? $_W['uniacid'] : intval($uniacid);
	$account_info = pdo_get('account', array('uniacid' => $uniacid));
	if (!empty($account_info)) {
		$accounts = pdo_fetchall("SELECT w.*, a.type, a.isconnect FROM " . tablename('account') . " a INNER JOIN " . tablename(uni_account_tablename($account_info['type'])) . " w USING(acid) WHERE a.uniacid = :uniacid AND a.isdeleted <> 1 ORDER BY a.acid ASC", array(':uniacid' => $uniacid), 'acid');
	}
	return !empty($accounts) ? $accounts : array();
}


function uni_fetch($uniacid = 0) {
	global $_W;
	$uniacid = empty($uniacid) ? $_W['uniacid'] : intval($uniacid);
	$cachekey = "uniaccount:{$uniacid}";
	$cache = cache_load($cachekey);
	if (!empty($cache)) {
		if(!isset($cache['isconnect'])){
			$cache['isconnect'] = pdo_fetchcolumn('SELECT isconnect FROM ' . tablename('account') . ' WHERE uniacid = :uniacid', array(':uniacid' => $uniacid));
			cache_write($cachekey, $cache);
		}
		return $cache;
	}
	$account = uni_account_default($uniacid);
	$owneruid = pdo_fetchcolumn("SELECT uid FROM ".tablename('uni_account_users')." WHERE uniacid = :uniacid AND role = 'owner'", array(':uniacid' => $uniacid));
	load()->model('user');
	$owner = user_single(array('uid' => $owneruid));
	$account['uid'] = $owner['uid'];
	$account['starttime'] = $owner['starttime'];
	$account['endtime'] = $owner['endtime'];
	load()->model('mc');
	$account['groups'] = mc_groups($uniacid);
	$account['grouplevel'] = pdo_fetchcolumn('SELECT grouplevel FROM ' . tablename('uni_settings') . ' WHERE uniacid = :uniacid', array(':uniacid' => $uniacid));
	cache_write($cachekey, $account);
	return $account;
}

// 获取系统已安装模块
function uni_modules($enabledOnly = true) {
	global $_W;
	$cachekey = "unimodules:{$_W['uniacid']}:{$enabledOnly}";
	$cache = cache_load($cachekey);
	if (!empty($cache)) {
		return $cache;
	}
	$owneruid = pdo_fetchcolumn("SELECT uid FROM ".tablename('uni_account_users')." WHERE uniacid = :uniacid AND role = 'owner'", array(':uniacid' => $_W['uniacid']));
	load()->model('user');
	$owner = user_single(array('uid' => $owneruid));
	if (empty($owner)) {
		$groupid = '-1';
	} else {
		$groupid = $owner['groupid'];
	}
	$extend = pdo_getall('uni_account_group', array('uniacid' => $_W['uniacid']), array(), 'groupid');
	if (!empty($extend)) {
		$groupid = '-2';
	}
	if (empty($groupid)) {
		$modules = pdo_fetchall("SELECT * FROM " . tablename('modules') . " WHERE issystem = 1 ORDER BY issystem DESC, mid ASC", array(), 'name');
	} elseif ($groupid == '-1') {
		$modules = pdo_fetchall("SELECT * FROM " . tablename('modules') . " ORDER BY issystem DESC, mid ASC", array(), 'name');
	} else {
		$group = pdo_fetch("SELECT id, name, package FROM ".tablename('users_group')." WHERE id = :id", array(':id' => $groupid));
		if (!empty($group)) {
			$packageids = iunserializer($group['package']);
		} else {
			$packageids = array();
		}
		if (!empty($extend)) {
			foreach ($extend as $extend_packageid => $row) {
				$packageids[] = $extend_packageid;
			}
		}
		if (!empty($packageids) && in_array('-1', $packageids)) {
			$modules = pdo_fetchall("SELECT * FROM " . tablename('modules') . " ORDER BY issystem DESC, mid ASC", array(), 'name');
		} else {
			$wechatgroup = pdo_fetchall("SELECT `modules` FROM " . tablename('uni_group') . " WHERE " . (!empty($packageids) ? "id IN ('".implode("','", $packageids)."') OR " : '') . " uniacid = '{$_W['uniacid']}'");
			$ms = array();
			$mssql = '';
			if (!empty($wechatgroup)) {
				foreach ($wechatgroup as $row) {
					$row['modules'] = iunserializer($row['modules']);
					$row['wxapp'] = iunserializer($row['wxapp']);
					if (!empty($row['modules'])) {
						foreach ($row['modules'] as $modulename) {
							$ms[$modulename] = $modulename;
						}
					}
					if (!empty($row['wxapp'])) {
						foreach ($row['wxapp'] as $modulename) {
							$ms[$modulename] = $modulename;
						}
					}
				}
				$mssql = " OR `name` IN ('".implode("','", $ms)."')";
			}
			$modules = pdo_fetchall("SELECT * FROM " . tablename('modules') . " WHERE issystem = 1{$mssql} ORDER BY issystem DESC, mid ASC", array(), 'name');
		}
	}
	$focus_enable_modules = pdo_getall('modules', array('issystem' => 2));
	if (!empty($focus_enable_modules)) {
		foreach ($focus_enable_modules as $focus_enable_modules_row) {
			$modules[$focus_enable_modules_row['name']] = $focus_enable_modules_row;
		}
	}
	if (!empty($modules)) {
		$ms = implode("','", array_keys($modules));
		$ms = "'{$ms}'";
		$mymodules = pdo_fetchall("SELECT `module`, `enabled`, `settings` FROM " . tablename('uni_account_modules') . " WHERE uniacid = '{$_W['uniacid']}' AND `module` IN ({$ms}) ORDER BY enabled DESC", array(), 'module');
	}
	if (!empty($mymodules)) {
		foreach ($mymodules as $name => $row) {
			if ($enabledOnly && !$modules[$name]['issystem']) {
				if ($row['enabled'] == 0 || empty($modules[$name])) {
					unset($modules[$name]);
					continue;
				}
			}
			if (!empty($row['settings'])) {
				$modules[$name]['config'] = iunserializer($row['settings']);
			}
			$modules[$name]['enabled'] = $row['enabled'];
		}
	}
	if (!empty($modules)) {
		foreach ($modules as $name => &$row) {
			if ($row['issystem'] == 1) {
				$row['enabled'] = 1;
			} elseif (!isset($row['enabled'])) {
				$row['enabled'] = 1;
			}
			if (empty($row['config'])) {
				$row['config'] = array();
			}
			if (!empty($row['subscribes'])) {
				$row['subscribes'] = iunserializer($row['subscribes']);
			}
			if (!empty($row['handles'])) {
				$row['handles'] = iunserializer($row['handles']);
			}
			$row['isdisplay'] = 1;
			unset($modules[$name]['description']);
		}
	}
	$modules['core'] = array('title' => '系统事件处理模块', 'name' => 'core', 'issystem' => 1, 'enabled' => 1, 'isdisplay' => 0);
	cache_write($cachekey, $modules);
	return $modules;
}

function uni_modules_app_binding() {
	global $_W;
	$cachekey = "unimodulesappbinding:{$_W['uniacid']}";
	$cache = cache_load($cachekey);
	if (!empty($cache)) {
		return $cache;
	}
	load()->model('module');
	$result = array();
	$modules = uni_modules();
	if(!empty($modules)) {
		foreach($modules as $module) {
			if($module['type'] == 'system') {
				continue;
			}
			$entries = module_app_entries($module['name'], array('home', 'profile', 'shortcut', 'function', 'cover'));
			if(empty($entries)) {
				continue;
			}
			if($module['type'] == '') {
				$module['type'] = 'other';
			}
			$result[$module['name']] = array(
				'name' => $module['name'],
				'type' => $module['type'],
				'title' => $module['title'],
				'entries' => array(
					'cover' => $entries['cover'],
					'home' => $entries['home'],
					'profile' => $entries['profile'],
					'shortcut' => $entries['shortcut'],
					'function' => $entries['function']
				)
			);
			unset($module);
		}
	}
	cache_write($cachekey, $result);
	return $result;
}


function uni_groups($groupids = array()) {
	$condition = ' WHERE uniacid = 0';
	if (!is_array($groupids)) {
		$groupids = array($groupids);
	}
	if (!empty($groupids)) {
		foreach ($groupids as $i => $row) {
			$groupids[$i] = intval($row);
		}
		unset($row);
		$condition .= " AND id IN (" . implode(',', $groupids) . ")";
	}
	$list = pdo_fetchall("SELECT * FROM " . tablename('uni_group') . $condition . " ORDER BY id DESC", array(), 'id');
	if (in_array('-1', $groupids)) {
		$list[-1] = array('id' => -1, 'name' => '所有服务');
	}
	if (in_array('0', $groupids)) {
		$list[0] = array('id' => 0, 'name' => '基础服务');
	}
	if (!empty($list)) {
		foreach ($list as $k=>&$row) {
			if (!empty($row['modules'])) {
				$modules = iunserializer($row['modules']);
				if (is_array($modules)) {
					$modules_lists = pdo_fetchall("SELECT name, title, app_support, wxapp_support FROM " . tablename('modules') . " WHERE name IN ('" . implode("','", $modules) . "')");
					$row['modules'] = $modules_lists;
					if (!empty($row['modules'])) {
						foreach ($row['modules'] as $key=>&$module) {
							if ($module['wxapp_support'] == 2) {
								$row['wxapp'][] = $module;
								unset($row['modules'][$key]);
							}
						}
					}

				}
			}
			if (!empty($row['templates'])) {
				$templates = iunserializer($row['templates']);
				if (is_array($templates)) {
					$row['templates'] = pdo_fetchall("SELECT name, title FROM " . tablename('site_templates') . " WHERE id IN ('" . implode("','", $templates) . "')");
				}
			}
		}
	}
	return $list;
}


function uni_templates() {
	global $_W;
	$owneruid = pdo_fetchcolumn("SELECT uid FROM ".tablename('uni_account_users')." WHERE uniacid = :uniacid AND role = 'owner'", array(':uniacid' => $_W['uniacid']));
	load()->model('user');
	$owner = user_single(array('uid' => $owneruid));
		if (empty($owner)) {
		$groupid = '-1';
	} else {
		$groupid = $owner['groupid'];
	}
	$extend = pdo_getall('uni_account_group', array('uniacid' => $_W['uniacid']), array(), 'groupid');
	if (!empty($extend)) {
		$groupid = '-2';
	}
	if (empty($groupid)) {
		$templates = pdo_fetchall("SELECT * FROM " . tablename('site_templates') . " WHERE name = 'default'", array(), 'id');
	} elseif ($groupid == '-1') {
		$templates = pdo_fetchall("SELECT * FROM " . tablename('site_templates') . " ORDER BY id ASC", array(), 'id');
	} else {
		$group = pdo_fetch("SELECT id, name, package FROM ".tablename('users_group')." WHERE id = :id", array(':id' => $groupid));
		$packageids = iunserializer($group['package']);
		if (!empty($extend)) {
			foreach ($extend as $extend_packageid => $row) {
				$packageids[] = $extend_packageid;
			}
		}
		if(is_array($packageids)) {
			if (in_array('-1', $packageids)) {
				$templates = pdo_fetchall("SELECT * FROM " . tablename('site_templates') . " ORDER BY id ASC", array(), 'id');
			} else {
				$wechatgroup = pdo_fetchall("SELECT `templates` FROM " . tablename('uni_group') . " WHERE id IN ('".implode("','", $packageids)."') OR uniacid = '{$_W['uniacid']}'");
				$ms = array();
				$mssql = '';
				if (!empty($wechatgroup)) {
					foreach ($wechatgroup as $row) {
						$row['templates'] = iunserializer($row['templates']);
						if (!empty($row['templates'])) {
							foreach ($row['templates'] as $templateid) {
								$ms[$templateid] = $templateid;
							}
						}
					}
					$ms[] = 1;
					$mssql = " `id` IN ('".implode("','", $ms)."')";
				}
				$templates = pdo_fetchall("SELECT * FROM " . tablename('site_templates') .(!empty($mssql) ? " WHERE $mssql" : '')." ORDER BY id DESC", array(), 'id');
			}
		}
	}
	if (empty($templates)) {
		$templates = pdo_fetchall("SELECT * FROM " . tablename('site_templates') . " WHERE id = 1 ORDER BY id DESC", array(), 'id');
	}
	return $templates;
}


function uni_setting_save($name, $value) {
	global $_W;
	if (empty($name)) {
		return false;
	}
	if (is_array($value)) {
		$value = serialize($value);
	}
	$unisetting = pdo_get('uni_settings', array('uniacid' => $_W['uniacid']), array('uniacid'));
	if (!empty($unisetting)) {
		pdo_update('uni_settings', array($name => $value), array('uniacid' => $_W['uniacid']));
	} else {
		pdo_insert('uni_settings', array($name => $value, 'uniacid' => $_W['uniacid']));
	}
	$cachekey = "unisetting:{$_W['uniacid']}";
	cache_delete($cachekey);
	return true;
}


function uni_setting_load($name = '', $uniacid = 0) {
	global $_W;
	$uniacid = empty($uniacid) ? $_W['uniacid'] : $uniacid;
	$cachekey = "unisetting:{$uniacid}";
	$unisetting = cache_load($cachekey);
	if (empty($unisetting)) {
		$unisetting = pdo_get('uni_settings', array('uniacid' => $uniacid));
		if (!empty($unisetting)) {
			$serialize = array('site_info', 'stat', 'oauth', 'passport', 'uc', 'notify', 
								'creditnames', 'default_message', 'creditbehaviors', 'shortcuts', 'payment', 
								'recharge', 'tplnotice', 'mcplugin');
			foreach ($unisetting as $key => &$row) {
				if (in_array($key, $serialize) && !empty($row)) {
					$row = (array)iunserializer($row);
				}
			}
		}
		cache_write($cachekey, $unisetting);
	}
	if (empty($unisetting)) {
		return array();
	}
	if (empty($name)) {
		return $unisetting;
	}
	if (!is_array($name)) {
		$name = array($name);
	}
	return array_elements($name, $unisetting);
}

if (!function_exists('uni_setting')) {
	function uni_setting($uniacid = 0, $fields = '*', $force_update = false) {
		global $_W;
		load()->model('account');
		if ($fields == '*') {
			$fields = '';
		}
		return uni_setting_load($fields, $uniacid);
	}
}


function uni_account_default($uniacid = 0) {
	global $_W;
	$uniacid = empty($uniacid) ? $_W['uniacid'] : intval($uniacid);
	$uni_account = pdo_fetch("SELECT * FROM ".tablename('uni_account')." a LEFT JOIN ".tablename('account')." w ON a.default_acid = w.acid WHERE a.uniacid = :uniacid", array(':uniacid' => $uniacid), 'acid');
	if (!empty($uni_account)) {
		$account = pdo_get(uni_account_tablename($uni_account['type']), array('acid' => $uni_account['acid']));
		$account['type'] = $uni_account['type'];
		$account['isconnect'] = $uni_account['isconnect'];
		return $account;
	}
}

function uni_account_tablename($type) {
	switch ($type) {
		case ACCOUNT_TYPE_OFFCIAL_NORMAL:
		case ACCOUNT_TYPE_OFFCIAL_AUTH:
			return 'account_wechats';
		case ACCOUNT_TYPE_APP_NORMAL:
			return 'account_wxapp';
	}
}


function uni_permission($uid = 0, $uniacid = 0) {
	global $_W;
	$uid = empty($uid) ? $_W['uid'] : intval($uid);
	
	$founders = explode(',', $_W['config']['setting']['founder']);
	if (in_array($uid, $founders)) {
		return ACCOUNT_MANAGE_NAME_FOUNDER;
	}
	if (!empty($uniacid)) {
		$role = pdo_getcolumn('uni_account_users', array('uid' => $uid, 'uniacid' => $uniacid), 'role');
		if ($role == ACCOUNT_MANAGE_NAME_OWNER) {
			$role = ACCOUNT_MANAGE_NAME_OWNER;
		} elseif ($role == ACCOUNT_MANAGE_NAME_MANAGER) {
			$role = ACCOUNT_MANAGE_NAME_MANAGER;
		} else {
			$role = ACCOUNT_MANAGE_NAME_OPERATOR;
		}
	} else {
		$roles = pdo_getall('uni_account_users', array('uid' => $uid), array('role'), 'role');
		$roles = array_keys($roles);
		if (in_array(ACCOUNT_MANAGE_NAME_OWNER, $roles)) {
			$role = ACCOUNT_MANAGE_NAME_OWNER;
		} elseif (in_array(ACCOUNT_MANAGE_NAME_MANAGER, $roles)) {
			$role = ACCOUNT_MANAGE_NAME_MANAGER;
		} elseif (in_array(ACCOUNT_MANAGE_NAME_OPERATOR, $roles)) {
			$role = ACCOUNT_MANAGE_NAME_OPERATOR;
		} else {
			$role = ACCOUNT_MANAGE_NAME_OPERATOR;
		}
	}
	return $role;
}


function uni_user_permission_exist($uid = 0, $uniacid = 0) {
	global $_W;
	$uid = intval($uid) > 0 ? $uid : $_W['uid'];
	$uniacid = intval($uniacid) > 0 ? $uniacid : $_W['uniacid'];
	if ($_W['role'] == 'founder') {
		return false;
	}
	if (FRAME == 'system') {
		return true;
	}
	$is_exist = pdo_get('users_permission', array('uid' => $uid, 'uniacid' => $uniacid), array('id'));
	if(empty($is_exist)) {
		return false;
	} else {
		return true;
	}
}


function uni_user_permission($type = 'system') {
	global $_W;
	$user_permission = pdo_getcolumn('users_permission', array('uid' => $_W['uid'], 'uniacid' => $_W['uniacid'], 'type' => $type), 'permission');
	if(!empty($user_permission)) {
		$user_permission = explode('|', $user_permission);
	} else {
		$user_permission = array('account*');
	}
	$permission_append = frames_menu_append();
		if (!empty($permission_append[$_W['role']])) {
		$user_permission = array_merge($user_permission, $permission_append[$_W['role']]);
	}
	return (array)$user_permission;
}

function uni_user_permission_check($permission_name, $show_message = true, $action = '') {
	global $_W, $_GPC;
	$user_has_permission = uni_user_permission_exist();
	if (empty($user_has_permission)) {
		return true;
	}
	$modulename = trim($_GPC['m']);
	$do = trim($_GPC['do']);
	$entry_id = intval($_GPC['eid']);
	
	if($action == 'reply') {
		$system_modules = system_modules();
		if(!empty($m) && !in_array($m, $system_modules)) {
			$permission_name = $m . '_rule';
			$users_permission = uni_user_permission($m);
		}
	} elseif($action == 'cover' && $eid > 0) {
		load()->model('module');
		$entry = module_entry($eid);
		if(!empty($entry)) {
			$permission_name = $entry['module'] . '_cover_' . trim($entry['do']);
			$users_permission = uni_user_permission($entry['module']);
		}
	} elseif($action == 'nav') {
				if(!empty($m)) {
			$permission_name = "{$m}_{$do}";
			$users_permission = uni_user_permission($m);
		} else {
			return true;
		}
	} else {
		$users_permission = uni_user_permission('system');
	}
	if(!isset($users_permission)) {
		$users_permission = uni_user_permission('system');
	}
	if($users_permission[0] != 'all' && !in_array($permission_name, $users_permission)) {
		if($show_message) {
			message('您没有进行该操作的权限', referer(), 'error');
		} else {
			return false;
		}
	}
	return true;
}


function uni_user_module_permission_check($action = '', $module_name = '') {
	global $_GPC;
	$status = uni_user_permission_exist();
	if(!is_error($status)) {
		return true;
	}
	$do = $_GPC['do'];
	$m = $_GPC['m'];
	if(!empty($do) && !empty($m)) {
		$is_exist = pdo_fetch('SELECT eid FROM ' . tablename('modules_bindings') . ' WHERE module=:module AND do = :do AND entry = :entry', array(':module' => $m, ':do' => $do, ':entry' => 'menu'));
		if(empty($is_exist)) {
			return true;
		}
	}
	if(empty($module_name)) {
		$module_name = IN_MODULE;
	}
	$permission = uni_user_permission($module_name);
	if(empty($permission) || ($permission[0] != 'all' && !empty($action) && !in_array($action, $permission))) {
		return false;
	}
	return true;
}


function uni_user_account_permission() {
	global $_W;
	$group = pdo_fetch('SELECT * FROM ' . tablename('users_group') . ' WHERE id = :id', array(':id' => $_W['user']['groupid']));
	$uniacocunts = pdo_getall('uni_account_users', array('uid' => $_W['uid'], 'role' => 'owner'), array(), 'uniacid');
	if (empty($uniacocunts)) {
		$uniacid_num = 0;
	} else {
				$uniacid_num = pdo_fetchcolumn('SELECT COUNT(*) FROM (SELECT u.uniacid, a.default_acid FROM ' . tablename('uni_account_users') . ' as u RIGHT JOIN '. tablename('uni_account').' as a  ON a.uniacid = u.uniacid  WHERE u.uid = :uid AND u.role = :role ) AS c LEFT JOIN '.tablename('account').' as d ON c.default_acid = d.acid WHERE d.isdeleted = 0', array(':uid' => $_W['uid'], ':role' => 'owner'));
	}
	$data = array(
		'group_name' => $group['name'],
		'maxaccount' => $group['maxaccount'],
		'uniacid_num' => $uniacid_num,
		'uniacid_limit' => max((intval($group['maxaccount']) - $uniacid_num), 0),
	);
	return $data;
}

function uni_update_week_stat() {
	global $_W;
	$cachekey = "stat:todaylock:{$_W['uniacid']}";
	$cache = cache_load($cachekey);
	if(!empty($cache) && $cache['expire'] > TIMESTAMP) {
		return true;
	}
	$seven_days = array(
		date('Ymd', strtotime('-1 days')),
		date('Ymd', strtotime('-2 days')),
		date('Ymd', strtotime('-3 days')),
		date('Ymd', strtotime('-4 days')),
		date('Ymd', strtotime('-5 days')),
		date('Ymd', strtotime('-6 days')),
		date('Ymd', strtotime('-7 days')),
	);
	$week_stat_fans = pdo_getall('stat_fans', array('date' => $seven_days, 'uniacid' => $_W['uniacid']), '', 'date');
	$stat_update_yes = false;
	foreach ($seven_days as $sevens) {
		if (empty($week_stat_fans[$sevens]) || $week_stat_fans[$sevens]['cumulate'] <=0) {
			$stat_update_yes = true;
			break;
		}
	}
	if (empty($stat_update_yes)) {
		return true;
	}
	foreach($seven_days as $sevens) {
		if($_W['account']['level'] == ACCOUNT_SUBSCRIPTION_VERIFY || $_W['account']['level'] == ACCOUNT_SERVICE_VERIFY) {
			$account_obj = WeAccount::create();
			$weixin_stat = $account_obj->getFansStat();
			if(is_error($weixin_stat) || empty($weixin_stat)) {
				return error(-1, '调用微信接口错误');
			} else {
				$update_stat = array();
				$update_stat = array(
					'uniacid' => $_W['uniacid'],
					'new' => $weixin_stat[$sevens]['new'],
					'cancel' => $weixin_stat[$sevens]['cancel'],
					'cumulate' => $weixin_stat[$sevens]['cumulate'],
					'date' => $sevens,
				);
			}
		} else {
			$update_stat = array();
			$update_stat['cumulate'] = pdo_fetchcolumn("SELECT COUNT(*) FROM " . tablename('mc_mapping_fans') . " WHERE acid = :acid AND uniacid = :uniacid AND follow = :follow AND followtime < :endtime", array(':acid' => $_W['acid'], ':uniacid' => $_W['uniacid'], ':endtime' => strtotime($sevens)+86400, ':follow' => 1));
			$update_stat['date'] = $sevens;
			$update_stat['new'] = $week_stat_fans[$sevens]['new'];
			$update_stat['cancel'] = $week_stat_fans[$sevens]['cancel'];
			$update_stat['uniacid'] = $_W['uniacid'];
		}
		if(empty($week_stat_fans[$sevens])) {
			pdo_insert('stat_fans', $update_stat);
		} elseif (empty($week_stat_fans[$sevens]['cumulate']) || $week_stat_fans[$sevens]['cumulate'] < 0) {
			pdo_update('stat_fans', $update_stat, array('id' => $week_stat_fans[$sevens]['id']));
		}
	}
	cache_write($cachekey, array('expire' => TIMESTAMP + 7200));
	return true;
}


function account_create($uniacid, $account) {
	$accountdata = array('uniacid' => $uniacid, 'type' => $account['type'], 'hash' => random(8));
	pdo_insert('account', $accountdata);
	$acid = pdo_insertid();
	$account['acid'] = $acid;
	$account['token'] = random(32);
	$account['encodingaeskey'] = random(43);
	$account['uniacid'] = $uniacid;
	unset($account['type']);
	pdo_insert('account_wechats', $account);
	return $acid;
}


function account_fetch($acid) {
	$account_info = pdo_get('account', array('acid' => $acid));
	if (empty($account_info)) {
		return error(-1, '公众号不存在');
	}
	$account = pdo_fetch("SELECT w.*, a.type, a.isconnect FROM " . tablename('account') . " a INNER JOIN " . tablename(uni_account_tablename($account_info['type'])) . " w USING(acid) WHERE acid = :acid AND a.isdeleted = '0'", array(':acid' => $acid));
	if (empty($account)) {
		return error(1, '公众号不存在');
	}
	$uniacid = $account['uniacid'];
	$owneruid = pdo_fetchcolumn("SELECT uid FROM ".tablename('uni_account_users')." WHERE uniacid = :uniacid AND role = 'owner'", array(':uniacid' => $uniacid));
	load()->model('user');
	$owner = user_single(array('uid' => $owneruid));
	$account['uid'] = $owner['uid'];
	$account['starttime'] = $owner['starttime'];
	$account['endtime'] = $owner['endtime'];
	$account['thumb'] = tomedia('headimg_'.$account['acid']. '.jpg').'?time='.time();
	load()->model('mc');
	$account['groups'] = mc_groups($uniacid);
	$account['grouplevel'] = pdo_fetchcolumn('SELECT grouplevel FROM ' . tablename('uni_settings') . ' WHERE uniacid = :uniacid', array(':uniacid' => $uniacid));
	return $account;
}


function uni_setmeal($uniacid = 0) {
	global $_W;
	if(!$uniacid) {
		$uniacid = $_W['uniacid'];
	}
	$owneruid = pdo_fetchcolumn("SELECT uid FROM ".tablename('uni_account_users')." WHERE uniacid = :uniacid AND role = 'owner'", array(':uniacid' => $uniacid));
	if(empty($owneruid)) {
		$user = array(
			'uid' => -1,
			'username' => '创始人',
			'timelimit' => '未设置',
			'groupid' => '-1',
			'groupname' => '所有服务'
		);
		return $user;
	}
	load()->model('user');
	$groups = pdo_getall('users_group', array(), array('id', 'name'), 'id');
	$owner = user_single(array('uid' => $owneruid));
	$user = array(
		'uid' => $owner['uid'],
		'username' => $owner['username'],
		'groupid' => $owner['groupid'],
		'groupname' => $groups[$owner['groupid']]['name']
	);
	if(empty($owner['endtime'])) {
		$user['timelimit'] = date('Y-m-d', $owner['starttime']) . ' ~ 无限制' ;
	} else {
		if($owner['endtime'] <= TIMESTAMP) {
			$user['timelimit'] = '已到期';
		} else {
			$year = 0;
			$month = 0;
			$day = 0;
			$endtime = $owner['endtime'];
			$time = strtotime('+1 year');
			while ($endtime > $time)
			{
				$year = $year + 1;
				$time = strtotime("+1 year", $time);
			};
			$time = strtotime("-1 year", $time);
			$time = strtotime("+1 month", $time);
			while($endtime > $time)
			{
				$month = $month + 1;
				$time = strtotime("+1 month", $time);
			} ;
			$time = strtotime("-1 month", $time);
			$time = strtotime("+1 day", $time);
			while($endtime > $time)
			{
				$day = $day + 1;
				$time = strtotime("+1 day", $time);
			} ;
			if (empty($year)) {
				$timelimit = empty($month)? $day.'天' : date('Y-m-d', $owner['starttime']) . '~'. date('Y-m-d', $owner['endtime']);
			}else {
				$timelimit = date('Y-m-d', $owner['starttime']) . '~'. date('Y-m-d', $owner['endtime']);
			}
			$user['timelimit'] = $timelimit;
		}
	}
	return $user;
}


function uni_is_multi_acid($uniacid = 0) {
	global $_W;
	if(!$uniacid) {
		$uniacid = $_W['uniacid'];
	}
	$cachekey = "unicount:{$uniacid}";
	$nums = cache_load($cachekey);
	$nums = intval($nums);
	if(!$nums) {
		$nums = pdo_fetchcolumn('SELECT COUNT(*) FROM ' . tablename('account_wechats') . ' WHERE uniacid = :uniacid', array(':uniacid' => $_W['uniacid']));
		cache_write($cachekey, $nums);
	}
	if($nums == 1) {
		return false;
	}
	return true;
}

function account_delete($acid) {
	global $_W;
	load()->func('file');
	load()->model('module');
		$account = pdo_get('uni_account', array('default_acid' => $acid));
	if ($account) {
		$uniacid = $account['uniacid'];
		$state = uni_permission($_W['uid'], $uniacid);
		if($state != ACCOUNT_MANAGE_NAME_FOUNDER && $state != ACCOUNT_MANAGE_NAME_OWNER) {
			message('没有该公众号操作权限！', url('account/recycle'), 'error');
		}
		if($uniacid == $_W['uniacid']) {
			isetcookie('__uniacid', '');
		}
		cache_delete("unicount:{$uniacid}");
		$modules = array();
				$rules = pdo_fetchall("SELECT id, module FROM ".tablename('rule')." WHERE uniacid = '{$uniacid}'");
		if (!empty($rules)) {
			foreach ($rules as $index => $rule) {
				$deleteid[] = $rule['id'];
			}
			pdo_delete('rule', "id IN ('".implode("','", $deleteid)."')");
		}

		$subaccount = pdo_fetchall("SELECT acid FROM ".tablename('account')." WHERE uniacid = :uniacid", array(':uniacid' => $uniacid));
		if (!empty($subaccount)) {
			foreach ($subaccount as $account) {
				@unlink(IA_ROOT . '/attachment/qrcode_'.$account['acid'].'.jpg');
				@unlink(IA_ROOT . '/attachment/headimg_'.$account['acid'].'.jpg');
				file_remote_delete('qrcode_'.$account['acid'].'.jpg');
				file_remote_delete('headimg_'.$account['acid'].'.jpg');
			}
			if (!empty($acid)) {
				rmdirs(IA_ROOT . '/attachment/images/' . $uniacid);
				@rmdir(IA_ROOT . '/attachment/images/' . $uniacid);
				rmdirs(IA_ROOT . '/attachment/audios/' . $uniacid);
				@rmdir(IA_ROOT . '/attachment/audios/' . $uniacid);
			}
		}

				$tables = array(
			'account','account_wechats', 'account_wxapp', 'wxapp_versions', 'core_attachment','core_paylog','core_queue','core_resource',
			'wechat_attachment', 'cover_reply', 'mc_chats_record','mc_credits_recharge','mc_credits_record',
			'mc_fans_groups','mc_groups','mc_handsel','mc_mapping_fans','mc_mapping_ucenter','mc_mass_record',
			'mc_member_address','mc_member_fields','mc_members','menu_event',
			'qrcode','qrcode_stat', 'rule','rule_keyword','site_article','site_category','site_multi','site_nav','site_slide',
			'site_styles','site_styles_vars','stat_keyword','stat_msg_history',
			'stat_rule','uni_account','uni_account_modules','uni_account_users','uni_settings', 'uni_group', 'uni_verifycode','users_permission',
			'mc_member_fields',
		);
		if (!empty($tables)) {
			foreach ($tables as $table) {
				$tablename = str_replace($GLOBALS['_W']['config']['db']['tablepre'], '', $table);
				pdo_delete($tablename, array( 'uniacid'=> $uniacid));
			}
		}
	} else {
		$account = account_fetch($acid);
		if (empty($account)) {
			message('子公众号不存在或是已经被删除');
		}
		$uniacid = $account['uniacid'];
		$state = uni_permission($_W['uid'], $uniacid);
		if($state != ACCOUNT_MANAGE_NAME_FOUNDER && $state != ACCOUNT_MANAGE_NAME_OWNER) {
			message('没有该公众号操作权限！', url('account/recycle'), 'error');
		}
		$uniaccount = uni_fetch($account['uniacid']);
		if ($uniaccount['default_acid'] == $acid) {
			message('默认子公众号不能删除');
		}
		pdo_delete('account', array('acid' => $acid));
		pdo_delete('account_wechats', array('acid' => $acid, 'uniacid' => $uniacid));
		cache_delete("unicount:{$uniacid}");
		cache_delete("unisetting:{$uniacid}");
		cache_delete('account:auth:refreshtoken:'.$acid);
		$oauth = uni_setting($uniacid, array('oauth'));
		if($oauth['oauth']['account'] == $acid) {
			$acid = pdo_fetchcolumn('SELECT acid FROM ' . tablename('account_wechats') . " WHERE uniacid = :id AND level = 4 AND secret != '' AND `key` != ''", array(':id' => $uniacid));
			pdo_update('uni_settings', array('oauth' => iserializer(array('account' => $acid, 'host' => $oauth['oauth']['host']))), array('uniacid' => $uniacid));
		}
		@unlink(IA_ROOT . '/attachment/qrcode_'.$acid.'.jpg');
		@unlink(IA_ROOT . '/attachment/headimg_'.$acid.'.jpg');
		file_remote_delete('qrcode_'.$acid.'.jpg');
		file_remote_delete('headimg_'.$acid.'.jpg');
	}
	return true;
}


function account_wechatpay_proxy () {
	global $_W;
	$proxy_account = cache_load(cache_system_key('proxy_wechatpay_account:'));
	if (empty($proxy_account)) {
		$proxy_account = cache_build_proxy_wechatpay_account();
	}
	unset($proxy_account['borrow'][$_W['uniacid']]);
	unset($proxy_account['service'][$_W['uniacid']]);
	return $proxy_account;
}
