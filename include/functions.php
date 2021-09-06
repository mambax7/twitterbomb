<?php

if (!function_exists('twitterbomb_getuser_id')) {
	function twitterbomb_getuser_id()
	{
		if (is_object($GLOBALS['xoopsUser']))
			$ret['uid'] = $GLOBALS['xoopsUser']->getVar('uid');
		else 
			$ret['uid'] = 0;
	
		if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
			$ret['ip']  = $_SERVER['HTTP_X_FORWARDED_FOR'];
			$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] . ':' . $_SERVER['REMOTE_ADDR'];
			$net = gethostbyaddr($_SERVER['HTTP_X_FORWARDED_FOR']);
		} else { 
			$ret['ip']  = $_SERVER['REMOTE_ADDR'];
			$ip = $_SERVER['REMOTE_ADDR'];
			$net = gethostbyaddr($_SERVER['REMOTE_ADDR']);
		}
		$ret['netbios'] = $net;
		
		$module_handler = xoops_getHandler('module');
		$config_handler = xoops_getHandler('config');
		$GLOBALS['twitterbombModule'] = $module_handler->getByDirname('twitterbomb');
		$GLOBALS['twitterbombModuleConfig'] = $config_handler->getConfigList($GLOBALS['twitterbombModule']->getVar('mid'));
		
		$ret['md5'] = md5(XOOPS_LICENSE_KEY . $GLOBALS['twitterbombModuleConfig']['salt'] . $ret['ip'] . $ret['netbios'] . $ret['uid']);	
		return $ret;
	}
}

if (!function_exists('twitterbomb_object2array')) {
	function twitterbomb_object2array($objects) {
		$ret = [];
		foreach((array)$objects as $key => $value) {
			if (is_object($value)) {
				$ret[$key] = twitterbomb_object2array($value);
			} elseif (is_array($value)) {
				$ret[$key] = twitterbomb_object2array($value);
			} else {
				$ret[$key] = $value;
			}
		}
		return $ret;
	}
}

if (!function_exists('twitterbomb_shortenurl')) {
	function twitterbomb_shortenurl($url) {
		$module_handler = xoops_getHandler('module');
		$config_handler = xoops_getHandler('config');
		$GLOBALS['twitterbombModule'] = $module_handler->getByDirname('twitterbomb');
		$GLOBALS['twitterbombModuleConfig'] = $config_handler->getConfigList($GLOBALS['twitterbombModule']->getVar('mid'));
	
		if (!empty($GLOBALS['twitterbombModuleConfig']['bitly_username'])&&!empty($GLOBALS['twitterbombModuleConfig']['bitly_apikey'])) {
			$source_url = $GLOBALS['twitterbombModuleConfig']['bitly_apiurl'].'/shorten?login='.$GLOBALS['twitterbombModuleConfig']['bitly_username'].'&apiKey='.$GLOBALS['twitterbombModuleConfig']['bitly_apikey'].'&format=json&longUrl='.urlencode($url);
			$cookies = XOOPS_ROOT_PATH.'/uploads/twitterbomb_'.md5($GLOBALS['twitterbombModuleConfig']['bitly_apikey']).'.cookie';
			if (!$ch = curl_init($source_url)) {
				return $url;
			}
			curl_setopt($ch, CURLOPT_COOKIEJAR, $cookies); 
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
			curl_setopt($ch, CURLOPT_USERAGENT, $GLOBALS['twitterbombModuleConfig']['user_agent']); 
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $GLOBALS['twitterbombModuleConfig']['curl_connect_timeout']);
			curl_setopt($ch, CURLOPT_TIMEOUT, $GLOBALS['twitterbombModuleConfig']['curl_timeout']);
			$data = curl_exec($ch); 
			curl_close($ch); 
			$result = twitterbomb_object2array(json_decode($data));
			if ($result['status_code']=200) {
				if (!empty($result['data']['url']))
					return $result['data']['url'];
				else 
					return $url;
			}
			return $url;
		} else {
			return $url;
		}
	}
}

