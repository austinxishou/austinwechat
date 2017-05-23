<?php
/**
 * [WeEngine System] Copyright (c) 2014 WE7.CC
 * WeEngine is NOT a free software, it under the license terms, visited http://www.we7.cc/ for more details.
 */
defined('IN_IA') or exit('Access Denied');

load()->model('cache');
load()->model('setting');

$_W['page']['title'] = '更新缓存 - 设置 - 系统管理';

if (checksubmit('submit', true)) {
	pdo_delete('core_cache');
	cache_clean();
	
	cache_build_template();
	cache_build_users_struct();
	cache_build_setting();
	cache_build_frame_menu();
	cache_build_module_subscribe_type();
	cache_build_cloud_ad();
	message(error(0, '更新缓存成功！'), '', 'ajax');
}

template('system/updatecache');