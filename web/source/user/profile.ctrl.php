<?php
/**
 * [WeEngine System] Copyright (c) 2014 WE7.CC
 * WeEngine is NOT a free software, it under the license terms, visited http://www.we7.cc/ for more details.
 */
defined('IN_IA') or exit('Access Denied');
load()->model('user');
load()->func('file');

$dos = array('base', 'post');
$do = in_array($do, $dos) ? $do : 'base';
$_W['page']['title'] = '账号信息 - 我的账户 - 用户管理';

if ($do == 'post' && $_W['isajax'] && $_W['ispost']) {
	$type = trim($_GPC['type']);

	if ($_W['isfounder']) {
		$uid = is_array($_GPC['uid']) ? 0 : intval($_GPC['uid']);
	} else {
		$uid = $_W['uid'];
	}
	if (empty($uid) || empty($type)) {
		message(error(40035, '参数错误，请刷新后重试！'), '', 'ajax');
	}
	$user = user_single($uid);
	if (empty($user)) {
		message(error(-1, '用户不存在或已经被删除！'), '', 'ajax');
	}

	$users_profile_exist = pdo_get('users_profile', array('uid' => $uid));

	if ($type == 'birth') {
		if ($users_profile_exist['year'] == $_GPC['year'] && $users_profile_exist['month'] == $_GPC['month'] && $users_profile_exist['day'] == $_GPC['day']) message(error(0, '未作修改！'), '', 'ajax');
	} elseif ($type == 'reside') {
		if ($users_profile_exist['province'] == $_GPC['province'] && $users_profile_exist['city'] == $_GPC['city'] && $users_profile_exist['district'] == $_GPC['district']) message(error(0, '未作修改！'), '', 'ajax');
	} else {
		if (in_array($type, array('username', 'password'))) {
			if ($user[$type] == $_GPC[$type] && $type != 'password') message(error(0, '未做修改！'), '', 'ajax');
		} else {
			if ($users_profile_exist[$type] == $_GPC[$type]) message(error(0, '未作修改！'), '', 'ajax');
		}
	}
	switch ($type) {
		case 'avatar':
			if ($users_profile_exist) {
				$result = pdo_update('users_profile', array('avatar' => $_GPC['avatar']), array('uid' => $uid));
			} else {
				$data = array(
						'uid' => $uid,
						'createtime' => TIMESTAMP,
						'avatar' => $_GPC['avatar']
					);
				$result = pdo_insert('users_profile', $data);
			}
			break;
		case 'username':
			$founders = explode(',', $_W['config']['setting']['founder']);
			if (in_array($uid, $founders)) {
				message(error(1, '用户名不可与网站创始人同名！'), '', 'ajax');
			}
			$result = pdo_update('users', array('username' => trim($_GPC['username'])), array('uid' => $uid));
			break;
		case 'password':
			if ($_GPC['newpwd'] !== $_GPC['renewpwd']) message(error(2, '两次密码不一致！'), '', 'ajax');
			if (!$_W['isfounder']) {
				$pwd = user_hash($_GPC['oldpwd'], $user['salt']);
				if ($pwd != $user['password']) message(error(3, '原密码不正确！'), '', 'ajax');
			}
			$newpwd = user_hash($_GPC['newpwd'], $user['salt']);
			if ($newpwd == $user['password']) {
				message(error(0, '未作修改！'), '', 'ajax');
			}
			$result = pdo_update('users', array('password' => $newpwd), array('uid' => $uid));
			break;
		case 'endtime' :
			if ($_GPC['endtype'] == 1) {
				$endtime = 0;
			} else {
				$endtime = strtotime($_GPC['endtime']);
			}
			$result = pdo_update('users', array('endtime' => $endtime), array('uid' => $uid));
			break;
		case 'realname':
			if ($users_profile_exist) {
				$result = pdo_update('users_profile', array('realname' => trim($_GPC['realname'])), array('uid' => $uid));
			} else {
				$data = array(
						'uid' => $uid,
						'createtime' => TIMESTAMP,
						'realname' => trim($_GPC['realname'])
					);
				$result = pdo_insert('users_profile', $data);
			}
			break;
		case 'birth':
			if ($users_profile_exist) {
				$result = pdo_update('users_profile', array('birthyear' => intval($_GPC['year']), 'birthmonth' => intval($_GPC['month']), 'birthday' => intval($_GPC['day'])), array('uid' => $uid));
			} else {
				$data = array(
						'uid' => $uid,
						'createtime' => TIMESTAMP,
						'birthyear' => intval($_GPC['year']),
						'birthmonth' => intval($_GPC['month']),
						'birthday' => intval($_GPC['day'])
					);
				$result = pdo_insert('users_profile', $data);
			}
			break;
		case 'address':
			if ($users_profile_exist) {
				$result = pdo_update('users_profile', array('address' => trim($_GPC['address'])), array('uid' => $uid));
			} else {
				$data = array(
						'uid' => $uid,
						'createtime' => TIMESTAMP,
						'address' => trim($_GPC['address'])
					);
				$result = pdo_insert('users_profile', $data);
			}
			break;
		case 'reside':
			if ($users_profile_exist) {
				$result = pdo_update('users_profile', array('resideprovince' => $_GPC['province'], 'residecity' => $_GPC['city'], 'residedist' => $_GPC['district']), array('uid' => $uid));
			} else {
				$data = array(
						'uid' => $uid,
						'createtime' => TIMESTAMP,
						'resideprovince' => $_GPC['province'],
						'residecity' => $_GPC['city'],
						'residedist' => $_GPC['district']
					);
				$result = pdo_insert('users_profile', $data);
			}
			break;
	}
	if ($result) {
		pdo_update('users_profile', array('edittime' => TIMESTAMP), array('uid' => $uid));
		message(error(0, '修改成功！'), '', 'ajax');
	} else {
		message(error(1, '修改失败，请稍候重试！'), '', 'ajax');
	}
}

if ($do == 'base') {
	$user = user_single($_W['uid']);
	if (empty($user)) {
		message('抱歉，用户不存在或是已经被删除！', url('user/profile'), 'error');
	}
	$user['last_visit'] = date('Y-m-d H:i:s', $user['lastvisit']);
	$profile = pdo_get('users_profile', array('uid' => $_W['uid']));
	if (!empty($profile)) {
		$avatar = file_fetch($profile['avatar']);
		if (is_error($avatar)) {
			$profile['avatar'] = './resource/images/nopic-107.png';
		}
		$profile['reside'] = array(
			'province' => $profile['resideprovince'],
			'city' => $profile['residecity'],
			'district' => $profile['residedist']
		);
		$profile['birth'] = array(
			'year' => $profile['birthyear'],
			'month' => $profile['birthmonth'],
			'day' => $profile['birthday'],
		);
		$profile['avatar'] = tomedia($profile['avatar']);
		$profile['resides'] = $profile['resideprovince'] .' '. $profile['residecity'] .' '. $profile['residedist'] ;

		$profile['births'] = ($profile['birthyear'] ? $profile['birthyear'] : '--') . '年' . ($profile['birthmonth'] ? $profile['birthmonth'] : '--') . '月' . ($profile['birthday'] ? $profile['birthday'] : '--') .'日';
	}
	template('user/profile');
}