if (!function_exists('twitterbomb_searchtwitter')) {
	function twitterbomb_searchtwitter($numberofresults = 10, $q='', $exceptions = [], $geocode='', $lang='en', $page=1, $result_type = 'mixed', $rpp = '100', $show_user = 'true', $until='', $since_id ='', $gathered=0, $next_url = '') {
		$module_handler = xoops_getHandler('module');
		$config_handler = xoops_getHandler('config');
		$GLOBALS['twitterbombModule'] = $module_handler->getByDirname('twitterbomb');
		$GLOBALS['twitterbombModuleConfig'] = $config_handler->getConfigList($GLOBALS['twitterbombModule']->getVar('mid'));
		$GLOBALS['execution_time'] = $GLOBALS['execution_time'] + 15;
		set_time_limit($GLOBALS['execution_time']);
		$ret = [];
		if (!empty($GLOBALS['twitterbombModuleConfig']['search_url'])) {
			if (empty($next_url))
				$source_url = $GLOBALS['twitterbombModuleConfig']['search_url'].'?q='.$q.(!empty($geocode)?'&geocode='.$geocode:'').(!empty($lang)?'&lang='.$lang:'').(!empty($page)?'&page='.$page:'').(!empty($result_type)?'&result_type='.$result_type:'').(!empty($rpp)?'&rpp='.$rpp:'').(!empty($show_user)?'&show_user='.$show_user:'').(!empty($until)?'&until='.$until:'').(!empty($since_id)?'&since_id='.$since_id:'');
			else 
				$source_url = $GLOBALS['twitterbombModuleConfig']['search_url'].$next_url;
				
			$cookies = XOOPS_ROOT_PATH.'/uploads/twitterbomb_'.md5($GLOBALS['twitterbombModuleConfig']['search_url']).'.cookie'; 
			if (!$ch = curl_init($source_url)) {
				return $url;
			}
			curl_setopt($ch, CURLOPT_COOKIEJAR, $cookies); 
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
			curl_setopt($ch, CURLOPT_USERAGENT, $GLOBALS['twitterbombModuleConfig']['user_agent']); 
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $GLOBALS['twitterbombModuleConfig']['curl_connect_timeout']);
			curl_setopt($ch, CURLOPT_TIMEOUT, $GLOBALS['twitterbombModuleConfig']['curl_timeout']);
			$data = curl_exec($ch);
			curl_close($ch); 
			unlink($cookies);
			$result = twitterbomb_object2array(json_decode($data));
			if (!empty($result['results'])) {
				foreach($result['results'] as $result) {
					if ($gathered<$numberofresults) {
						if (count($exceptions)>0&!empty($exceptions)) {
							$foundneg=0;
							$foundpos=0;
							$numbernegative=0;
							$numberpositive=0;
							$pass=true;
							foreach($exceptions as $searchfor) {
								if (!empty($searchfor)) {
									if ('-' == substr($searchfor, 0, 1)) {
										$numbernegative++;
										if (!strpos(strtolower(' '.$result['text']), strtolower(substr($searchfor,1,strlen($searchfor)-1)))) {
											$foundneg++;
										}
									} else {
										$numberpositive++;
										if (strpos(strtolower(' '.$result['text']), strtolower($searchfor))) {
											$foundpos++;
										} 
									}
								}
							}
							if (($numberpositive>0&&$foundpos/$numberpositive*100<$GLOBALS['twitterbombModuleConfig']['positive_match_perc']))
								$pass=true;
							elseif ($numbernegative>0&&$foundneg/$numbernegative*100>$GLOBALS['twitterbombModuleConfig']['negative_match_perc'])
								$pass=true;
							elseif (0 == $numberpositive && 0 == $foundpos)
								$pass=true;
							elseif (0 == $numbernegative && 0 == $foundneg)
								$pass=true;
							elseif ($numberpositive>0&&$foundpos>0)
								$pass=true;
							
							if ($numbernegative>0&&$foundneg/$numbernegative*100<$GLOBALS['twitterbombModuleConfig']['negative_match_perc'])
								$pass=false;
							elseif (0 == $foundpos && $numberpositive > 0)
								$pass=false;
						} else {
							$pass=true;
						}
						if (true == $pass) {
							$ret[$result['id_str']] = $result;
							$gathered++;
						} 
					}
				}
				if ($gathered<$numberofresults) {
					$page++;
					if ($page<$GLOBALS['twitterbombModuleConfig']['maximum_search_pages']+1) {
						foreach(twitterbomb_searchtwitter($numberofresults, $q, $exceptions, $geocode, $lang, $page, $result_type, $rpp, $show_user, $until, $since, $gathered, $result['next_page']) as $id=>$result) {
							$ret[$id] = $result;
						}
					}
				}
				return $ret;
			} else {
				return false;
			}
		} else {
			return $ret;
		}
	}
}
if (!function_exists('twitterbomb_adminMenu')) {
  function twitterbomb_adminMenu ($currentoption = 0)  {
	   	echo "<table width=\"100%\" border='0'><tr><td>";
	   	echo '<tr><td>';
	   	$indexAdmin = new ModuleAdmin();
	   	echo $indexAdmin->addNavigation(strtolower(basename($_SERVER['REQUEST_URI'])));
  	   	echo '</td></tr>';
	   	echo "<tr'><td><div id='form'>";
  }
  
  function twitterbomb_footer_adminMenu()
  {
		echo '</div></td></tr>';
  		echo '</table>';
		echo "<div align=\"center\"><a href=\"http://www.xoops.org\" target=\"_blank\"><img src=" . XOOPS_URL . '/' . $GLOBALS['twitterbombModule']->getInfo('icons32') . '/xoopsmicrobutton.gif'.' '." alt='XOOPS' title='XOOPS'></a></div>";
		echo "<div class='center smallsmall italic pad5'><strong>" . $GLOBALS['twitterbombModule']->getVar('name') . "</strong> is maintained by the <a class='tooltip' rel='external' href='http://www.xoops.org/' title='Visit XOOPS Community'>XOOPS Community</a> and <a class='tooltip' rel='external' href='http://www.chronolabs.coop/' title='Visit Chronolabs Co-op'>Chronolabs Co-op</a></div>";
  		
  }
}

