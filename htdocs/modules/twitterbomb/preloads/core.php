<?php

defined('XOOPS_ROOT_PATH') or die('Restricted access');

class TwitterbombCorePreload extends XoopsPreloadItem
{
	//function eventCoreIncludeCommonEnd($args)
    //{
    //	include('../post.common.end.php');
    //}
    
	function eventCoreFooterEnd($args)
    {
    	include('../post.common.end.php');
    	include('../post.footer.end.php');
    }

    function eventCoreHeaderCacheEnd($args)
    {
    	include('../post.common.end.php');
    	include('../post.cache.end.php');
    }
    
}
?>