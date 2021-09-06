<?php

if (!defined('XOOPS_ROOT_PATH')) {
	exit();
}

require_once($GLOBALS['xoops']->path('/modules/twitterbomb/include/twitteroauth.php'));
require_once($GLOBALS['xoops']->path('/modules/twitterbomb/include/functions.php'));

class TwitterbombOauth extends XoopsObject
{
    public $_connection = null;
    public $_handler    = null;
    public $_modConfig  = [];

    public function __construct($fid = null)
    {
        $this->initVar('oid', XOBJ_DTYPE_INT, null, false);
        $this->initVar('cids', XOBJ_DTYPE_ARRAY, [], false);
        $this->initVar('catids', XOBJ_DTYPE_ARRAY, [], false);
        $this->initVar('mode', XOBJ_DTYPE_ENUM, 'valid', false, false, false, ['valid', 'invalid', 'expired', 'disabled', 'other']);
        $this->initVar('consumer_key', XOBJ_DTYPE_TXTBOX, CONSUMER_KEY, true, 255);
        $this->initVar('consumer_secret', XOBJ_DTYPE_TXTBOX, CONSUMER_SECRET, true, 255);
        $this->initVar('oauth_token', XOBJ_DTYPE_TXTBOX, null, true, 255);
        $this->initVar('oauth_token_secret', XOBJ_DTYPE_TXTBOX, null, true, 255);
        $this->initVar('username', XOBJ_DTYPE_TXTBOX, null, false, 64);
        $this->initVar('id', XOBJ_DTYPE_TXTBOX, 0, true, 255);
        $this->initVar('ip', XOBJ_DTYPE_TXTBOX, null, true, 64);
        $this->initVar('netbios', XOBJ_DTYPE_TXTBOX, null, true, 255);
        $this->initVar('uid', XOBJ_DTYPE_INT, null, false);
        $this->initVar('created', XOBJ_DTYPE_INT, null, false);
        $this->initVar('updated', XOBJ_DTYPE_INT, null, false);
        $this->initVar('actioned', XOBJ_DTYPE_INT, null, false);
        $this->initVar('tweeted', XOBJ_DTYPE_INT, null, false);
        $this->initVar('mentions', XOBJ_DTYPE_INT, null, false);
        $this->initVar('friends', XOBJ_DTYPE_INT, null, false);
        $this->initVar('tweets', XOBJ_DTYPE_INT, null, false);
        $this->initVar('calls', XOBJ_DTYPE_INT, null, false);
        $this->initVar('remaining_hits', XOBJ_DTYPE_INT, null, false);
        $this->initVar('hourly_limit', XOBJ_DTYPE_INT, null, false);
        $this->initVar('api_resets', XOBJ_DTYPE_INT, null, false);
        $this->initVar('reset', XOBJ_DTYPE_INT, null, false);

        $this->_handler   = xoops_getModuleHandler('oauth', 'twitterbomb');
        $this->_modConfig = $this->_handler->_modConfig;
    }

    private function reset()
    {
        $sql = 'UPDATE ' . $GLOBALS['xoopsDB']->prefix('twitterbomb_oauth') . ' SET `calls` = 0, `reset` = "' . time() . '", `updated` = "' . time() . '" WHERE `oid` = ' . $this->getVar('oid');
        $GLOBALS['xoopsDB']->queryF($sql);
        $this->vars['calls']['value'] = 0;
        $this->vars['reset']['value'] = time();
    }

    private function setLimits($limits)
    {
        $sql = 'UPDATE '
               . $GLOBALS['xoopsDB']->prefix('twitterbomb_oauth')
               . ' SET `remaining_hits` = "'
               . $limits['remaining_hits']
               . '", `hourly_limit` = "'
               . $limits['hourly_limit']
               . '", `api_resets` = "'
               . $limits['reset_time_in_seconds']
               . '", `updated` = "'
               . time()
               . '" WHERE `oid` = '
               . $this->getVar('oid');
        $GLOBALS['xoopsDB']->queryF($sql);
        $this->vars['remaining_hits']['value'] = $limits['remaining_hits'];
        $this->vars['hourly_limit']['value']   = $limits['hourly_limit'];
        $this->vars['api_resets']['value']     = $limit['reset_time_in_seconds'];
    }

