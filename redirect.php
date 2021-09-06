<?php

include('header.php');

$oauth_handler = xoops_getModuleHandler('oauth', 'twitterbomb');

$oauth_handler->getTempAuthentication();

?>