if (!function_exists('twitterbomb_getSubCategoriesIn')) {
	function twitterbomb_getSubCategoriesIn($catid=0){
		$category_handler =& xoops_getModuleHandler('category', 'twitterbomb');
		$categories = $category_handler->getObjects(new Criteria('pcatdid', $catid), true);
		$in_array = twitterbomb_getCategoryTree([], $categories, -1, $catid);
		$in_array[$catid]=$catid;
		return $in_array;
	}
}

if (!function_exists('twitterbomb_getCategoryTree')) {
	function twitterbomb_getCategoryTree($in_array, $categories, $ownid) {
		$category_handler =& xoops_getModuleHandler('category', 'twitterbomb');
		foreach($categories as $catid => $category) {
			$in_array[$catid] = $catid;
			if ($categoriesb = $category_handler->getObjects(new Criteria('pcatdid', $catid), true)){
				$in_array = twitterbomb_getCategoryTree($in_array, $categoriesb, $ownid);
			}
		}
		return ($in_array);
	}
}

if (!function_exists('twitterbomb_get_rss')) {
	function twitterbomb_get_rss($items, $cid, $catid) {
		$base_matrix_handler=&xoops_getModuleHandler('base_matrix', 'twitterbomb');
		$usernames_handler=&xoops_getModuleHandler('usernames', 'twitterbomb');
		$urls_handler=&xoops_getModuleHandler('urls', 'twitterbomb');
		xoops_load('xoopscache');
		if (!class_exists('XoopsCache')) {
			// XOOPS 2.4 Compliance
			xoops_load('cache');
			if (!class_exists('XoopsCache')) {
				include_once XOOPS_ROOT_PATH.'/class/cache/xoopscache.php';
			}
		}
		$ret = XoopsCache::read('tweetbomb_bomb_'.md5($cid.$catid));
		while($looped<$items) {
			$sentence = $base_matrix_handler->getSentence($cid, $catid);
			$username = $usernames_handler->getUser($cid, $catid);
			$sourceuser = $usernames_handler->getSourceUser($cid, $catid, $sentence);
			$url = $urls_handler->getUrl($cid, $catid);
			$c = count($ret) + 1;
			$mtr=mt_rand($GLOBALS['twitterbombModuleConfig']['odds_lower'],$GLOBALS['twitterbombModuleConfig']['odds_higher']);
			$ret[$c]['title'] = (is_object($sourceuser)?'@'.$sourceuser->getVar('screen_name').' ':'').(strlen($username)>0&&($mtr<=$GLOBALS['twitterbombModuleConfig']['odds_minimum']||$mtr>=$GLOBALS['twitterbombModuleConfig']['odds_maximum'])?'@'.$username.' ':'').str_replace('#@', '@', str_replace('#(', '(#', str_replace('##', '#', twitterbomb_TweetString(htmlspecialchars_decode($sentence), $GLOBALS['twitterbombModuleConfig']['aggregate'], $GLOBALS['twitterbombModuleConfig']['wordlength']))));	  
			$ret[$c]['link'] = XOOPS_URL.'/modules/twitterbomb/go.php?cid='.$cid.'&catid='.$catid.'&uri='.urlencode( sprintf($url, urlencode(str_replace(['#', '@'], '', $sentence))));
			$ret[$c]['description'] = (is_object($sourceuser)?'@'.$sourceuser->getVar('screen_name').' ':'').(strlen($username)>0&&($mtr<=$GLOBALS['twitterbombModuleConfig']['odds_minimum']||$mtr>=$GLOBALS['twitterbombModuleConfig']['odds_maximum'])?'@'.$username.' ':'').htmlspecialchars_decode($sentence);
			if (0 != strlen($ret[$c]['title'])) {
    			$log_handler=xoops_getModuleHandler('log', 'twitterbomb');
    			$log = $log_handler->create();
    			$log->setVar('cid', $cid);
    			$log->setVar('catid', $catid);
    			$log->setVar('provider', 'bomb');
    			$log->setVar('url', $ret[$c]['link']);
    			$log->setVar('tweet', substr($ret[$c]['title'],0,139));
    			$log->setVar('tags', twitterbomb_ExtractTags($ret[$c]['title']));
    			$ret[$c]['lid'] = $log_handler->insert($log, true);
    			$ret[$c]['link'] = twitterbomb_shortenurl(XOOPS_URL.'/modules/twitterbomb/go.php?cid='.$cid.'&lid='.$ret[$c]['lid'].'&catid='.$catid.'&uri='.urlencode( sprintf($url, urlencode(str_replace(['#', '@'], '', $sentence)))));
    			if ($GLOBALS['twitterbombModuleConfig']['tags']) {
    				$tag_handler = xoops_getModuleHandler('tag', 'tag');
					$tag_handler->updateByItem($log->getVar('tags'), $ret[$c]['lid'], $GLOBALS['twitterbombModule']->getVar('dirname'), $catid);
    			}
    		}
    		$c++;
			$looped++;
		}
		if (count($ret)>$GLOBALS['twitterbombModuleConfig']['items']) {
			foreach($ret as $key => $value) {
				if (count($ret)>$GLOBALS['twitterbombModuleConfig']['items'])
					unset($ret[$key]);
			}
		}			
		return $ret;
	}
}

