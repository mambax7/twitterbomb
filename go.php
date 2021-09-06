<?php
	include('header.php');	
	
	$cid = isset($_REQUEST['cid'])? (int)$_REQUEST['cid'] :0;
	$sid = isset($_REQUEST['sid'])? (int)$_REQUEST['sid'] :0;
	$lid = isset($_REQUEST['lid'])? (int)$_REQUEST['lid'] :0;
	$catid = isset($_REQUEST['catid'])? (int)$_REQUEST['catid'] :0;
	$uri = $_REQUEST['uri'] ?? '';
	
	if (0 == $cid || $url= '' || 0 == $catid) {
		header('HTTP/1.1 301 Moved Permanently');
		header('Location: '.XOOPS_URL);
		exit(0);
	}
	
	if (0 != $sid) {
		$scheduler_handler = xoops_getModuleHandler('scheduler', 'twitterbomb');
		$scheduler_handler->plusHit($sid);
	}           	
	if (0 != $lid) {
		$log_handler = xoops_getModuleHandler('log', 'twitterbomb');
		$log_handler->plusHit($lid);
	}
	if (0 != $cid) {
		$campaign_handler = xoops_getModuleHandler('campaign', 'twitterbomb');
		$campaign_handler->plusHit($cid);
	}
	if (0 != $catid) {
		$category_handler = xoops_getModuleHandler('category', 'twitterbomb');
		$category_handler->plusHit($catid);
	}	
	header('HTTP/1.1 301 Moved Permanently');
	header('Location: '.$uri);

?>