    private function increaseCall($amount = 1)
    {
        $sql = 'UPDATE ' . $GLOBALS['xoopsDB']->prefix('twitterbomb_oauth') . ' SET `calls` = `calls` + ' . $amount . ', `updated` = "' . time() . '" WHERE `oid` = ' . $this->getVar('oid');
        $GLOBALS['xoopsDB']->queryF($sql);
        $this->vars['calls']['value'] = $this->vars['calls']['value'] + $amount;
        $this->getRateLimits(false);
    }

    private function increaseTweets($amount = 1)
    {
        $sql = 'UPDATE ' . $GLOBALS['xoopsDB']->prefix('twitterbomb_oauth') . ' SET `tweets` = `tweets` + ' . $amount . ', `tweeted` = "' . time() . '", `updated` = "' . time() . '" WHERE `oid` = ' . $this->getVar('oid');
        $GLOBALS['xoopsDB']->queryF($sql);
        $this->vars['tweets']['value']  = $this->vars['tweets']['value'] + $amount;
        $this->vars['tweeted']['value'] = time();
    }

    private function validate($user)
    {
        $sql = 'UPDATE ' . $GLOBALS['xoopsDB']->prefix('twitterbomb_oauth') . ' SET `mode` = "valid", `username` = "' . $user['screen_name'] . '", `id` = "' . $user['id'] . '", `updated` = "' . time() . '" WHERE `oid` = ' . $this->getVar('oid');
        $GLOBALS['xoopsDB']->queryF($sql);
        $this->vars['mode']['value']     = 'valid';
        $this->vars['username']['value'] = $user['screen_name'];
        $this->vars['id']['value']       = $user['id'];
    }

    public function setFriendsTimer()
    {
        $sql = 'UPDATE ' . $GLOBALS['xoopsDB']->prefix('twitterbomb_oauth') . ' SET `friends` = "' . time() + $this->_modConfig['look_for_friends'] . '", `updated` = "' . time() . '" WHERE `oid` = ' . $this->getVar('oid');
        $GLOBALS['xoopsDB']->queryF($sql);
        $this->vars['friends']['value'] = time() + $this->_modConfig['look_for_friends'];
    }

    public function setMentionsTimer()
    {
        $sql = 'UPDATE ' . $GLOBALS['xoopsDB']->prefix('twitterbomb_oauth') . ' SET `mentions` = "' . time() + $this->_modConfig['look_for_mention'] . '", `updated` = "' . time() . '" WHERE `oid` = ' . $this->getVar('oid');
        $GLOBALS['xoopsDB']->queryF($sql);
        $this->vars['mentions']['value'] = time() + $this->_modConfig['look_for_mention'];
    }

    public function shortenURL($url)
    {
        return twitterbomb_shortenurl($url);
    }

    public function setHandler($handler)
    {
        $this->_handler = $handler;
    }

    public function getConnection($for_tweet = false)
    {
        if (!is_a($this->_connection, 'TwitterOAuth')) {
            $this->_connection = new TwitterOAuth($this->getVar('consumer_key'), $this->getVar('consumer_secret'), $this->getVar('oauth_token'), $this->getVar('oauth_token_secret'));
        }
        $this->validateCredentials($for_tweet);
        $this->getRateLimits($for_tweet);
        return $this->_connection;
    }

    public function createFollow($mixed, $type = 'user_id', $for_tweet = false)
    {
        if (!is_a($this->_connection, 'TwitterOAuth')) {
            @$this->getConnection($for_tweet);
        }

        if (is_a($this->_connection, 'TwitterOAuth') && $this->getVar('remaining_hits') > 0) {
            $follow = twitterbomb_object2array($this->_connection->post('friendships/create', [$type => $mixed, 'follow' => 'true']));
            switch ($this->_connection->http_code) {
                case 200:
                    $this->increaseCall(1);
                    return $follow;
                    break;
                default:
                    return false;
                    break;
            }
        } else {
            return false;
        }
    }