if (!function_exists('twitterbomb_get_scheduler_rss')) {
	function twitterbomb_get_scheduler_rss($items, $cid, $catid) {
		$scheduler_handler=&xoops_getModuleHandler('scheduler', 'twitterbomb');
		$urls_handler=&xoops_getModuleHandler('urls', 'twitterbomb');
		$usernames_handler=&xoops_getModuleHandler('usernames', 'twitterbomb');
		xoops_load('xoopscache');
		if (!class_exists('XoopsCache')) {
			// XOOPS 2.4 Compliance
			xoops_load('cache');
			if (!class_exists('XoopsCache')) {
				include_once XOOPS_ROOT_PATH.'/class/cache/xoopscache.php';
			}
		}
		$ret = XoopsCache::read('tweetbomb_scheduler_'.md5($cid.$catid));
		while($looped<$items) {
			$sentence = $scheduler_handler->getTweet($cid, $catid, 0, 0);
			if (is_array($sentence)&&count($ret)<$items) {
				$sourceuser = $usernames_handler->getSourceUser($cid, $catid, $sentence['tweet']);
				$url = $urls_handler->getUrl($cid, $catid);
				$ret[$c]['title'] = (is_object($sourceuser)?'@'.$sourceuser->getVar('screen_name').' ':'').str_replace('#@', '@', str_replace('#(', '(#', str_replace('##', '#', twitterbomb_TweetString(htmlspecialchars_decode($sentence['tweet']), $GLOBALS['twitterbombModuleConfig']['scheduler_aggregate'], $GLOBALS['twitterbombModuleConfig']['scheduler_wordlength']))));	  
				$ret[$c]['link'] = XOOPS_URL.'/modules/twitterbomb/go.php?sid='.$sentence['sid'].'&cid='.$cid.'&catid='.$catid.'&uri='.urlencode( sprintf($url, urlencode(str_replace(['#', '@'], '', $sentence['tweet']))));
				$ret[$c]['description'] = htmlspecialchars_decode((is_object($sourceuser)?'@'.$sourceuser->getVar('screen_name').' ':'').$sentence['tweet']);
				$ret[$c]['sid'] = $sentence['sid'];
				if (0 != strlen($ret[$c]['title'])) {
					$log_handler=xoops_getModuleHandler('log', 'twitterbomb');
	    			$log = $log_handler->create();
	    			$log->setVar('provider', 'scheduler');
	    			$log->setVar('cid', $cid);
	    			$log->setVar('catid', $catid);
	    			$log->setVar('sid', $ret[$c]['sid']);
	    			$log->setVar('url', $ret[$c]['link']);
	    			$log->setVar('tweet', substr($ret[$c]['title'],0,139));
	    			$log->setVar('tags', twitterbomb_ExtractTags($ret[$c]['title']));
	    			$ret[$c]['lid'] = $log_handler->insert($log, true);
	    			$ret[$c]['link'] = twitterbomb_shortenurl(XOOPS_URL.'/modules/twitterbomb/go.php?sid='.$sentence['sid'].'&lid='.$ret[$c]['lid'].'&cid='.$cid.'&catid='.$catid.'&uri='.urlencode( sprintf($url, urlencode(str_replace(['#', '@'], '', $sentence['tweet'])))));
	    			if ($GLOBALS['twitterbombModuleConfig']['tags']) {
						$tag_handler = xoops_getModuleHandler('tag', 'tag');
						$tag_handler->updateByItem($log->getVar('tags'), $ret[$c]['lid'], $GLOBALS['twitterbombModule']->getVar('dirname'), $catid);
	    			}
		    	}
				$c++;
			}
			$looped++;
		}
		if (is_array($ret))
			if (count($ret)>$GLOBALS['twitterbombModuleConfig']['scheduler_items']) {
				foreach($ret as $key => $value) {
					if (count($ret)>$GLOBALS['twitterbombModuleConfig']['scheduler_items'])
						unset($ret[$key]);
				}
			}	
		return is_array($ret)?$ret: [];
	}
}

