<?php
/**
 * [WeEngine System] Copyright (c) 2014 WE7.CC
 * WeEngine is NOT a free software, it under the license terms, visited http://www.we7.cc/ for more details.
 */
defined('IN_IA') or exit('Access Denied');
load()->model('module');

$dos = array('display', 'delete', 'post', 'save');
$do = !empty($_GPC['do']) ? $_GPC['do'] : 'display';
if ($_W['role'] != ACCOUNT_MANAGE_NAME_OWNER && $_W['role'] != ACCOUNT_MANAGE_NAME_MANAGER && $_W['role'] != ACCOUNT_MANAGE_NAME_FOUNDER) {
	message('无权限操作！', referer(), 'error');
}
if ($do != 'display' && $_W['role'] != ACCOUNT_MANAGE_NAME_FOUNDER) {
	message('您只有查看权限！', url('system/module-group'), 'error');
}

if ($do == 'save') {
	$modules = empty($_GPC['modules']) ? array() : (array)array_keys($_GPC['modules']);
	$wxapp = empty($_GPC['wxapp']) ? array() : (array)array_keys($_GPC['wxapp']);
	$package_info = array(
		'id' => intval($_GPC['id']),
		'name' => $_GPC['name'],
		'modules' => array_merge($modules, $wxapp),
		'templates' => $_GPC['templates'],
	);
	if (empty($package_info['name'])) {
		message(error(1, '请输入套餐名'), '', 'ajax');
	}

	if (!empty($package_info['modules'])) {
		$package_info['modules'] = iserializer($package_info['modules']);
	}
	if (!empty($package_info['templates'])) {
		foreach ($package_info['templates'] as $key => $template) {
			$package_info['templates'][] = $template['id'];
			unset($package_info['templates'][$key]);
		}
		$package_info['templates'] = iserializer($package_info['templates']);
	}
	if (!empty($package_info['id'])) {
		$name_exist = pdo_get('uni_group', array('uniacid' => 0, 'id <>' => $package_info['id'], 'name' => $package_info['name']));
		if (!empty($name_exist)) {
			message(error(1, '套餐名已存在'), '', 'ajax');
		}
		$packageid = $package_info['id'];
		unset($package_info['id']);
		pdo_update('uni_group', $package_info, array('id' => $packageid));
		cache_build_account_modules();
		module_build_privileges();
		message(error(0, url('system/module-group')), '', 'ajax');
	} else {
		$name_exist = pdo_get('uni_group', array('uniacid' => 0, 'name' => $package_info['name']));
		if (!empty($name_exist)) {
			message(error(1, '套餐名已存在'), '', 'ajax');
		}
		pdo_insert('uni_group', $package_info);
		module_build_privileges();
		message(error(0, url('system/module-group')), '', 'ajax');
	}
}

if ($do == 'display') {
	$_W['page']['title'] = '应用套餐列表';

	$param = array('uniacid' => 0);
	if (!empty($_GPC['name'])) {
		$param['name like'] = "%". trim($_GPC['name']) ."%";
	}
	$modules_group_list = uni_groups();
	if (!empty($modules_group_list)) {
		foreach ($modules_group_list as &$group) {
			if (!empty($group['modules'])) {
				$modules = $group['modules'];
				if (is_array($modules) && !empty($modules)) {
					if (!empty($group['modules'])) {
						foreach ($group['modules'] as &$module) {
							if (file_exists(IA_ROOT.'/addons/'.$module['name'].'/icon-custom.jpg')) {
								$module['logo'] = tomedia(IA_ROOT.'/addons/'.$module['name'].'/icon-custom.jpg');
							} else {
								$module['logo'] = tomedia(IA_ROOT.'/addons/'.$module['name'].'/icon.jpg');
							}
						}
						unset($module);
					}
				} else {
					$group['modules'] = array();
				}
			} else {
				$group['modules'] = array();
			}
			if (!empty($group['wxapp'])) {
				$wxapp = $group['wxapp'];
				if (is_array($wxapp) && !empty($wxapp)) {
					if (!empty($group['wxapp'])) {
						foreach ($group['wxapp'] as &$wxapp) {
							if (file_exists(IA_ROOT.'/addons/'.$wxapp['name'].'/icon-custom.jpg')) {
								$wxapp['logo'] = tomedia(IA_ROOT.'/addons/'.$wxapp['name'].'/icon-custom.jpg');
							} else {
								$wxapp['logo'] = tomedia(IA_ROOT.'/addons/'.$wxapp['name'].'/icon.jpg');
							}
						}
						unset($wxapp);
					}
				} else {
					$group['wxapp'] = array();
				}
			}
			$group['templates'] = !empty($group['templates']) ? $group['templates'] : array();
		}
		unset($group);
	}
}

