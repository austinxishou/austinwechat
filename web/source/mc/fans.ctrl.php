<?php
/**
 * [WeEngine System] Copyright (c) 2014 WE7.CC
 * WeEngine is NOT a free software, it under the license terms, visited http://www.we7.cc/ for more details.
 */

defined('IN_IA') or exit('Access Denied');
set_time_limit(60);

load()->model('mc');

$dos = array('display', 'add_tag', 'del_tag', 'edit_tagname', 'edit_fans_tag', 'batch_edit_fans_tag', 'download_fans', 'sync', 'fans_sync_set');
$do = in_array($do, $dos) ? $do : 'display';
uni_user_permission_check('mc_fans');

if ($do == 'display') {
	$_W['page']['title'] = '粉丝列表';
	$fans_tag = mc_fans_groups(true);
	$pageindex = max(1, intval($_GPC['page']));
	$search_mod = intval($_GPC['search_mod']) == '' ? 1 : intval($_GPC['search_mod']);
	$pagesize = 10;

	$param = array(
		':uniacid' => $_W['uniacid'],
		':acid' => $_W['acid']
		);
	$condition = " WHERE f.`uniacid` = :uniacid AND f.`acid` = :acid";
	$tag = intval($_GPC['tag']) ? intval($_GPC['tag']) : 0;
	if (!empty($tag)) {
		$param[':tagid'] = $tag;
		$condition .= " AND m.`tagid` = :tagid";
	}
	if ($_GPC['type'] == 'bind') {
		$condition .= " AND f.`uid` > 0";
		$type = 'bind';
	}
	$nickname = $_GPC['nickname'] ? addslashes(trim($_GPC['nickname'])) : '';
	if (!empty($nickname)) {
		if ($search_mod == 1) {
			$condition .= " AND ((f.`nickname` = :nickname) OR (f.`openid` = :openid))";
			$param[':nickname'] = $nickname;
			$param[':openid'] = $nickname;
		} else {
			$condition .= " AND ((f.`nickname` LIKE :nickname) OR (f.`openid` LIKE :openid))";
			$param[':nickname'] = "%".$nickname."%";
			$param[':openid'] = "%".$nickname."%";
		}
	}
	if (!empty($_GPC['time']['start'])) {
		$starttime = strtotime($_GPC['time']['start']);
		$endtime = strtotime($_GPC['time']['end']) + 86399;
	} else {
		$starttime = strtotime('-3 month', time());
		$endtime = time();
	}
	if (empty($tag)) {
		$param[':starttime'] = $starttime;
		$param[':endtime'] = $endtime;
	}
	$follow = intval($_GPC['follow']) ? intval($_GPC['follow']) : 1;
	if ($follow == 1) {
		$orderby = " ORDER BY f.`followtime` DESC";
		$condition .= " AND f.`follow` = 1";
		if (!empty($starttime) && empty($tag)) {
			$condition .= " AND f.`followtime` >= :starttime AND f.`followtime` <= :endtime";
		}
	} elseif ($follow == 2) {
		$orderby = " ORDER BY f.`unfollowtime` DESC";
		$condition .= " AND f.`follow` = 0";
		if (!empty($starttime) && empty($tag)) {
			$condition .= " AND f.`unfollowtime` >= :starttime AND f.`unfollowtime` <= :endtime";
		}
	}
	$fans_list = pdo_fetchall("SELECT f.* FROM " .tablename('mc_mapping_fans')." AS f LEFT JOIN ".tablename('mc_fans_tag_mapping')." AS m ON m.`fanid` = f.`fanid`". $condition. " GROUP BY f.`fanid`" . $orderby . " LIMIT " .($pageindex - 1) * $pagesize.",".$pagesize, $param);
	if (!empty($fans_list)) {
		foreach ($fans_list as &$v) {
			$v['tag_show'] = mc_show_tag($v['groupid']);
			$v['tag_show'] = explode(',', $v['tag_show']);
			$v['groupid'] = trim($v['groupid'], ',');
			if (!empty($v['uid'])) {
				$user = mc_fetch($v['uid'], array('realname', 'nickname', 'mobile', 'email', 'avatar'));
			}
			if (!empty($user)) {
				$v['member'] = $user;
			}
			if (!empty($v['tag']) && is_string($v['tag'])) {
				if (is_base64($v['tag'])) {
					$v['tag'] = base64_decode($v['tag']);
				}
								if (is_serialized($v['tag'])) {
					$v['tag'] = @iunserializer($v['tag']);
				}
				if (!empty($v['tag']['headimgurl'])) {
					$v['tag']['avatar'] = tomedia($v['tag']['headimgurl']);
				}
			}
			if (empty($v['tag'])) {
				$v['tag'] = array();
			}
			if (empty($v['user']['nickname']) && !empty($v['tag']['nickname'])) {
				$v['user']['nickname'] = $v['tag']['nickname'];
			}
			if (empty($v['user']['avatar']) && !empty($v['tag']['avatar'])) {
				$v['user']['avatar'] = $v['tag']['avatar'];
			}
			unset($user,$niemmo,$niemmo_effective);
		}
		unset($v);
	}

	$total = pdo_fetchcolumn("SELECT COUNT(DISTINCT f.`fanid`) FROM " .tablename('mc_mapping_fans')." AS f LEFT JOIN ".tablename('mc_fans_tag_mapping').' AS m ON m.`fanid` = f.`fanid`'.$condition, $param);
	$pager = pagination($total, $pageindex, $pagesize);
	$fans['total'] = pdo_fetchcolumn('SELECT COUNT(*) FROM ' . tablename('mc_mapping_fans') . ' WHERE uniacid = :uniacid AND acid = :acid AND follow = 1', array(':uniacid' => $_W['uniacid'], ':acid' => $_W['acid']));
}

