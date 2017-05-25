<?php defined('IN_IA') or exit('Access Denied');?><?php (!empty($this) && $this instanceof WeModuleSite || 0) ? (include $this->template('common/header-base', TEMPLATE_INCLUDEPATH)) : (include template('common/header-base', TEMPLATE_INCLUDEPATH));?>
<?php  $frames = buildframes(FRAME);_calc_current_frames($frames);?>
<div class="head">
	<nav class="navbar navbar-default" role="navigation">
		<div class="container">
			<div class="navbar-header">
				<a class="navbar-brand" href="<?php  echo $_W['siteroot'];?>">
					<img src="<?php  if(!empty($_W['setting']['copyright']['blogo'])) { ?><?php  echo tomedia($_W['setting']['copyright']['blogo'])?><?php  } else { ?>./resource/images/logo/logo.png<?php  } ?>" class="pull-left" width="110px" height="35px">
					<span class="version"><?php echo IMS_VERSION;?></span>
				</a>
			</div>
			<?php  if(!empty($_W['uid'])) { ?>
			<div class="collapse navbar-collapse">
				<ul class="nav navbar-nav navbar-left">
					<?php  global $top_nav?>
					<?php  if(is_array($top_nav)) { foreach($top_nav as $nav) { ?>
						<li<?php  if(FRAME == $nav['name']) { ?> class="active"<?php  } ?>><a href="<?php  if(empty($nav['url'])) { ?><?php  echo url('home/welcome/' . $nav['name']);?><?php  } else { ?><?php  echo $nav['url'];?><?php  } ?>" <?php  if(!empty($nav['blank'])) { ?>target="_blank"<?php  } ?>><?php  echo $nav['title'];?></a></li>
					<?php  } } ?>
				</ul>
				<ul class="nav navbar-nav navbar-right">
					<li class="dropdown">
						<a href="javascript:;" class="dropdown-toggle" data-toggle="dropdown"><i class="wi wi-user color-gray"></i><?php  echo $_W['user']['username'];?> <span class="caret"></span></a>
						<ul class="dropdown-menu color-gray" role="menu">
							<li>
								<a href="<?php  echo url('user/profile');?>" target="_blank"><i class="wi wi-account color-gray"></i> 我的账号</a>
							</li>
							<?php  if($_W['isfounder']) { ?>
							<li class="divider"></li>
							<li><a href="<?php  echo url('cloud/upgrade');?>" target="_blank"><i class="wi wi-update color-gray"></i> 自动更新</a></li>
							<li><a href="<?php  echo url('system/updatecache');?>" target="_blank"><i class="wi wi-cache color-gray"></i> 更新缓存</a></li>
							<li class="divider"></li>
							<?php  } ?>
							<li>
								<a href="<?php  echo url('user/logout');?>"><i class="fa fa-sign-out color-gray"></i> 退出系统</a>
							</li>
						</ul>
					</li>
				</ul>
			</div>
			<?php  } else { ?>
			<div class="collapse navbar-collapse">
				<ul class="nav navbar-nav navbar-right">
					<li class="dropdown"><a href="<?php  echo url('user/register');?>">注册</a></li>
					<li class="dropdown"><a href="<?php  echo url('user/login');?>">登陆</a></li>
				</ul>
			</div>
			<?php  } ?>
		</div>
	</nav>