    public function sendTweet($tweet, $url, $for_tweet = false)
    {
        if (!is_a($this->_connection, 'TwitterOAuth')) {
            @$this->getConnection($for_tweet);
        }

        $url = $this->shortenURL($url);

        if (is_a($this->_connection, 'TwitterOAuth') && $this->getVar('remaining_hits') > 0) {
            $tweet = twitterbomb_object2array(
                $this->_connection->post('statuses/update', [
                    'status'     => substr($tweet, 0, (!empty($url) ? 126 : 140)) . ' ' . $url,
                    'wrap_links' => 'true',
                ])
            );

            if (isset($tweet['error']) && !empty($tweet['error'])) {
                return false;
            }

            switch ($this->_connection->http_code) {
                case 200:
                    $this->increaseTweets(1);
                    $this->increaseCall(1);
                    return $tweet['id_str'];
                    break;
                default:
                    return false;
                    break;
            }
        } else {
            return false;
        }
    }

    public function sendReply($tweet, $url, $in_reply_to_status_id = 0, $for_tweet = false)
    {
        if (!is_a($this->_connection, 'TwitterOAuth')) {
            @$this->getConnection($for_tweet);
        }

        $url = $this->shortenURL($url);

        if (is_a($this->_connection, 'TwitterOAuth') && $this->getVar('remaining_hits') > 0) {
            $tweet = twitterbomb_object2array(
                $this->_connection->post('statuses/update', [
                    'status'                => substr($tweet, 0, (!empty($url) ? 126 : 140)) . ' ' . $url,
                    'wrap_links'            => 'true',
                    'in_reply_to_status_id' => $in_reply_to_status_id,
                ])
            );

            if (isset($tweet['error']) && !empty($tweet['error'])) {
                return false;
            }

            switch ($this->_connection->http_code) {
                case 200:
                    $this->increaseTweets(1);
                    $this->increaseCall(1);
                    return $tweet['id_str'];
                    break;
                default:
                    return false;
                    break;
            }
        } else {
            return false;
        }
    }

    public function sendRetweet($id, $for_tweet = false)
    {
        if (!is_a($this->_connection, 'TwitterOAuth')) {
            @$this->getConnection($for_tweet);
        }

        if (is_a($this->_connection, 'TwitterOAuth') && $this->getVar('remaining_hits') > 0) {
            $tweet = twitterbomb_object2array($this->_connection->post('statuses/retweet/' . $id));

            if (isset($tweet['error']) && !empty($tweet['error'])) {
                return false;
            }

            switch ($this->_connection->http_code) {
                case 200:
                    $this->increaseTweets(1);
                    $this->increaseCall(1);
                    return $tweet;
                    break;
                default:
                    return false;
                    break;
            }
        } else {
            return false;
        }
    }

    public function getRateLimits($for_tweet = false)
    {
        if (!is_a($this->_connection, 'TwitterOAuth')) {
            @$this->getConnection($for_tweet);
        }

        xoops_load('XoopsCache');
        if ($limits = XoopsCache::read('twitterbomb_rate_limits_api')) {
            if (is_a($this->_connection, 'TwitterOAuth')) {
                $limits = twitterbomb_object2array($this->_connection->get('account/rate_limit_status', []));
                switch ($this->_connection->http_code) {
                    case 200:
                        if ($this->getVar('api_resets') <> $limits['reset_time_in_seconds']) {
                            $this->reset();
                        }
                        XoopsCache::write('twitterbomb_rate_limits_api', $limits, mt_rand(2, 20));
                        $this->setLimits($limits);
                        return $limits;
                        break;
                    default:
                        return false;
                        break;
                }
            } else {
                return false;
            }
        } else {
            return $limits;
        }
    }