if (!function_exists('twitterbomb_get_retweet_rss')) {
	function twitterbomb_get_retweet_rss($items, $cid, $catid) {
		$campaign_handler=&xoops_getModuleHandler('campaign', 'twitterbomb');
		$retweet_handler=&xoops_getModuleHandler('retweet', 'twitterbomb');
		$urls_handler=&xoops_getModuleHandler('urls', 'twitterbomb');
		$usernames_handler=&xoops_getModuleHandler('usernames', 'twitterbomb');
		$log_handler=&xoops_getModuleHandler('log', 'twitterbomb');
		$oauth_handler=&xoops_getModuleHandler('oauth', 'twitterbomb');

		xoops_load('xoopscache');
		if (!class_exists('XoopsCache')) {
			// XOOPS 2.4 Compliance
			xoops_load('cache');
			if (!class_exists('XoopsCache')) {
				include_once XOOPS_ROOT_PATH.'/class/cache/xoopscache.php';
			}
		}
		
		$oauth = $oauth_handler->getRootOauth(true);
		if (!is_object($oauth))
			return [];
		
		$campaign = $campaign_handler->get($cid);
		if (!is_object($campaign))
			return [];
		
		$item = 0;
		$ret = XoopsCache::read('tweetbomb_scheduler_'.md5($cid.$catid));
		$itemsttl = count($ret);
		while($looped<$items) {
			$search = $retweet_handler->doSearchForTweet($cid, $catid, $campaign->getVar('rids'));
			if (is_array($search)) {
				foreach ($search as $rid => $results) {
					foreach($results as $id => $tweet) {
						if (is_array($tweet)&&item<$items) {
							$log_handler=xoops_getModuleHandler('log', 'twitterbomb');
			    			$log = $log_handler->create();
			    			$log->setVar('provider', 'retweet');
			    			$log->setVar('cid', $cid);
			    			$log->setVar('catid', $catid);
			    			$log->setVar('alias', '@'.$tweet['from_user']);
			    			$log->setVar('rid', $rid);
			    			$log->setVar('id', $id);
			    			$log->setVar('url', '');
			    			$log->setVar('tweet', substr($tweet['text'],0,140));
			    			$log->setVar('tags', twitterbomb_ExtractTags($tweet['text']));
							$log = $log_handler->get($lid = $log_handler->insert($log, true));
					   		if ($retweet = $oauth->sendRetweet($id, true)) {
					   			$retweet_handler->setReweeted($rid);
						   		if ($GLOBALS['twitterbombModuleConfig']['tags']) {
									$tag_handler = xoops_getModuleHandler('tag', 'tag');
									$tag_handler->updateByItem(twitterbomb_ExtractTags($tweet['text']), $lid, $GLOBALS['twitterbombModule']->getVar('dirname'), $catid);
				    			}
				    			$url = $urls_handler->getUrl($cid, $catid);
				    			$link = XOOPS_URL.'/modules/twitterbomb/go.php?rid='.$rid.'&cid='.$cid.'&lid='.$lid.'&catid='.$catid.'&uri='.urlencode( sprintf($url, urlencode(str_replace(['#', '@'], '', $tweet['text']))));
					   			
				    			$criteria = new Criteria('`screen_name`', $tweet['from_user']);
				    			if (0 == $usernames_handler->getCount($criteria)) {
				    				$username = $usernames_handler->create();
				    				$username->setVar('screen_name', $tweet['from_user']);
				    				$username->setVar('type' , 'bomb');
				    				$tid = $usernames_handler->insert($username,  true);
				    			} else {
				    				$usernames = $usernames_handler->getObjects($criteria, false);
				    				if (is_object($usernames[0]))
				    					$tid = $usernames[0]->getVar('tid');
				    				else 
				    					$tid = 0;
				    			}
				    											    			
				    			$log->setVar('tweet', substr($retweet['text'],0,140));
				    			$log->setVar('url', $link);
				    			$log->setVar('tid', $tid);
				    			$log_handler->insert($log, true);

					   	   		$ret[]['title']                  = $retweet['text'];
                                $ret[count($ret)]['link']        = $link;
                                $ret[count($ret)]['description'] = htmlspecialchars_decode($retweet['text']);
                                $ret[count($ret)]['lid']         = $lid;
                                $ret[count($ret)]['rid']         = $rid;
								$item++;
					   		} else {
					   			@$log_handler->delete($log, true);
					   		}
			    		}
					}
				}
			}
			$looped++;
		}
		if (is_array($ret))
			if (count($ret)>$GLOBALS['twitterbombModuleConfig']['retweet_items']) {
				foreach($ret as $key => $value) {
					if (count($ret)>$GLOBALS['twitterbombModuleConfig']['retweet_items'])
						unset($ret[$key]);
				}
			}	
		return is_array($ret)?$ret: [];
	}
}