</div>
<!-- austin modify 注释到期检测 -->
<!-- <?php  if(empty($_COOKIE['check_setmeal']) && !empty($_W['account']['endtime']) && ($_W['account']['endtime'] - TIMESTAMP < (6*86400))) { ?>
	<div class="upgrade-tips we7-body-alert" id="setmeal-tips">
		<div class="alert-info text-center">
			<a href="<?php  echo url('user/edit', array('uid' => $_W['account']['uid']));?>" target="_blank">
				您的服务有效期限：<?php  echo date('Y-m-d', $_W['account']['starttime']);?> ~ <?php  echo date('Y-m-d', $_W['account']['endtime']);?>.
				<?php  if($_W['account']['endtime'] < TIMESTAMP) { ?>
				目前已到期，请联系管理员续费
				<?php  } else { ?>
				将在<?php  echo floor(($_W['account']['endtime'] - strtotime(date('Y-m-d')))/86400);?>天后到期，请及时付费
				<?php  } ?>
			</a>
			<span class="tips-close" onclick="check_setmeal_hide();"><i class="wi wi-error-sign"></i></span>
		</div>
	</div>
	<script>
		function check_setmeal_hide() {
			util.cookie.set('check_setmeal', 1, 1800);
			$('#setmeal-tips').hide();
			return false;
		}
	</script>
<?php  } ?> -->
<div class="main">
	<div class="container">

	<?php  if(in_array(FRAME, array('account', 'system', 'adviertisement')) && !in_array($_GPC['a'], array('news-show', 'notice-show'))) { ?>

		<?php  if(!empty($_GPC['m']) && !in_array($_GPC['m'], array('keyword', 'special', 'welcome', 'default', 'userapi')) || defined('IN_MODULE')) { ?>
			<a href="javascript:;" class="js-big-main button-to-big color-gray" title="加宽">
			<?php  if($_GPC['main-lg']) { ?>正常<?php  } else { ?>宽屏<?php  } ?></a>
		<?php  } ?>

		<div class="panel panel-content">

			<div class="content-head panel-heading">
				<?php  if(!empty($_GPC['m']) && !in_array($_GPC['m'], array('keyword', 'special', 'welcome', 'default', 'userapi')) || defined('IN_MODULE')) { ?>
					<a href="<?php  echo url('home/welcome/account')?>" class="we7-head-back"><i class="wi wi-back-circle"></i></a>
					<?php  if(file_exists(IA_ROOT. "/addons/". $_W['current_module']['name']. "/icon-custom.jpg")) { ?>
					<img src="<?php  echo tomedia("addons/".$_W['current_module']['name']."/icon-custom.jpg")?>" class="head-app-logo" onerror="this.src='./resource/images/gw-wx.gif'">
					<?php  } else { ?>
					<img src="<?php  echo tomedia("addons/".$_W['current_module']['name']."/icon.jpg")?>" class="head-app-logo" onerror="this.src='./resource/images/gw-wx.gif'">
					<?php  } ?>
					<span class="font-lg"><?php  echo $_W['current_module']['title'];?></span>
					
				<?php  } else if(FRAME == 'account') { ?>
					<img src="<?php  echo tomedia('headimg_'.$_W['account']['acid'].'.jpg')?>?time=<?php  echo time()?>" class="head-logo">
					<span class="font-lg"><?php  echo $_W['account']['name'];?></span>
					<?php  if($_W['account']['level'] == 1 || $_W['account']['level'] == 3) { ?>
						<span class="label label-primary">订阅号 </span><?php  if($_W['account']['level'] == 3) { ?><span class="label label-primary">已认证</span><?php  } ?>
					<?php  } ?>
					<?php  if($_W['account']['level'] == 2 || $_W['account']['level'] == 4) { ?>
						<span class="label label-primary">服务号</span> <?php  if($_W['account']['level'] == 4) { ?><span class="label label-primary">已认证</span><?php  } ?>
					<?php  } ?>
					<?php  if($_W['uniaccount']['isconnect'] == 0) { ?>
						<span class="color-red"><i class="wi wi-warning-sign"></i>未接入微信公众号 </span>
						<span class="color-default"><a href="<?php  echo url('account/post', array('uniacid' => $_W['account']['uniacid'], 'acid' => $_W['acid']))?>">立即接入</a></span>
					<?php  } ?>
						<span class="pull-right"><a href="<?php  echo url('account/display')?>" class="color-default we7-margin-left"><i class="wi wi-cut color-default"></i>切换公众号</a></span>
						<?php  if(uni_permission($_W['uid'], $_W['uniacid']) != ACCOUNT_MANAGE_NAME_OPERATOR) { ?>
						<span class="pull-right"><a href="<?php  echo url('account/post', array('uniacid' => $_W['account']['uniacid'], 'acid' => $_W['acid']))?>"><i class="wi wi-appsetting"></i>公众号设置</a></span>
						<?php  } ?>
						<span class="pull-right"><a href="<?php  echo url('utility/emulator');?>" target="_blank"><i class="wi wi-iphone"></i>模拟测试</a></span>
				<?php  } ?>
				<?php  if(FRAME == 'system') { ?>
					<span class="font-lg"><i class="wi wi-setting"></i> 系统管理</span>
				<?php  } ?>
				<?php  if(FRAME == 'adviertisement') { ?>
				<span class="font-lg"><i class="wi wi-ad"></i>广告联盟</span>
				<?php  } ?>
			</div>
			<div class="panel-body">
				<div class="left-menu">
					<?php  if(is_array($frames['section'])) { foreach($frames['section'] as $frame_section_id => $frame_section) { ?>
						<?php  if(!isset($frame_section['is_display']) || !empty($frame_section['is_display'])) { ?>
						<div class="panel panel-menu">
							<?php  if($frame_section['title']) { ?>
								<div class="panel-heading">
									<?php  echo $frame_section['title'];?><span class="wi wi-appsetting pull-right setting"></span>
								</div>
							<?php  } ?>
							<ul class="list-group">
								<?php  if(is_array($frame_section['menu'])) { foreach($frame_section['menu'] as $menu_id => $menu) { ?>
									<?php  if(!empty($menu['is_display'])) { ?>
										<?php  if($menu_id == 'platform_module_more') { ?>
										<li class="list-group-item list-group-more">
											<a href="<?php  echo url('profile/module');?>" target="_blank"><span class="label label-more">更多应用</span></a>
										</li>
										<?php  } else { ?>
										<li class="list-group-item <?php  if($menu['active']) { ?>active<?php  } ?>">
											<a href="<?php  echo $menu['url'];?>" class="text-over" <?php  if($frame_section_id == 'platform_module') { ?>target="_blank"<?php  } ?>>
												<?php  if($menu['icon']) { ?>
													<?php  if($frame_section_id == 'platform_module') { ?>
														<img src="<?php  echo $menu['icon'];?>"/>
													<?php  } else { ?>
														<i class="<?php  echo $menu['icon'];?>"></i>
													<?php  } ?>
												<?php  } ?>
												<?php  echo $menu['title'];?>
											</a>
										</li>
										<?php  } ?>
									<?php  } ?>
								<?php  } } ?>
							</ul>
						</div>
						<?php  } ?>
					<?php  } } ?>
				</div>
				<div class="right-content">
	<?php  } ?>