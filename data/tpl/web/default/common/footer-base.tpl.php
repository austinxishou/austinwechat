<?php defined('IN_IA') or exit('Access Denied');?></div>
	<div class="container-fluid footer text-center" role="footer">	
		<span>
			<?php  if(empty($_W['setting']['copyright']['footerright'])) { ?>
				<a href="http://www.we7.cc">微信开发</a>&nbsp;&nbsp;
				<a href="http://s.we7.cc">微信应用</a>&nbsp;&nbsp;
				<a href="http://bbs.we7.cc">微擎论坛</a>&nbsp;&nbsp;
				<a href="http://wpa.b.qq.com/cgi/wpa.php?ln=1&key=XzkzODAwMzEzOV8xNzEwOTZfNDAwMDgyODUwMl8yXw">联系客服</a>
			<?php  } else { ?>
				<?php  echo $_W['setting']['copyright']['footerright'];?>
			<?php  } ?>
		</span>
		<br/>
		<span><?php  if(empty($_W['setting']['copyright']['footerleft'])) { ?>Powered by <a href="http://www.we7.cc"><b>微擎</b></a> v<?php echo IMS_VERSION;?> &copy; 2014-2015 <a href="http://www.we7.cc">www.we7.cc</a><?php  } else { ?><?php  echo $_W['setting']['copyright']['footerleft'];?><?php  } ?></span>	
	</div>
	<?php  if(!empty($_W['setting']['copyright']['statcode'])) { ?><?php  echo $_W['setting']['copyright']['statcode'];?><?php  } ?>
	<?php  if(!empty($_GPC['m']) && !in_array($_GPC['m'], array('keyword', 'special', 'welcome', 'default', 'userapi')) || defined('IN_MODULE')) { ?>
	<script>
		require(['bootstrap']);
	</script>
	<?php  } ?>
</body>
</html>