if (!function_exists('twitterbomb_ExtractTags')) {
	function twitterbomb_ExtractTags($tweet='', $as_array = false, $seperator=', ') {
		$ret = [];
		foreach(explode(' ', $tweet) as $node) {
    		if (in_array(substr($node, 0, 1), ['@', '#'])) {
    			$ret[ucfirst(substr($node, 1, strlen($node)-1))] = ucfirst(substr($node, 1, strlen($node)-1)); 
    		}
    	}
		if (true == $as_array)
			return $ret;
		else 
			return implode($seperator, $ret);
				    	
	}
}
	 
if (!function_exists('twitterbomb_TweetString')) {
	function twitterbomb_TweetString($title, $doit=false, $wordlen=4) {
		if (true == $doit) {
			$title_array = explode(' ', $title);
			$title = '';
			foreach($title_array as $item) {
				if (strlen($item)>$wordlen && ('#' != substr($item, 0, 1) && '@' != substr($item, 0, 1)))
					$title .= ' #'.$item;
				else 
					$title .= ' '.$item;
			}
		}
		return trim($title);
	}
}

// Sometimes this function needs altering
if (!function_exists('twitterbomb_checkmirc_log_line')) {
	function twitterbomb_checkmirc_log_line($line) {
		$parts = explode(' ', $line);
		if ($parts[0]== $parts[count($parts)]) {
			return null;
		}
		if ('Session' == $parts[0]) {
			return null;
		}
		return $line;
	}
}

