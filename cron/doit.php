<?php
/**
 * @file
 * Take the user when they return from Twitter. Get access tokens.
 * Verify credentials and redirect to based on response from Twitter.
 */
if (!defined('NLB')) {
	if (!isset($_SERVER['HTTP_HOST']))
		define('NLB', "\n");
	else 
		define('NLB', '<br/>');
}


include_once('../../../mainfile.php');
include_once(XOOPS_ROOT_PATH.'/modules/twitterbomb/include/functions.php');

error_reporting(E_ALL);
ini_set('log_errors', '1');
ini_set('error_log', XOOPS_ROOT_PATH . '/uploads/cron.errors.log.' . md5(XOOPS_ROOT_PATH) . '.txt');
ini_set('display_errors', '1');


$module_handler = xoops_getHandler('module');
$config_handler = xoops_getHandler('config');
$GLOBALS['twitterbombModule'] = $module_handler->getByDirname('twitterbomb');
$GLOBALS['twitterbombModuleConfig'] = $config_handler->getConfigList($GLOBALS['twitterbombModule']->getVar('mid'));
if (!isset($GLOBALS['cron_run_for']))
	$GLOBALS['cron_run_for'] = ceil($GLOBALS['twitterbombModuleConfig']['interval_of_cron'] / (($GLOBALS['twitterbombModuleConfig']['cron_follow']?1:0)+($GLOBALS['twitterbombModuleConfig']['cron_gather']?1:0)+($GLOBALS['twitterbombModuleConfig']['cron_tweet']?1:0)+($GLOBALS['twitterbombModuleConfig']['cron_retweet']?1:0)));
$GLOBALS['cron_start'] = microtime(true);