    public function validateCredentials($for_tweet = false)
    {
        if (!is_a($this->_connection, 'TwitterOAuth')) {
            @$this->getConnection($for_tweet);
        }

        if (is_a($this->_connection, 'TwitterOAuth') && $this->getVar('remaining_hits') > 0) {
            $user = twitterbomb_object2array($this->_connection->get('account/verify_credentials', ['include_entities' => 'true']));
            switch ($this->_connection->http_code) {
                case 200:
                    $this->increaseCall(1);
                    $this->validate($user);
                    return $user;
                    break;
                default:
                    $this->setVar('mode', 'invalid');
                    if (is_a($this->_handler, 'TwitterbombOauthHandler')) {
                        @$this->_handler->insert($this, true);
                    }
                    return false;
                    break;
            }
        } else {
            return false;
        }
    }

    public function getUser($mixed = '', $type = 'user_id', $for_tweet = false)
    {
        if (!is_a($this->_connection, 'TwitterOAuth')) {
            @$this->getConnection($for_tweet);
        }

        if (is_a($this->_connection, 'TwitterOAuth') && $this->getVar('remaining_hits') > 0) {
            $user = twitterbomb_object2array($this->_connection->get('users/show', [$type => $mixed, 'include_entities' => 'true']));
            switch ($this->_connection->http_code) {
                case 200:
                    $this->increaseCall(1);
                    return $user;
                    break;
                default:
                    return false;
                    break;
            }
        } else {
            return false;
        }
    }

    public function getUsers($mixed = '', $type = 'user_id', $for_tweet = false)
    {
        if (!is_a($this->_connection, 'TwitterOAuth')) {
            @$this->getConnection($for_tweet);
        }

        if (is_array($mixed)) {
            $c = 1;
            foreach ($mixed as $key => $value) {
                $i++;
                $ret[$c] .= $value . (sizeof($mixed) != $key || $i < 100 ? ',' : '');
                if (100 == $i) {
                    $i = 0;
                    $c++;
                }
            }
        } else {
            $c     = 1;
            $mixed = explode(',', $mixed);
            foreach ($mixed as $key => $value) {
                $i++;
                $ret[$c] .= $value . (sizeof($mixed) != $key || $i < 100 ? ',' : '');
                if (100 == $i) {
                    $i = 0;
                    $c++;
                }
            }
        }
        foreach ($ret as $key => $mixed) {
            $GLOBALS['execution_time'] = $GLOBALS['execution_time'] + 30;
            set_time_limit($GLOBALS['execution_time']);
            if (is_a($this->_connection, 'TwitterOAuth') && $this->getVar('remaining_hits') > 0) {
                $users[$key] = twitterbomb_object2array($this->_connection->get('users/lookup', [$type => $mixed]));
                switch ($this->_connection->http_code) {
                    case 200:
                        $this->increaseCall(1);
                        foreach ($users[$key] as $user) {
                            $output[('screen_name' == $type ? $user['screen_name'] : $user['id'])] = $user;
                        }
                        break;
                }
            } else {
                return $output;
            }
        }
        return $output;
    }

    public function getFriends($mixed, $type = 'user_id', $for_tweet = false)
    {
        if (!is_a($this->_connection, 'TwitterOAuth')) {
            @$this->getConnection($for_tweet);
        }

        $ids = [];
        if (is_a($this->_connection, 'TwitterOAuth')) {
            $cursor = -1;
            while ($cursor > $friends['next_cursor'] && $this->getVar('remaining_hits') > 0) {
                $friends = twitterbomb_object2array($this->_connection->get('friends/ids', [$type => $mixed, 'cursor' => $cursor]));
                switch ($this->_connection->http_code) {
                    case 200:
                        $this->increaseCall(1);
                        if ($friends['next_cursor'] > $cursor) {
                            $cursor = $friends['next_cursor'];
                        }
                        $ids = array_merge($ids, $friends['ids']);
                        break;
                    default:
                        $friends['next_cursor'] = -1;
                        $cursor                 = 0;
                        break;
                }
            }
        } else {
            return $ids;
        }
        return $ids;
    }