if ($do == 'delete') {
	$id = intval($_GPC['id']);
	if (!empty($id)) {
		pdo_delete('uni_group', array('id' => $id));
		cache_build_account_modules();
	}
	message('删除成功！', referer(), 'success');
}

if ($do == 'post') {
	$id = intval($_GPC['id']);
	$_W['page']['title'] = $id ? '编辑应用套餐' : '添加应用套餐';

	$module_list = pdo_getall('modules', array('issystem' => 0), array(), 'name');
	$template_list = pdo_getall('site_templates',array(), array(), 'name');
	$group_have_module_app = array();
	$group_have_module_wxapp = array();
	$group_have_template = array();
	$group_have_module = array();
	if (!empty($id)) {
		$uni_module_groups = uni_groups();
		$module_group = $uni_module_groups[$id];
		$module_group['modules'] = empty($module_group['modules']) ? array() : iunserializer($module_group['modules']);
		if (!empty($module_group['modules'])) {
			foreach ($module_group['modules'] as $module) {
				$module_name = !empty($module['name']) ? $module['name'] : '';
				$module_info = pdo_get('modules', array('name' => $module_name));
				if (empty($module_info)) {
					continue;
				}
				$group_have_module[$module_info['name']] = $module_info;
				if (file_exists(IA_ROOT.'/addons/'.$module_name.'/icon-custom.jpg')) {
					$group_have_module[$module_info['name']]['logo'] = tomedia(IA_ROOT.'/addons/'.$module_name.'/icon-custom.jpg');
				} else {
					$group_have_module[$module_info['name']]['logo'] = tomedia(IA_ROOT.'/addons/'.$module_name.'/icon.jpg');
				}
				if ($group_have_module[$module_info['name']]['app_support'] == 2) {
					$group_have_module_app[$module_info['name']] = $group_have_module[$module_info['name']];
				}
			}
		}
		$module_group['wxapp'] = empty($module_group['wxapp']) ? array() : iunserializer($module_group['wxapp']);
		if (!empty($module_group['wxapp'])) {
			foreach ($module_group['wxapp'] as $module) {
				$module_name = !empty($module['name']) ? $module['name'] : '';
				$module_info = pdo_get('modules', array('name' => $module_name));
				if (empty($module_info)) {
					continue;
				}
				$group_have_module[$module_info['name']] = $module_info;
				if (file_exists(IA_ROOT.'/addons/'.$module_name.'/icon-custom.jpg')) {
					$group_have_module[$module_info['name']]['logo'] = tomedia(IA_ROOT.'/addons/'.$module_name.'/icon-custom.jpg');
				} else {
					$group_have_module[$module_info['name']]['logo'] = tomedia(IA_ROOT.'/addons/'.$module_name.'/icon.jpg');
				}
				if ($group_have_module[$module_info['name']]['wxapp_support'] == 2) {
					$group_have_module_wxapp[$module_info['name']] = $group_have_module[$module_info['name']];
				}
			}
		}
		$module_group['templates'] = empty($module_group['templates']) ? array() : iunserializer($module_group['templates']);
		if (!empty($module_group['templates'])) {
			foreach ($module_group['templates'] as $templateid) {
				$template_info = pdo_get('site_templates', array('id' => $templateid));
				if (!empty($template_info)) {
					$group_have_template[$template_info['name']] = $template_info;
				}
			}
		}
	}
	$group_not_have_module = array();	$group_not_have_module_app = array();
	$group_not_have_module_wxapp = array();
	if (!empty($module_list)) {
		foreach ($module_list as $name => $module_info) {
			if (!in_array($module_info['name'], array_keys($group_have_module))) {
				$group_not_have_module[$module_info['name']] = $module_info;
				if (file_exists(IA_ROOT.'/addons/'.$module_name.'/icon-custom.jpg')) {
					$group_not_have_module[$module_info['name']]['logo'] = tomedia(IA_ROOT.'/addons/'.$module_info['name'].'/icon-custom.jpg');
				} else {
					$group_not_have_module[$module_info['name']]['logo'] = tomedia(IA_ROOT.'/addons/'.$module_info['name'].'/icon.jpg');
				}
				if ($group_not_have_module[$module_info['name']]['app_support'] == 2) {
					$group_not_have_module_app[$module_info['name']] = $group_not_have_module[$module_info['name']];
				}
				if ($group_not_have_module[$module_info['name']]['wxapp_support'] == 2) {
					$group_not_have_module_wxapp[$module_info['name']] = $group_not_have_module[$module_info['name']];
				}
			}
		}
	}
	$group_not_have_template = array();	if (!empty($template_list)) {
		foreach ($template_list as $template) {
			if (!in_array($template['name'], array_keys($group_have_template))) {
				$group_not_have_template[$template['name']] =  $template;
			}
		}
	}
}

template('system/module-group');