if ($GLOBALS['twitterbombModuleConfig']['cron_tweet']||$GLOBALS['twitterbombModuleConfig']['cron_retweet']) {
	echo 'Tweeter Cron Started: '.date('Y-m-d D H:i:s', time()).NLB;
	xoops_load('xoopscache');
	if (!class_exists('XoopsCache')) {
		// XOOPS 2.4 Compliance
		xoops_load('cache');
		if (!class_exists('XoopsCache')) {
			include_once XOOPS_ROOT_PATH.'/class/cache/xoopscache.php';
		}
	}
	
	$scheduler_handler=&xoops_getModuleHandler('scheduler', 'twitterbomb');
	$base_matrix_handler=&xoops_getModuleHandler('base_matrix', 'twitterbomb');
	$usernames_handler=&xoops_getModuleHandler('usernames', 'twitterbomb');
	$urls_handler=&xoops_getModuleHandler('urls', 'twitterbomb');
	$campaign_handler = xoops_getModuleHandler('campaign', 'twitterbomb');
	$retweet_handler=&xoops_getModuleHandler('retweet', 'twitterbomb');
	
	$oauth_handler = xoops_getModuleHandler('oauth', 'twitterbomb');
	@$oauth = $oauth_handler->getRootOauth(true);
	if (!$cids = XoopsCache::read('twitterbomb_cids_cron')) {
		$criteria_a = new CriteriaCompo(new Criteria('timed', '0'));
		$criteria_b = new CriteriaCompo(new Criteria('timed', '1'));
		$criteria_b->add(new Criteria('start', time(), '<'));
		$criteria_b->add(new Criteria('end', time(), '>'));
		$criteria = new CriteriaCompo($criteria_a);
		$criteria->add($criteria_b, 'OR');
	} else {
		XoopsCache::delete('twitterbomb_cids_cron');
		$criteria_a = new CriteriaCompo(new Criteria('timed', '0'));
		$criteria_a->add(new Criteria('cid', '('.implode(',', $cids).')', 'IN'));
		$criteria_b = new CriteriaCompo(new Criteria('timed', '1'));
		$criteria_b->add(new Criteria('start', time(), '<'));
		$criteria_b->add(new Criteria('end', time(), '>'));
		$criteria_b->add(new Criteria('cid', '('.implode(',', $cids).')', 'IN'));
		$criteria = new CriteriaCompo($criteria_a);
		$criteria->add($criteria_b, 'OR');
	}
	if ($GLOBALS['twitterbombModuleConfig']['cron_retweet']&&$GLOBALS['twitterbombModuleConfig']['cron_tweet']) {
		$criteria->add(new Criteria('`type`', '("scheduler", "bomber", "retweet")', 'IN'));
	} elseif ($GLOBALS['twitterbombModuleConfig']['cron_tweet']) {
		$criteria->add(new Criteria('`type`', '("scheduler", "bomber")', 'IN'));
	} elseif ($GLOBALS['twitterbombModuleConfig']['cron_retweet']) {
		$criteria->add(new Criteria('`type`', '("retweet")', 'IN'));		
	}
	$criteria->setOrder('DESC');
	$criteria->setSort('RAND()');
	$campaigns = $campaign_handler->getObjects($criteria, true);
	$campaignCount = $campaign_handler->getCount($criteria);
	
	while ($c<=(($GLOBALS['twitterbombModuleConfig']['tweets_per_session']+$GLOBALS['twitterbombModuleConfig']['retweets_per_session']))&&$loopsb<=(($GLOBALS['twitterbombModuleConfig']['tweets_per_session']+$GLOBALS['twitterbombModuleConfig']['retweets_per_session']))*1.75&&$campaignCount>0) {
		if (microtime(true)-$GLOBALS['cron_start']>$GLOBALS['cron_run_for']*(($GLOBALS['twitterbombModuleConfig']['cron_tweet']?1:0)+($GLOBALS['twitterbombModuleConfig']['cron_retweet']?1:0))) {
			return endtweeter($cids);
		}
		$loopsb++;
		$cids= [];
		foreach($campaigns as $cid => $campaign) {
			$cids[$cid] = $cid;	
		}
		foreach($campaigns as $cid => $campaign) {
			unset ($cids[$cid]);
			if (microtime(true)-$GLOBALS['cron_start']>$GLOBALS['cron_run_for']*(($GLOBALS['twitterbombModuleConfig']['cron_tweet']?1:0)+($GLOBALS['twitterbombModuleConfig']['cron_retweet']?1:0))) {
				return endtweeter($cids);
			}
			$GLOBALS['execution_time'] = $GLOBALS['execution_time'] + 45;
			set_time_limit($GLOBALS['execution_time']);
			$catid = $campaign->getVar('catid');
			if ($c<=(($GLOBALS['twitterbombModuleConfig']['tweets_per_session']+$GLOBALS['twitterbombModuleConfig']['retweets_per_session']))) {
				if (microtime(true)-$GLOBALS['cron_start']>$GLOBALS['cron_run_for']*(($GLOBALS['twitterbombModuleConfig']['cron_tweet']?1:0)+($GLOBALS['twitterbombModuleConfig']['cron_retweet']?1:0))) {
					return endtweeter($cids);
				}
				$campaign->setCron();
				switch($campaign->getVar('type')) {
					case 'bomb':
						if (microtime(true)-$GLOBALS['cron_start']>$GLOBALS['cron_run_for']*(($GLOBALS['twitterbombModuleConfig']['cron_tweet']?1:0)+($GLOBALS['twitterbombModuleConfig']['cron_retweet']?1:0))) {
							return endtweeter($cids);
						}
						$ret = XoopsCache::read('tweetbomb_channel_last');
						if (!isset($ret['last']))
							$ret = ['last' => time() - (60 * 25)];
						if (isset($ret['last']))
							if ($ret['last']+(60*20)<time()) {
								$item=0;
								$items = 0;
								$loop=0;
								$ret = XoopsCache::read('tweetbomb_'.$campaign->getVar('type').'_'.md5($cid.$catid));
								while((((($GLOBALS['twitterbombModuleConfig']['tweets_per_session']+$GLOBALS['twitterbombModuleConfig']['retweets_per_session']))/$campaignCount)*(($GLOBALS['twitterbombModuleConfig']['items']+$GLOBALS['twitterbombModuleConfig']['scheduler_items']+$GLOBALS['twitterbombModuleConfig']['retweet_items'])/$GLOBALS['twitterbombModuleConfig']['items']))>$items&&$c<=(($GLOBALS['twitterbombModuleConfig']['tweets_per_session']+$GLOBALS['twitterbombModuleConfig']['retweets_per_session']))&&(((($GLOBALS['twitterbombModuleConfig']['tweets_per_session']+$GLOBALS['twitterbombModuleConfig']['retweets_per_session']))/$campaignCount)*(($GLOBALS['twitterbombModuleConfig']['items']+$GLOBALS['twitterbombModuleConfig']['scheduler_items']+$GLOBALS['twitterbombModuleConfig']['retweet_items'])/$GLOBALS['twitterbombModuleConfig']['items']))*2>$loop) {
									if (microtime(true)-$GLOBALS['cron_start']>$GLOBALS['cron_run_for']*(($GLOBALS['twitterbombModuleConfig']['cron_tweet']?1:0)+($GLOBALS['twitterbombModuleConfig']['cron_retweet']?1:0))) {
										return endtweeter($cids);
									}
									$GLOBALS['execution_time'] = $GLOBALS['execution_time'] + 15;
									set_time_limit($GLOBALS['execution_time']);
									$sentence = $base_matrix_handler->getSentence($cid, $catid);
									$username = $usernames_handler->getUser($cid, $catid);
									$url = $urls_handler->getUrl($cid, $catid);
									$sourceuser = $usernames_handler->getSourceUser($cid, $catid, $sentence);
									if (strlen($sentence)>0) {
										$mtr=mt_rand($GLOBALS['twitterbombModuleConfig']['odds_lower'],$GLOBALS['twitterbombModuleConfig']['odds_higher']);
										$tweet = (is_object($sourceuser)?'@'.$sourceuser->getVar('screen_name').' ':'').(strlen($username)>0&&($mtr<=$GLOBALS['twitterbombModuleConfig']['odds_minimum']||$mtr>=$GLOBALS['twitterbombModuleConfig']['odds_maximum'])?'#'.$username.' ':'').str_replace('#@', '@', str_replace('#(', '(#', str_replace('##', '#', twitterbomb_TweetString(htmlspecialchars_decode($sentence), $GLOBALS['twitterbombModuleConfig']['aggregate'], $GLOBALS['twitterbombModuleConfig']['wordlength']))));
										$log_handler=xoops_getModuleHandler('log', 'twitterbomb');
								   		$log = $log_handler->create();
								   		$log->setVar('cid', $cid);
								   		$log->setVar('catid', $catid);
								   		$log->setVar('provider', 'bomb');
								   		$log->setVar('url', $ret[$c]['link']);
								   		$log->setVar('tweet', substr($tweet,0,139));
								  		$log->setVar('tags', twitterbomb_ExtractTags($tweet));
								   		$lid = $log_handler->insert($log, true);
								   		$log = $log_handler->get($lid, true);
								   		$link = XOOPS_URL.'/modules/twitterbomb/go.php?cid='.$cid.'&lid='.$lid.'&catid='.$catid.'&uri='.urlencode( sprintf($url, urlencode(str_replace(['#', '@'], '', $sentence))));
								   		$link = twitterbomb_shortenurl($link);
								   		$log->setVar('url', $link);
								   		$log = $log_handler->get($lid = $log_handler->insert($log, true));
								   		if ($id = $oauth->sendTweet($tweet, $link, true)) {
								   			echo 'Tweet Sent: '.$tweet.' - '.$link.NLB;
									   		if ($GLOBALS['twitterbombModuleConfig']['tags']) {
									   			$tag_handler = xoops_getModuleHandler('tag', 'tag');
												$tag_handler->updateByItem(twitterbomb_ExtractTags($tweet), $lid, $GLOBALS['twitterbombModule']->getVar('dirname'), $catid);
									   		}
									   		$log->setVar('id', $id);
									   		$lid = $log_handler->insert($log, true);
									   		$ret[]['title'] = $tweet;
                                            $ret[count($ret)]['link'] = $link;
                                            $ret[count($ret)]['description'] = (strlen($username) > 0 && ($mtr <= $GLOBALS['twitterbombModuleConfig']['odds_minimum'] || $mtr >= $GLOBALS['twitterbombModuleConfig']['odds_maximum'])? '@' . $username . ' ':'') . htmlspecialchars_decode($sentence);
                                            $ret[count($ret)]['lid'] = $lid;
											$item++;
								   		} else {
								   			echo 'Tweet Failed: '.$tweet.' - '.$link.NLB;
								   			$log_handler->delete($log, true);
								   		}
								   		$c++;
								   		$items++;
									}
									$loop++;
								}
								if (count($ret)>$GLOBALS['twitterbombModuleConfig']['items']) {
									foreach($ret as $key => $value) {
										if (count($ret)>$GLOBALS['twitterbombModuleConfig']['items'])
											unset($ret[$key]);
									}
								}	
								XoopsCache::write('tweetbomb_'.$campaign->getVar('type').'_'.md5($cid.$catid), $ret, $GLOBALS['twitterbombModuleConfig']['interval_of_cron']+$GLOBALS['twitterbombModuleConfig']['cache']);
							}
						break;
					case 'scheduler':
						if (microtime(true)-$GLOBALS['cron_start']>$GLOBALS['cron_run_for']*(($GLOBALS['twitterbombModuleConfig']['cron_tweet']?1:0)+($GLOBALS['twitterbombModuleConfig']['cron_retweet']?1:0))) {
							return endtweeter($cids);
						}
						$items=0;
						$loop=0;
						$item=0;
						$ret = XoopsCache::read('tweetbomb_channel_last');
						if (!isset($ret['last']))
							$ret = ['last' => time() - (60 * 25)];
						if (isset($ret['last']))
							if ($ret['last']+(60*20)<time()) {
								$ret = XoopsCache::read('tweetbomb_'.$campaign->getVar('type').'_'.md5($cid.$catid));
								while((((($GLOBALS['twitterbombModuleConfig']['tweets_per_session']+$GLOBALS['twitterbombModuleConfig']['retweets_per_session']))/$campaignCount)*(($GLOBALS['twitterbombModuleConfig']['items']+$GLOBALS['twitterbombModuleConfig']['scheduler_items']+$GLOBALS['twitterbombModuleConfig']['retweet_items'])/$GLOBALS['twitterbombModuleConfig']['scheduler_items']))>$items&&$c<=(($GLOBALS['twitterbombModuleConfig']['tweets_per_session']+$GLOBALS['twitterbombModuleConfig']['retweets_per_session']))&&(((($GLOBALS['twitterbombModuleConfig']['tweets_per_session']+$GLOBALS['twitterbombModuleConfig']['retweets_per_session']))/$campaignCount)*(($GLOBALS['twitterbombModuleConfig']['items']+$GLOBALS['twitterbombModuleConfig']['scheduler_items']+$GLOBALS['twitterbombModuleConfig']['retweet_items'])/$GLOBALS['twitterbombModuleConfig']['scheduler_items']))*2>$loop) {
									$GLOBALS['execution_time'] = $GLOBALS['execution_time'] + 15;
									set_time_limit($GLOBALS['execution_time']);
									if (microtime(true)-$GLOBALS['cron_start']>$GLOBALS['cron_run_for']*(($GLOBALS['twitterbombModuleConfig']['cron_tweet']?1:0)+($GLOBALS['twitterbombModuleConfig']['cron_retweet']?1:0))) {
										return endtweeter($cids);
									}
									$sentence = $scheduler_handler->getTweet($cid, $catid, 0, 0);
									if (is_array($sentence)) {
										$sourceuser = $usernames_handler->getSourceUser($cid, $catid, $sentence['tweet']);
										$url = $urls_handler->getUrl($cid, $catid);
										$tweet = (is_object($sourceuser)?'@'.$sourceuser->getVar('screen_name').' ':'').str_replace('#@', '@', str_replace('#(', '(#', str_replace('##', '#', $sentence['tweet'])));	  
										$link = XOOPS_URL.'/modules/twitterbomb/go.php?sid='.$sentence['sid'].'&cid='.$cid.'&catid='.$catid.'&uri='.urlencode( sprintf($url, urlencode(str_replace(['#', '@'], '', $tweet))));
										if (0 != strlen($tweet)) {
											$log_handler=xoops_getModuleHandler('log', 'twitterbomb');
							    			$log = $log_handler->create();
							    			$log->setVar('provider', 'scheduler');
							    			$log->setVar('cid', $cid);
							    			$log->setVar('catid', $catid);
							    			$log->setVar('sid', $ret[$c]['sid']);
							    			$log->setVar('url', $link);
							    			$log->setVar('tweet', substr($tweet,0,139));
							    			$log->setVar('tags', twitterbomb_ExtractTags($tweet));
							    			$lid = $log_handler->insert($log, true);
											$log = $log_handler->get($lid, true);
									   		$link = XOOPS_URL.'/modules/twitterbomb/go.php?sid='.$sentence['sid'].'&cid='.$cid.'&lid='.$lid.'&catid='.$catid.'&uri='.urlencode( sprintf($url, urlencode(str_replace(['#', '@'], '', $sentence['tweet']))));
									   		$link = twitterbomb_shortenurl($link);
									   		$log->setVar('url', $link);
									   		$log = $log_handler->get($lid = $log_handler->insert($log, true));
									   		if ($id = $oauth->sendTweet($tweet, $link, true)) {
									   			echo 'Tweet Sent: '.$tweet.' - '.$link.NLB;
										   		if ($GLOBALS['twitterbombModuleConfig']['tags']) {
													$tag_handler = xoops_getModuleHandler('tag', 'tag');
													$tag_handler->updateByItem(twitterbomb_ExtractTags($tweet), $lid, $GLOBALS['twitterbombModule']->getVar('dirname'), $catid);
								    			}
								    			$log->setVar('id', $id);
									   			$lid = $log_handler->insert($log, true);
									   	   		$ret[]['title'] = $tweet;
                                                $ret[count($ret)]['link'] = $link;
                                                $ret[count($ret)]['description'] = htmlspecialchars_decode($sentence['tweet']);
                                                $ret[count($ret)]['lid'] = $lid;
                                                $ret[count($ret)]['sid'] = $sentence['sid'];
												$item++;
									   		} else {
									   			echo 'Tweet Failed: '.$tweet.' - '.$link.NLB;
									   			$scheduler = $scheduler_handler->get($sentence['sid']);
									   			if (is_object($scheduler)) {
									   				$scheduler->setVar('when', 0);
									   				$scheduler_handler->insert($scheduler);
									   			}
									   			@$log_handler->delete($log, true);
									   		}
							    			$c++;
									   		$items++;
							    		}
									}
									$loop++;
								}
								if (count($ret)>$GLOBALS['twitterbombModuleConfig']['scheduler_items']) {
									foreach($ret as $key => $value) {
										if (count($ret)>$GLOBALS['twitterbombModuleConfig']['scheduler_items'])
											unset($ret[$key]);
									}
								}						
								XoopsCache::write('tweetbomb_'.$campaign->getVar('type').'_'.md5($cid.$catid), $ret, $GLOBALS['twitterbombModuleConfig']['interval_of_cron']+$GLOBALS['twitterbombModuleConfig']['scheduler_cache']);
							}
						break;
					case 'retweet':
						$items=0;
						$loop=0;
						$item=0;
						$ret = XoopsCache::read('tweetbomb_'.$campaign->getVar('type').'_'.md5($cid.$catid));
						while((((($GLOBALS['twitterbombModuleConfig']['tweets_per_session']+$GLOBALS['twitterbombModuleConfig']['retweets_per_session']))/$campaignCount)*(($GLOBALS['twitterbombModuleConfig']['items']+$GLOBALS['twitterbombModuleConfig']['scheduler_items']+$GLOBALS['twitterbombModuleConfig']['retweet_items'])/$GLOBALS['twitterbombModuleConfig']['retweet_items']))>$items&&$c<=(($GLOBALS['twitterbombModuleConfig']['tweets_per_session']+$GLOBALS['twitterbombModuleConfig']['retweets_per_session']))&&(((($GLOBALS['twitterbombModuleConfig']['tweets_per_session']+$GLOBALS['twitterbombModuleConfig']['retweets_per_session']))/$campaignCount)*(($GLOBALS['twitterbombModuleConfig']['items']+$GLOBALS['twitterbombModuleConfig']['scheduler_items']+$GLOBALS['twitterbombModuleConfig']['retweet_items'])/$GLOBALS['twitterbombModuleConfig']['retweet_items']))*2>$loop) {
							if (microtime(true)-$GLOBALS['cron_start']>$GLOBALS['cron_run_for']*(($GLOBALS['twitterbombModuleConfig']['cron_tweet']?1:0)+($GLOBALS['twitterbombModuleConfig']['cron_retweet']?1:0))) {
								return endtweeter($cids);
							}
							$GLOBALS['execution_time'] = $GLOBALS['execution_time'] + 30;
							set_time_limit($GLOBALS['execution_time']);
							$search = $retweet_handler->doSearchForTweet($cid, $catid, $campaign->getVar('rids'));
							if (is_array($search)) {
								foreach ($search as $rid => $results) {
									if (microtime(true)-$GLOBALS['cron_start']>$GLOBALS['cron_run_for']*(($GLOBALS['twitterbombModuleConfig']['cron_tweet']?1:0)+($GLOBALS['twitterbombModuleConfig']['cron_retweet']?1:0))) {
										return endtweeter($cids);
									}
									if (!empty($results)) {
										foreach($results as $id => $tweet) {
											if (microtime(true)-$GLOBALS['cron_start']>$GLOBALS['cron_run_for']*(($GLOBALS['twitterbombModuleConfig']['cron_tweet']?1:0)+($GLOBALS['twitterbombModuleConfig']['cron_retweet']?1:0))) {
												return endtweeter($cids);
											}
											$GLOBALS['execution_time'] = $GLOBALS['execution_time'] + 15;
											set_time_limit($GLOBALS['execution_time']);
											if (is_array($tweet)) {
												$log_handler=xoops_getModuleHandler('log', 'twitterbomb');
												$pass=true;
												$criteria = new Criteria('id', $id);
												if (0 == $log_handler->getCount($criteria) && true == $pass) {
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
											   			echo 'Retweet Sent: '.$id.' - '.$tweet['text'].NLB;
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
										    			$c++;
												   		$items++;
											   		} else {
											   			echo 'Retweet Failed(api): '.$id.' - '.$tweet['text'].NLB;
											   			@$log_handler->delete($log, true);
											   		}
												} else {
													if (false == $pass)
														echo 'Retweet Failed (exceptions): ';
													else 
														echo 'Retweet Failed (exists): ';
										   			echo $id.' - '.$tweet['text'].NLB;
												}
											}
							    		}
									}
								}
							}
							$loop++;
						}
						if (count($ret)>$GLOBALS['twitterbombModuleConfig']['retweet_items']) {
							foreach($ret as $key => $value) {
								if (count($ret)>$GLOBALS['twitterbombModuleConfig']['retweet_items'])
									unset($ret[$key]);
							}
						}
						XoopsCache::write('tweetbomb_'.$campaign->getVar('type').'_'.md5($cid.$catid), $ret, $GLOBALS['twitterbombModuleConfig']['interval_of_cron']+$GLOBALS['twitterbombModuleConfig']['retweet_cache']);
						break;						
				}
			} else {
				$cids[$cid] = $cid;
			}
			
		}
		if ($c<=(($GLOBALS['twitterbombModuleConfig']['tweets_per_session']+$GLOBALS['twitterbombModuleConfig']['retweets_per_session']))) {
			if (0 == count($cids)) {
				$criteria_a = new CriteriaCompo(new Criteria('timed', '0'));
				$criteria_b = new CriteriaCompo(new Criteria('timed', '1'));
				$criteria_b->add(new Criteria('start', time(), '<'));
				$criteria_b->add(new Criteria('end', time(), '>'));
				$criteria = new CriteriaCompo($criteria_a);
				$criteria->add($criteria_b, 'OR');
				$criteria->setSort('RAND()');
			} else {
				
				$criteria_a = new CriteriaCompo(new Criteria('timed', '0'));
				$criteria_a->add(new Criteria('cid', '('.implode(',', $cids).')', 'IN'));
				$criteria_b = new CriteriaCompo(new Criteria('timed', '1'));
				$criteria_b->add(new Criteria('start', time(), '<'));
				$criteria_b->add(new Criteria('end', time(), '>'));
				$criteria_b->add(new Criteria('cid', '('.implode(',', $cids).')', 'IN'));
				$criteria = new CriteriaCompo($criteria_a);
				$criteria->add($criteria_b, 'OR');
				$criteria->setSort('RAND()');
			}
			if ($GLOBALS['twitterbombModuleConfig']['cron_retweet']&&$GLOBALS['twitterbombModuleConfig']['cron_tweet']) {
				$criteria->add(new Criteria('`type`', '("scheduler", "bomber", "retweet")', 'IN'));
			} elseif ($GLOBALS['twitterbombModuleConfig']['cron_tweet']) {
				$criteria->add(new Criteria('`type`', '("scheduler", "bomber")', 'IN'));
			} elseif ($GLOBALS['twitterbombModuleConfig']['cron_retweet']) {
				$criteria->add(new Criteria('`type`', '("retweet")', 'IN'));		
			}
			$criteria->setOrder('ASC');
			$criteria->setSort('cron');
			$campaigns = $campaign_handler->getObjects($criteria, true);
			$campaignCount = $campaign_handler->getCount($criteria);
		}
		if (microtime(true)-$GLOBALS['cron_start']>$GLOBALS['cron_run_for']*(($GLOBALS['twitterbombModuleConfig']['cron_tweet']?1:0)+($GLOBALS['twitterbombModuleConfig']['cron_retweet']?1:0))) {
			return endtweeter($cids);
		}
	}
	return endtweeter($cids);
}

function endtweeter($cids) {
	if (0 == count($cids)) {
		XoopsCache::delete('twitterbomb_cids_cron');
	} else {
		XoopsCache::write('twitterbomb_cids_cron', $cids);
	}
	echo 'Tweeter Cron Ended: '.date('Y-m-d D H:i:s', time()).NLB;
}
?>