    public function getMentions($for_tweet = false, $page = 1)
    {
        if (!is_a($this->_connection, 'TwitterOAuth')) {
            @$this->getConnection($for_tweet);
        }

        $mentions = [];
        if (is_a($this->_connection, 'TwitterOAuth')) {
            while (0 != $page && $this->getVar('remaining_hits') > 0) {
                $mention = twitterbomb_object2array($this->_connection->get('statuses/mentions', ['count' => 200, 'include_entities ' => 'true', 'contributor_details' => 'true']));
                switch ($this->_connection->http_code) {
                    case 200:
                        $this->increaseCall(1);
                        return $mention;
                        break;
                    default:
                        return false;
                        break;
                }
            }
        } else {
            return false;
        }
        return false;
    }

    public function getTrend($type = '', $for_tweet = false)
    {
        if (!is_a($this->_connection, 'TwitterOAuth')) {
            $this->getConnection($for_tweet);
        }

        if (is_a($this->_connection, 'TwitterOAuth') && $this->getVar('remaining_hits') > 0) {
            $trends = twitterbomb_object2array($this->_connection->get('trends' . (!empty($type) ? '/' . $type : ''), []));
            switch ($this->_connection->http_code) {
                case 200:
                    $this->increaseCall(1);
                    return $trends;
                    break;
                default:
                    return [];
                    break;
            }
        } else {
            return [];
        }
    }

    public function getForm()
    {
        return tweetbomb_oauth_get_form($this);
    }

    public function toArray()
    {
        $ret           = parent::toArray();
        $ele           = [];
        $ele['id']     = new XoopsFormHidden('id[' . $ret['oid'] . ']', $this->getVar('oid'));
        $ele['cids']   = new TwitterBombFormSelectCampaigns('', $ret['oid'] . '[cids]', $this->getVar('cids'), 6, true);
        $ele['catids'] = new TwitterBombFormSelectCategories('', $ret['oid'] . '[catids]', $this->getVar('catids'), 6, true);
        $ele['type']   = new TwitterBombFormSelectOAuthMode('', $ret['oid'] . '[mode]', $this->getVar('mode'));
        if ($ret['uid'] > 0) {
            $member_handler = xoops_getHandler('member');
            $user           = $member_handler->getUser($ret['uid']);
            $ele['uid']     = new XoopsFormLabel('', '<a href="' . XOOPS_URL . '/userinfo.php?uid=' . $ret['uid'] . '">' . $user->getVar('uname') . '</a>');
        } else {
            $ele['uid'] = new XoopsFormLabel('', _MI_TWEETBOMB_ANONYMOUS);
        }
        if ($ret['created'] > 0) {
            $ele['created'] = new XoopsFormLabel('', date(_DATESTRING, $ret['created']));
        } else {
            $ele['created'] = new XoopsFormLabel('', '');
        }
        if ($ret['actioned'] > 0) {
            $ele['actioned'] = new XoopsFormLabel('', date(_DATESTRING, $ret['actioned']));
        } else {
            $ele['actioned'] = new XoopsFormLabel('', '');
        }
        if ($ret['updated'] > 0) {
            $ele['updated'] = new XoopsFormLabel('', date(_DATESTRING, $ret['updated']));
        } else {
            $ele['updated'] = new XoopsFormLabel('', '');
        }
        if ($ret['tweeted'] > 0) {
            $ele['tweeted'] = new XoopsFormLabel('', date(_DATESTRING, $ret['tweeted']));
        } else {
            $ele['tweeted'] = new XoopsFormLabel('', '');
        }
        foreach ($ele as $key => $obj) {
            $ret['form'][$key] = $ele[$key]->render();
        }
        return $ret;
    }

    public function runInsertPlugin()
    {
        include_once($GLOBALS['xoops']->path('/modules/twitterbomb/plugins/' . $this->getVar('mode') . '.php'));

        switch ($this->getVar('mode')) {
            case 'valid':
            case 'invalid':
            case 'expired':
            case 'disabled':
            case 'other':
                $func = ucfirst($this->getVar('mode')) . 'InsertHook';
                break;
            default:
                return false;
                break;
        }

        if (function_exists($func)) {
            return @$func($this);
        }
        return $this->getVar('oid');
    }