if ($do == 'add_tag') {
	$tag_name = trim($_GPC['tag']);
	if (empty($tag_name)) {
		message(error(1, '请填写标称名称'), '', 'ajax');
	}
	$account_api = WeAccount::create();
	$result = $account_api->fansTagAdd($tag_name);
	if (is_error($result)) {
		message($result, '', 'ajax');
	} else {
		message(error(0), '', 'ajax');
	}
}

if ($do == 'del_tag') {
	$tagid = intval($_GPC['tag']);
	if (empty($tagid)) {
		message(error(1, '标签id为空'), '', 'ajax');
	}
	$account_api = WeAccount::create();
	$tags = $account_api->fansTagDelete($tagid);

	if (!is_error($tags)) {
		$fans_list = pdo_getall('mc_mapping_fans', array('groupid LIKE' => "%,{$tagid},%"));
		$count = count($fans_list);
		if (!empty($count)) {
			$buffSize = ceil($count / 500);
			for ($i = 0; $i < $buffSize; $i++) {
				$sql = '';
				$wechat_fans = array_slice($fans_list, $i * 500, 500);
				foreach ($wechat_fans as $fans) {
					$tagids = trim(str_replace(','.$tagid.',', ',', $fans['groupid']), ',');
					if ($tagids == ',') {
						$tagids = '';
					}
					$sql .= 'UPDATE ' . tablename('mc_mapping_fans') . " SET `groupid`='" . $tagids . "' WHERE `fanid`={$fans['fanid']};";
				}
				pdo_query($sql); 					}
		}
		pdo_delete('mc_fans_tag_mapping', array('tagid' => $tagid));
		message(error(0, 'success'), '', 'ajax');
	} else {
		message(error(-1, $tags['message']), '', 'ajax');
	}
}

if ($do == 'edit_tagname') {
	$tag = intval($_GPC['tag']);
	if (empty($tag)) {
		message(error(1, '标签id为空'), '', 'ajax');
	}
	$tag_name = trim($_GPC['tag_name']);
	if (empty($tag_name)) {
		message(error(1, '标签名为空'), '', 'ajax');
	}

	$account_api = WeAccount::create();
	$result = $account_api->fansTagEdit($tag, $tag_name);
	if (is_error($result)) {
		message($result, '', 'ajax');
	} else {
		message(error('0'), '', 'ajax');
	}
}

if ($do == 'edit_fans_tag') {
	$fanid = intval($_GPC['fanid']);
	$tags = $_GPC['tags'];
	if (empty($tags) || !is_array($tags)) {
		message(error(1, '请选择标签'), '', 'ajax');
	}
	$openid = pdo_getcolumn('mc_mapping_fans', array('uniacid' => $_W['uniacid'], 'fanid' => $fanid), 'openid');
	$account_api = WeAccount::create();
	$result = $account_api->fansTagTagging($openid, $tags);
	if (!is_error($result)) {
		pdo_delete('mc_fans_tag_mapping', array('fanid' => $fanid));
		if (!empty($tags)) {
			foreach ($tags as $tag) {
				pdo_insert('mc_fans_tag_mapping', array('fanid' => $fanid, 'tagid' => $tag));
			}
			$tags = implode(',', $tags);
			pdo_update('mc_mapping_fans', array('groupid' => $tags), array('fanid' => $fanid));
		}
	}
	message($result, '', 'ajax');
}

