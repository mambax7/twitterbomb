<?php

defined('XOOPS_ROOT_PATH') or die('Restricted access');

class TwitterbombCorePreload extends XoopsPreloadItem
{
    //function eventCoreIncludeCommonEnd($args)
    //{
    //	include('../post.common.end.php');
    //}

    public function eventCoreFooterEnd($args)
    {
        include(dirname(__FILE__, 2) . '/post.common.end.php');
        include(dirname(__FILE__, 2) . '/post.footer.end.php');
    }

    public function eventCoreHeaderCacheEnd($args)
    {
        include(dirname(__FILE__, 2) . '/post.common.end.php');
        include(dirname(__FILE__, 2) . '/post.cache.end.php');
    }
}
?>