if (!function_exists('tweetbomb_getFilterElement')) {
	function tweetbomb_getFilterElement($filter, $field, $sort='created', $op = '', $fct = '') {
		$components = tweetbomb_getFilterURLComponents($filter, $field, $sort);
		include_once('formobjects.twitterbomb.php');
		switch ($field) {
			case 'urlid':
				$ele = new TwitterBombFormSelectUrls('', 'filter_'.$field.'', $components['value']);
		    	$ele->setExtra('onchange="window.open(\''.$_SERVER['PHP_SELF'].'?'.$components['extra'].'&filter='.$components['filter'].(!empty($components['filter'])?'|':'').$field.',\'+this.options[this.selectedIndex].value'.(!empty($components['operator'])?'+\','.$components['operator'].'\'':'').',\'_self\')"');
		    	break;
			case 'rcid':
				$ele = new TwitterBombFormSelectCampaigns('', 'filter_'.$field.'', $components['value'], 1, false, true, 'bomb');
		    	$ele->setExtra('onchange="window.open(\''.$_SERVER['PHP_SELF'].'?'.$components['extra'].'&filter='.$components['filter'].(!empty($components['filter'])?'|':'').$field.',\'+this.options[this.selectedIndex].value'.(!empty($components['operator'])?'+\','.$components['operator'].'\'':'').',\'_self\')"');
		    	break;
			case 'cid':
				$ele = new TwitterBombFormSelectCampaigns('', 'filter_'.$field.'', $components['value']);
		    	$ele->setExtra('onchange="window.open(\''.$_SERVER['PHP_SELF'].'?'.$components['extra'].'&filter='.$components['filter'].(!empty($components['filter'])?'|':'').$field.',\'+this.options[this.selectedIndex].value'.(!empty($components['operator'])?'+\','.$components['operator'].'\'':'').',\'_self\')"');
		    	break;
		    case 'pcatdid':	
			case 'catid':
				$ele = new TwitterBombFormSelectCategories('', 'filter_'.$field.'', $components['value']);
		    	$ele->setExtra('onchange="window.open(\''.$_SERVER['PHP_SELF'].'?'.$components['extra'].'&filter='.$components['filter'].(!empty($components['filter'])?'|':'').$field.',\'+this.options[this.selectedIndex].value'.(!empty($components['operator'])?'+\','.$components['operator'].'\'':'').',\'_self\')"');
		    	break;
	    	case 'mode':
				$ele = new TwitterBombFormSelectMode('', 'filter_'.$field.'', $components['value']);
		    	$ele->setExtra('onchange="window.open(\''.$_SERVER['PHP_SELF'].'?'.$components['extra'].'&filter='.$components['filter'].(!empty($components['filter'])?'|':'').$field.',\'+this.options[this.selectedIndex].value'.(!empty($components['operator'])?'+\','.$components['operator'].'\'':'').',\'_self\')"');
		    	break;
	    	case 'provider':
		    case 'type':
				 if ('retweet' == $op) {
					$ele = new TwitterBombFormSelectRetweetType('', 'filter_'.$field.'', $components['value'], 1, false, true);
			    	$ele->setExtra('onchange="window.open(\''.$_SERVER['PHP_SELF'].'?'.$components['extra'].'&filter='.$components['filter'].(!empty($components['filter'])?'|':'').$field.',\'+this.options[this.selectedIndex].value'.(!empty($components['operator'])?'+\','.$components['operator'].'\'':'').',\'_self\')"');
		    	} elseif ('log' == $op) {
					$ele = new TwitterBombFormSelectLogType('', 'filter_'.$field.'', $components['value'], 1, false, true);
			    	$ele->setExtra('onchange="window.open(\''.$_SERVER['PHP_SELF'].'?'.$components['extra'].'&filter='.$components['filter'].(!empty($components['filter'])?'|':'').$field.',\'+this.options[this.selectedIndex].value'.(!empty($components['operator'])?'+\','.$components['operator'].'\'':'').',\'_self\')"');
		    	} else {
					$ele = new TwitterBombFormSelectType('', 'filter_'.$field.'', $components['value'], 1, false, true);
			    	$ele->setExtra('onchange="window.open(\''.$_SERVER['PHP_SELF'].'?'.$components['extra'].'&filter='.$components['filter'].(!empty($components['filter'])?'|':'').$field.',\'+this.options[this.selectedIndex].value'.(!empty($components['operator'])?'+\','.$components['operator'].'\'':'').',\'_self\')"');
		    	}
		    	break;
		    case 'measurement':
				$ele = new TwitterBombFormSelectMeasurement('', 'filter_'.$field.'', $components['value'], 1, false, true);
		    	$ele->setExtra('onchange="window.open(\''.$_SERVER['PHP_SELF'].'?'.$components['extra'].'&filter='.$components['filter'].(!empty($components['filter'])?'|':'').$field.',\'+this.options[this.selectedIndex].value'.(!empty($components['operator'])?'+\','.$components['operator'].'\'':'').',\'_self\')"');
		    	break;
		    case 'language':
				$ele = new TwitterBombFormSelectLanguage('', 'filter_'.$field.'', $components['value'], 1, false, true);
		    	$ele->setExtra('onchange="window.open(\''.$_SERVER['PHP_SELF'].'?'.$components['extra'].'&filter='.$components['filter'].(!empty($components['filter'])?'|':'').$field.',\'+this.options[this.selectedIndex].value'.(!empty($components['operator'])?'+\','.$components['operator'].'\'':'').',\'_self\')"');
		    	break;
		    case 'base':
		    case 'base1':
		    case 'base2':
			case 'base3':
			case 'base4':
			case 'base5':
			case 'base6':
			case 'base7':						    	
				$ele = new TwitterBombFormSelectBase('', 'filter_'.$field.'', $components['value']);
		    	$ele->setExtra('onchange="window.open(\''.$_SERVER['PHP_SELF'].'?'.$components['extra'].'&filter='.$components['filter'].(!empty($components['filter'])?'|':'').$field.',\'+this.options[this.selectedIndex].value'.(!empty($components['operator'])?'+\','.$components['operator'].'\'':'').',\'_self\')"');
		    	break;
		    case 'description':
		    case 'pre':
		    case 'alias':
		    case 'screen_name':
		    case 'source_nick':	
		    case 'keyword':
		    case 'tweet':
		    case 'name':
		    case 'search':
		    case 'skip':
		    case 'longitude':
			case 'latitude':
			case 'replies':
			case 'mentions':
			case 'user':
			case 'reply':
			case 'keywords':		    	
		    	$ele = new XoopsFormElementTray('');
				$ele->addElement(new XoopsFormText('', 'filter_'.$field.'', 11, 40, $components['value']));
				$button = new XoopsFormButton('', 'button_'.$field.'', '[+]');
		    	$button->setExtra('onclick="window.open(\''.$_SERVER['PHP_SELF'].'?'.$components['extra'].'&filter='.$components['filter'].(!empty($components['filter'])?'|':'').$field.',\'+$(\'#filter_'.$field.'\').val()'.(!empty($components['operator'])?'+\','.$components['operator'].'\'':'').',\'_self\')"');
		    	$ele->addElement($button);
		    	break;
		    case 'radius':
		    	$measurement = tweetbomb_getFilterURLComponents($components['filter'], 'measurement', $sort);
				$ele = new XoopsFormElementTray('');
				$ele->addElement(new XoopsFormText('', 'filter_radius', 8, 40, $components['value']));
				$ele->addElement(new TwitterBombFormSelectMeasurement('', 'filter_measurement', $measurement['value']));
				$button = new XoopsFormButton('', 'button_'.$field.'', '[+]');
		    	$button->setExtra('onclick="window.open(\''.$_SERVER['PHP_SELF'].'?'.$measurement['extra'].'&filter='.$measurement['filter'].(!empty($measurement['filter'])?'|':'').'radius'.',\'+$(\'#filter_radius\').val()'.(!empty($components['operator'])?'+\','.$components['operator'].'\'':'').'+\'|'.'measurement'.',\'+$(\'#filter_measurement'.'\').val()'.(!empty($measurement['operator'])?'+\','.$measurement['operator'].'\'':'').',\'_self\')"');
		    	$ele->addElement($button);		    	
		}
		return $ele ?? false;
	}
}

