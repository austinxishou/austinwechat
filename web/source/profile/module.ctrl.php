<?php
/**
 * [WeEngine System] Copyright (c) 2014 WE7.CC
 * WeEngine is NOT a free software, it under the license terms, visited http://www.we7.cc/ for more details.
 */
defined('IN_IA') or exit('Access Denied');

load()->model('module');
load()->model('account');
load()->model('user');

$dos = array('display', 'setting', 'shortcut', 'enable');
$do = !empty($_GPC['do']) ? $_GPC['do'] : 'display';

$modulelist = uni_modules(false);

if($do == 'display') {
	$_W['page']['title'] = '公众号 - 应用模块 - 更多应用';
	
	$pageindex = max(1, intval($_GPC['page']));
	$pagesize = 30;
	
	$condition = '';
	$total_condition = array(
		'issystem !=' => '1',
	);
	$params = array();
	if (!empty($_GPC['letter']) && strlen($_GPC['letter']) == 1) {
		$condition .= " AND a.title_initial = :title_initial";
		$params[':title_initial'] = $total_condition['title_initial'] = strtoupper($_GPC['letter']);
	}
	if (!empty($_GPC['keyword'])) {
		$keyword = trim(addslashes($_GPC['keyword']));
		$condition .= " AND a.title LIKE :keyword";
		$params[':keyword'] = $total_condition['title'] = "%{$keyword}%";
	}
	
	$owneruid = pdo_getcolumn('uni_account_users', array('uniacid' => $_W['uniacid'], 'role' => 'owner'), 'uid');
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
	} else {
		if ($groupid == '-1') {
			$packageids = array('-1');
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
		}
		if (!empty($packageids) && in_array('-1', $packageids)) {
			$modules = pdo_fetchall("SELECT a.name, a.title, a.issystem,
						(SELECT b.displayorder FROM " . tablename('uni_account_modules') . " AS b WHERE b.uniacid = '{$_W['uniacid']}' AND b.module = a.name) AS displayorder 
						FROM " . tablename('modules') . " AS a WHERE a.issystem <> '1' $condition ORDER BY displayorder DESC, a.mid ASC LIMIT " . ($pageindex - 1) * $pagesize . ", {$pagesize}", $params, 'name');
			$total = pdo_getcolumn('modules', $total_condition, 'COUNT(*)');
		} else {
			$wechatgroup = pdo_fetchall("SELECT `modules` FROM " . tablename('uni_group') . " WHERE " . (!empty($packageids) ? "id IN ('".implode("','", $packageids)."') OR " : '') . " uniacid = '{$_W['uniacid']}'");
			$package_module = array();
			if (!empty($wechatgroup)) {
				foreach ($wechatgroup as $row) {
					$row['modules'] = iunserializer($row['modules']);
					if (!empty($row['modules'])) {
						foreach ($row['modules'] as $modulename) {
							$package_module[$modulename] = $modulename;
						}
					}
				}
			}
			if ($package_module) {
				$modules = pdo_fetchall("SELECT a.name, a.title, a.issystem,
							(SELECT b.displayorder FROM " . tablename('uni_account_modules') . " AS b WHERE b.uniacid = '{$_W['uniacid']}' AND b.module = a.name) AS displayorder
							FROM " . tablename('modules') . " AS a WHERE a.issystem <> '1' 
							AND a.name IN ('".implode("','", $package_module)."') $condition ORDER BY displayorder DESC, a.mid ASC LIMIT " . ($pageindex - 1) * $pagesize . ", {$pagesize}", $params, 'name');

				$total_condition['name'] = $package_module;
				$total = pdo_getcolumn('modules', $total_condition, 'COUNT(*)');
			}
		}
		if (empty($modules)) {
			$modules = pdo_getall('modules', array('issystem' => 2), array(), 'name');
		}
		if (!empty($modules)) {
			$module_profile = pdo_getall('uni_account_modules', array('module' => array_keys($modules), 'uniacid' => $_W['uniacid']), array('module', 'enabled', 'shortcut'), 'module');
			if (!empty($module_profile)) {
				foreach ($module_profile as $name => $row) {
					$modules[$name]['enabled'] = $row['enabled'];
					$modules[$name]['shortcut'] = $row['shortcut'];
				}
			}
			foreach ($modules as $name => &$row) {
				if ($row['issystem'] == 1) {
					$row['enabled'] = 1;
				} elseif (!isset($row['enabled'])) {
					$row['enabled'] = 1;
				}
				$row['isdisplay'] = 1;
				if (file_exists(IA_ROOT. "/addons/". $name. "/icon-custom.jpg")) {
					$row['preview'] = tomedia('addons/' . $name . '/icon-custom.jpg');
				} else {
					$row['preview'] = tomedia('addons/' . $name . '/icon.jpg');
				}
			}
			unset($row);
			$pager = pagination($total, $pageindex, $pagesize);
		}
	}
	template('profile/module');
} elseif ($do == 'shortcut') {
	$status = intval($_GPC['shortcut']);
	$modulename = $_GPC['modulename'];
	$module = module_fetch($modulename);
	if(empty($module)) {
		message('抱歉，你操作的模块不能被访问！');
	}
	
	$module_status = pdo_get('uni_account_modules', array('module' => $modulename, 'uniacid' => $_W['uniacid']), array('id', 'shortcut'));
	if (empty($module_status)) {
		$data = array(
			'uniacid' => $_W['uniacid'],
			'module' => $modulename,
			'enabled' => STATUS_ON,
			'shortcut' => $status ? STATUS_ON : STATUS_OFF,
			'settings' => '',
		);
		pdo_insert('uni_account_modules', $data);
	} else {
		$data = array(
			'shortcut' => $status ? STATUS_ON : STATUS_OFF,
		);
		pdo_update('uni_account_modules', $data, array('id' => $module_status['id']));
	}
	if ($status) {
		message('添加模块快捷操作成功！', referer(), 'success');
	} else {
		message('取消模块快捷操作成功！', referer(), 'success');
	}
} elseif ($do == 'enable') {
	$modulename = $_GPC['modulename'];
	if(empty($modulelist[$modulename])) {
		message('抱歉，你操作的模块不能被访问！');
	}
	pdo_update('uni_account_modules', array(
		'enabled' => empty($_GPC['enabled']) ? STATUS_OFF : STATUS_ON,
	), array(
		'module' => $modulename,
		'uniacid' => $_W['uniacid']
	));
	cache_build_account_modules();
	message('模块操作成功！', referer(), 'success');
} elseif ($do == 'top') {
	$modulename = $_GPC['modulename'];
	$module = $modulelist[$modulename];
	if(empty($module)) {
		message('抱歉，你操作的模块不能被访问！');
	}
	$max_displayorder = (int)pdo_getcolumn('uni_account_modules', array('uniacid' => $_W['uniacid']), 'MAX(displayorder)');
	
	$module_profile = pdo_get('uni_account_modules', array('module' => $modulename, 'uniacid' => $_W['uniacid']));
	if (!empty($module_profile)) {
		pdo_update('uni_account_modules', array('displayorder' => ++$max_displayorder), array('id' => $module_profile['id']));
	} else {
		pdo_insert('uni_account_modules', array(
			'displayorder' => ++$max_displayorder, 
			'module' => $modulename,
			'uniacid' => $_W['uniacid'],
			'enabled' => STATUS_ON,
			'shortcut' => STATUS_OFF,
		));
	}
	message('模块置顶成功', referer(), 'success');
} elseif ($do == 'setting') {
	$modulename = $_GPC['m'];
	$module = $_W['current_module'] = $modulelist[$modulename];
	
	if(empty($module)) {
		message('抱歉，你操作的模块不能被访问！');
	}
		if(!uni_user_module_permission_check($modulename.'_settings', $modulename)) {
		message('您没有权限进行该操作');
	}
	
	$config = $module['config'];
	if (($module['settings'] == 2) && !is_file(IA_ROOT."/addons/{$module['name']}/developer.cer")) {
		
		if (empty($_W['setting']['site']['key']) || empty($_W['setting']['site']['token'])) {
			message('站点未注册，请先注册站点。', url('cloud/profile'), 'info');
		}
		
		if (empty($config)) {
			$config = array();
		}
		
		load()->model('cloud');
		load()->func('communication');
		
		$pro_attach_url = tomedia('pro_attach_url');
		$pro_attach_url = str_replace('pro_attach_url', '', $pro_attach_url);
		
		$module_simple = array_elements(array('name', 'type', 'title', 'version', 'settings'), $module);
		$module_simple['pro_attach_url'] = $pro_attach_url;
		
		$iframe = cloud_module_setting_prepare($module_simple, 'setting');
		$result = ihttp_post($iframe, array('inherit_setting' => base64_encode(iserializer($config))));
		if (is_error($result)) {
			message($result['message']);
		}
		$result = json_decode($result['content'], true);
		if (is_error($result)) {
			message($result['message']);
		}
		
		$module_simple = array_elements(array('name', 'type', 'title', 'version', 'settings'), $module);
		$module_simple['pro_attach_url'] = $pro_attach_url;
		
		$iframe = cloud_module_setting_prepare($module_simple, 'setting');
		template('profile/module_setting');
		exit();
	}
	$obj = WeUtility::createModule($module['name']);
	$obj->settingsDisplay($config);
	exit();
}