if ($do == 'batch_edit_fans_tag') {
	$openid_list = $_GPC['openid'];
	if (empty($openid_list) || !is_array($openid_list)) {
		message(error(1, '请选择粉丝'), '', 'ajax');
	}
	$tags = $_GPC['tag'];
	if (empty($tags) || !is_array($tags)) {
		message(error(1, '请选择标签'), '', 'ajax');
	}

	$account_api = WeAccount::create();
	foreach ($tags as $tag) {
		$result = $account_api->fansTagBatchTagging($openid_list, $tags[0]);
		if (!is_error($result)) {
			foreach ($openid_list as $openid) {
				$fan_info = pdo_get('mc_mapping_fans', array('uniacid' => $_W['uniacid'], 'openid' => $openid));
				pdo_insert('mc_fans_tag_mapping', array('fanid' => $fan_info['fanid'], 'tagid' => $tag));
				$groupid = $fan_info['group'].",".$tag;
				pdo_update('mc_mapping_fans', array('groupid' => $groupid), array('uniacid' => $_W['uniacid'], 'openid' => $openid));
			}
		} else {
			message($result, '', 'ajax');
		}
	}
	message(error(0), '', 'ajax');
}

if ($do == 'download_fans') {
	$next_openid = $_GPC['next_openid'];
	if (empty($next_openid)) {
		pdo_update('mc_mapping_fans', array('follow' => 0), array('uniacid' => $_W['uniacid']));
	}
	$account_api = WeAccount::create();
	$wechat_fans_list = $account_api->fansAll();
	if (!is_error($wechat_fans_list)) {
		$wechat_fans_count = count($wechat_fans_list['fans']);
		$total_page = ceil($wechat_fans_count / 500);
		for ($i = 0; $i < $total_page; $i++) {
			$wechat_fans = array_slice($wechat_fans_list['fans'], $i * 500, 500);
			$wechat_openids = implode("','", $wechat_fans);
			$wechat_openids = "'{$wechat_openids}'";
			$sql = 'SELECT `openid`, `uniacid`, `acid` FROM ' . tablename('mc_mapping_fans') . " WHERE `openid` IN ({$wechat_openids})";
			$system_fans = pdo_fetchall($sql, array(), 'openid');
			$add_fans_sql = '';
			foreach($wechat_fans as $openid) {
				if (empty($system_fans) || empty($system_fans[$openid])) {
					$salt = random(8);
					$add_fans_sql .= "('{$_W['acid']}', '{$_W['uniacid']}', 0, '{$openid}', '{$salt}', 1, 0, ''),";
				}
			}
			if (!empty($add_fans_sql)) {
				$add_fans_sql = rtrim($add_fans_sql, ',');
				$add_fans_sql = 'INSERT INTO ' . tablename('mc_mapping_fans') . ' (`acid`, `uniacid`, `uid`, `openid`, `salt`, `follow`, `followtime`, `tag`) VALUES ' . $add_fans_sql;
				$result = pdo_query($add_fans_sql);
			}
			pdo_query("UPDATE " . tablename('mc_mapping_fans') . " SET follow = '1' WHERE `openid` IN ({$wechat_openids})");
		}
		$return['total'] = $wechat_fans_list['total'];
		$return['count'] = !empty($wechat_fans_list['fans']) ? $wechat_fans_count : 0;
		$return['next'] = $wechat_fans_list['next'];
		message(error(0, $return), '', 'ajax');
	} else {
		message($wechat_fans_list, '', 'ajax');
	}
}

if ($do == 'sync') {
	$type = $_GPC['type'] == 'all' ? 'all' : 'check';
	if ($type == 'all') {
		$pageindex = $_GPC['pageindex'];
		$pageindex++;
		$sync_fans = pdo_getslice('mc_mapping_fans', array('uniacid' => $_W['uniacid'], 'acid' => $_W['acid'], 'follow' => '1'), array($pageindex, 5), $total, array(), '', 'fanid DESC');
		$total = ceil($total/5);
		if (!empty($sync_fans)) {
			foreach ($sync_fans as $fans) {
				mc_init_fans_info($fans['openid']);
			}
		}
		message(error(0, array('pageindex' => $pageindex, 'total' => $total)), '', 'ajax');
	}
	if ($type == 'check') {
		$openids = $_GPC['openids'];
		if (empty($openids) || !is_array($openids)) {
			message(error(1, '请选择粉丝'), '', 'ajax');
		}
		$sync_fans = pdo_getall('mc_mapping_fans', array('openid' => $openids));
		if (!empty($sync_fans)) {
			foreach ($sync_fans as $fans) {
				mc_init_fans_info($fans['openid']);
			}
		}
		message(error(0, 'success'), '', 'ajax');
	}
}

if ($do == 'fans_sync_set') {
		$_W['page']['title'] = '更新粉丝信息 - 公众号选项';
	$operate = $_GPC['operate'];
	if ($operate == 'save_setting') {
		uni_setting_save('sync', intval($_GPC['setting']));
		message(error(0), '', 'ajax');
	}
	$setting = uni_setting($_W['uniacid'], array('sync'));
	$sync_setting = $setting['sync'];
}
template('mc/fans');