if (!function_exists('tweetbomb_getFilterURLComponents')) {
	function tweetbomb_getFilterURLComponents($filter, $field, $sort='created') {
		$parts = explode('|', $filter);
		$ret = [];
		$value = '';
    	foreach($parts as $part) {
    		$var = explode(',', $part);
    		if (count($var)>1) {
	    		if ($var[0]==$field) {
	    			$ele_value = $var[1];
	    			if (isset($var[2]))
	    				$operator = $var[2];
	    		} elseif (1 != $var[0]) {
	    			$ret[] = implode(',', $var);
	    		}
    		}
    	}
    	$pagenav = [];
    	$pagenav['op'] = $_REQUEST['op'] ?? 'campaign';
		$pagenav['fct'] = $_REQUEST['fct'] ?? 'list';
		$pagenav['limit'] = !empty($_REQUEST['limit'])? (int)$_REQUEST['limit'] :30;
		$pagenav['start'] = 0;
		$pagenav['order'] = !empty($_REQUEST['order'])?$_REQUEST['order']:'DESC';
		$pagenav['sort'] = !empty($_REQUEST['sort'])?''.$_REQUEST['sort'].'':$sort;
    	$retb = [];
		foreach($pagenav as $key=>$value) {
			$retb[] = "$key=$value";
		}
		return ['value' =>($ele_value ?? ''), 'field' =>($field ?? ''), 'operator' =>($operator ?? ''), 'filter' =>implode('|', (isset($ret) && is_array($ret)?$ret: [])), 'extra' =>implode('&', (isset($retb) && is_array($retb)?$retb: []))];
	}
}
?>