    public function runGetPlugin($for_tweet = false)
    {
        include_once($GLOBALS['xoops']->path('/modules/twitterbomb/plugins/' . $this->getVar('mode') . '.php'));

        switch ($this->getVar('mode')) {
            case 'valid':
            case 'invalid':
            case 'expired':
            case 'disabled':
            case 'other':
                $func = ucfirst($this->getVar('mode')) . 'GetHook';
                break;
            default:
                return false;
                break;
        }

        if (function_exists($func)) {
            return @$func($this, $for_tweet);
        }
        return $this;
    }
}

/**
 * XOOPS Spider handler class.
 * This class is responsible for providing data access mechanisms to the data source
* of XOOPS user class objects.
*
* @author  Simon Roberts <simon@xoops.org>
* @package kernel
*/
class TwitterbombOauthHandler extends XoopsPersistableObjectHandler
{
    public $_connection = null;
    public $_user       = [];
    public $_root_oauth = null;
    public $_modConfig  = [];
    public $_mod        = null;

    public function __construct($db)
    {
        parent::__construct($db, 'twitterbomb_oauth', 'TwitterbombOauth', 'oid', 'username');

        xoops_load('xoopscache');
        if (!class_exists('XoopsCache')) {
            // XOOPS 2.4 Compliance
            xoops_load('cache');
            if (!class_exists('XoopsCache')) {
                include_once XOOPS_ROOT_PATH . '/class/cache/xoopscache.php';
            }
        }

        $this->_user = twitterbomb_getuser_id();

        $module_handler   = xoops_getHandler('module');
        $config_handler   = xoops_getHandler('config');
        $this->_mod       = $module_handler->getByDirname('twitterbomb');
        $this->_modConfig = $config_handler->getConfigList($this->_mod->getVar('mid'));
    }

    public function insert($obj, $force = true)
    {
        if ($obj->isNew()) {
            $obj->setVar('created', time());
            if (is_object($GLOBALS['xoopsUser'])) {
                $obj->setVar('uid', $GLOBALS['xoopsUser']->getVar('uid'));
            }
        } else {
            $obj->setVar('updated', time());
        }

        $run_plugin = false;
        if (true == $obj->vars['mode']['changed']) {
            $obj->setVar('actioned', time());
            $run_plugin = true;
        }

        if (true == $run_plugin) {
            $id  = parent::insert($obj, $force);
            $obj = parent::get($id);
            if (is_object($obj)) {
                $ret = $obj->runInsertPlugin();
                return (0 != $ret) ? $ret : $id;
            } else {
                return $id;
            }
        } else {
            return parent::insert($obj, $force);
        }
    }

    public function get($id, $fields = '*', $for_tweet = false)
    {
        $obj = parent::get($id, $fields);
        if (is_object($obj)) {
            //$obj->setHandler($this);
            return @$obj->runGetPlugin($for_tweet);
        }
    }

    public function getObjects($criteria, $id_as_key = false, $as_object = true, $for_tweet = false)
    {
        $objs = parent::getObjects($criteria, $id_as_key, $as_object);
        foreach ($objs as $id => $obj) {
            if (is_object($obj)) {
                //$objs[$id]->setHandler($this);
                $objs[$id] = @$obj->runGetPlugin($for_tweet);
            }
        }
        return $objs;
    }

    public function getRootOauth($for_tweet = false)
    {
        if (!empty($this->_modConfig['consumer_key']) && !empty($this->_modConfig['consumer_secret'])
            && !empty($this->_modConfig['access_token'])
            && !empty($this->_modConfig['access_token_secret'])) {
            $criteria = new CriteriaCompo(new Criteria('consumer_key', $this->_modConfig['consumer_key']));
            $criteria->add(new Criteria('consumer_secret', $this->_modConfig['consumer_secret']));
            $criteria->add(new Criteria('oauth_token', $this->_modConfig['access_token']));
            $criteria->add(new Criteria('oauth_token_secret', $this->_modConfig['access_token_secret']));

            if (parent::getCount($criteria) > 0) {
                $oauths = parent::getObjects($criteria, false);
                if (is_object($oauths[0])) {
                    //$oauths[0]->setHandler($this);
                    return @$oauths[0]->runGetPlugin($for_tweet);
                }
            }

            $oauth = parent::create();
            $oauth->setVar('uid', $this->_user['uid']);
            $oauth->setVar('ip', $this->_user['ip']);
            $oauth->setVar('netbios', $this->_user['netbios']);
            $oauth->setVar('oauth_token', $this->_modConfig['access_token']);
            $oauth->setVar('oauth_token_secret', $this->_modConfig['access_token_secret']);
            $oauth->setVar('consumer_key', $this->_modConfig['consumer_key']);
            $oauth->setVar('consumer_secret', $this->_modConfig['consumer_secret']);
            $oauth->setVar('username', $this->_modConfig['root_tweeter']);
            $oauth->setVar('mode', 'valid');
            return $this->get($this->insert($oauth, true));
        }
    }

    public function getFilterCriteria($filter)
    {
        $parts    = explode('|', $filter);
        $criteria = new CriteriaCompo();
        foreach ($parts as $part) {
            $var = explode(',', $part);
            if (!empty($var[1]) && !is_numeric($var[0])) {
                $object = $this->create();
                if (XOBJ_DTYPE_TXTBOX == $object->vars[$var[0]]['data_type']
                    || XOBJ_DTYPE_TXTAREA == $object->vars[$var[0]]['data_type']) {
                    $criteria->add(new Criteria('`' . $var[0] . '`', '%' . $var[1] . '%', ($var[2] ?? 'LIKE')));
                } elseif (XOBJ_DTYPE_INT == $object->vars[$var[0]]['data_type']
                          || XOBJ_DTYPE_DECIMAL == $object->vars[$var[0]]['data_type']
                          || XOBJ_DTYPE_FLOAT == $object->vars[$var[0]]['data_type']) {
                    $criteria->add(new Criteria('`' . $var[0] . '`', $var[1], ($var[2] ?? '=')));
                } elseif (XOBJ_DTYPE_ENUM == $object->vars[$var[0]]['data_type']) {
                    $criteria->add(new Criteria('`' . $var[0] . '`', $var[1], ($var[2] ?? '=')));
                } elseif (XOBJ_DTYPE_ARRAY == $object->vars[$var[0]]['data_type']) {
                    $criteria->add(new Criteria('`' . $var[0] . '`', '%"' . $var[1] . '";%', ($var[2] ?? 'LIKE')));
                }
            } elseif (!empty($var[1]) && is_numeric($var[0])) {
                $criteria->add(new Criteria($var[0], $var[1]));
            }
        }
        return $criteria;
    }

    public function getFilterForm($filter, $field, $sort = 'created')
    {
        $ele = tweetbomb_getFilterElement($filter, $field, $sort);
        if (is_object($ele)) {
            return $ele->render();
        } else {
            return '&nbsp;';
        }
    }

    public function getRootConnection($for_tweet = false)
    {
        if (!is_a($this->_root_oauth, 'TwitterbombOauth')) {
            $this->_root_oauth = $this->getRootOauth($for_tweet);
        }
        if (is_object($this->_root_oauth)) {
            return $this->_connection = $this->_root_oauth->getConnection($for_tweet);
        }
        return false;
    }

    public function getTrend($type = '', $for_tweet = false)
    {
        if (!is_a($this->_connection, 'TwitterOAuth')) {
            $this->getRootConnection($for_tweet);
        }
        return $this->_root_oauth->getTrend($type, $for_tweet);
    }

    public function getTempAuthentication()
    {
        $this->_connection = new TwitterOAuth($this->_modConfig['consumer_key'], $this->_modConfig['consumer_secret']);
        /* Get temporary credentials. */
        $request_token = $this->_connection->getRequestToken($this->_modConfig['callback_url']);
        /* Save temporary credentials to file cache. */
        XoopsCache::write('twitterbomb_tmp_cred_' . $this->_user['uid'] . '_' . $this->_user['md5'], $request_token);

        /* If last connection failed don't display authorization link. */
        switch ($this->_connection->http_code) {
            case 200:
                /* Build authorize URL and redirect user to Twitter. */ $url = $this->_connection->getAuthorizeURL($request_token['oauth_token']);
                header('Location: ' . $url);
                break;
            default:
                /* Show notification if something went wrong. */ xoops_loadLanguage('errors', 'twitterbomb');
                require_once($GLOBALS['xoops']->path('/header.php'));
                xoops_error(_ERR_TWEETBOMB_COULDNT_CONNECT, _ERR_TWEETBOMB_COULDNT_CONNECT_TITLE);
                require_once($GLOBALS['xoops']->path('/footer.php'));
                XoopsCache::delete('twitterbomb_tmp_cred_' . $this->_user['uid'] . '_' . $this->_user['md5']);
                exit(0);
        }
    }

    public function getAuthentication($input)
    {
        if ($request_token = XoopsCache::read('twitterbomb_tmp_cred_' . $this->_user['uid'] . '_' . $this->_user['md5'])) {
            /* If the oauth_token is old redirect to the connect page. */
            if (isset($input['oauth_token']) && $request_token['oauth_token'] !== $input['oauth_token']) {
                xoops_loadLanguage('errors', 'twitterbomb');
                redirect_header(XOOPS_URL . '/modules/twitterbomb/', 10, _ERR_TWEETBOMB_TOKEN_OLDTOKEN);
                exit(0);
            }

            /* Create TwitteroAuth object with app key/secret and token key/secret from default phase */
            $this->_connection = new TwitterOAuth($this->_modConfig['consumer_key'], $this->_modConfig['consumer_secret'], $request_token['oauth_token'], $request_token['oauth_token_secret']);

            /* Request access tokens from twitter */
            $access_token = $this->_connection->getAccessToken($input['oauth_verifier']);

            /* If HTTP response is 200 continue otherwise send to connect page to retry */
            if (200 == $this->_connection->http_code) {
                $oauth = parent::create();
                $oauth->setVar('uid', $this->_user['uid']);
                $oauth->setVar('ip', $this->_user['ip']);
                $oauth->setVar('netbios', $this->_user['netbios']);
                $oauth->setVar('oauth_token', $access_token->key);
                $oauth->setVar('oauth_token_secret', $access_token->secret);
                $oauth->setVar('consumer_key', $this->_modConfig['consumer_key']);
                $oauth->setVar('consumer_secret', $this->_modConfig['consumer_secret']);
                $oauth->setVar('mode', 'valid');
                /* The user has been verified and the access tokens can be saved for future use */
                $oid = $this->insert($oauth, true);
                XoopsCache::delete('twitterbomb_tmp_cred_' . $this->_user['uid'] . '_' . $this->_user['md5']);
                redirect_header(XOOPS_URL . '/modules/twitterbomb/trail.php?oid=' . $oid, 10, _ERR_TWEETBOMB_CHOOSEYOUR_TRAIL);
            } else {
                /* Show notification if something went wrong. */
                xoops_loadLanguage('errors', 'twitterbomb');
                require_once($GLOBALS['xoops']->path('/header.php'));
                xoops_error(_ERR_TWEETBOMB_COULDNT_CONNECT, _ERR_TWEETBOMB_COULDNT_CONNECT_TITLE);
                require_once($GLOBALS['xoops']->path('/footer.php'));
            }
        } else {
            xoops_loadLanguage('errors', 'twitterbomb');
            require_once($GLOBALS['xoops']->path('/header.php'));
            xoops_error(_ERR_TWEETBOMB_CACHE_EMPTY, _ERR_TWEETBOMB_CACHE_EMPTY_TITLE);
            require_once($GLOBALS['xoops']->path('/footer.php'));
        }
        exit(0);
    }
}

?>
