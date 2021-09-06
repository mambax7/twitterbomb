<?php

if (!defined('XOOPS_ROOT_PATH')) {
	exit();
}
/**
 * Class for Blue Room TwitterBomb Retweet
 * @author Simon Roberts <simon@xoops.org>
 * @copyright copyright (c) 2009-2003 XOOPS.org
 * @package kernel
 */
class TwitterBombRetweet extends XoopsObject
{
    public function TwitterBombRetweet($id = null)
    {
        $this->initVar('rid', XOBJ_DTYPE_INT, null, false);
        $this->initVar('search', XOBJ_DTYPE_TXTBOX, false, false, 128);
        $this->initVar('skip', XOBJ_DTYPE_TXTBOX, 'RT', false, 128);
        $this->initVar('geocode', XOBJ_DTYPE_INT, null, false);
        $this->initVar('longitude', XOBJ_DTYPE_DECIMAL, null, false);
        $this->initVar('latitude', XOBJ_DTYPE_DECIMAL, null, false);
        $this->initVar('radius', XOBJ_DTYPE_INT, 2, false);
        $this->initVar('measurement', XOBJ_DTYPE_ENUM, 'km', false, false, false, ['mi', 'km']);
        $this->initVar('language', XOBJ_DTYPE_ENUM, 'en', false, false, false, [
            'aa',
            'ab',
            'af',
            'am',
            'ar',
            'as',
            'ay',
            'az',
            'ba',
            'be',
            'bg',
            'bh',
            'bi',
            'bn',
            'bo',
            'br',
            'ca',
            'co',
            'cs',
            'cy',
            'da',
            'de',
            'dz',
            'el',
            'en',
            'eo',
            'es',
            'et',
            'eu',
            'fa',
            'fi',
            'fj',
            'fo',
            'fr',
            'fy',
            'ga',
            'gd',
            'gl',
            'gn',
            'gu',
            'ha',
            'he',
            'hi',
            'hr',
            'hu',
            'hy',
            'ia',
            'id',
            'ie',
            'ik',
            'is',
            'it',
            'iu',
            'ja',
            'jw',
            'ka',
            'kk',
            'kl',
            'km',
            'kn',
            'ko',
            'ks',
            'ku',
            'ky',
            'la',
            'ln',
            'lo',
            'lt',
            'lv',
            'mg',
            'mi',
            'mk',
            'ml',
            'mn',
            'mo',
            'mr',
            'ms',
            'mt',
            'my',
            'na',
            'ne',
            'nl',
            'no',
            'oc',
            'om',
            'or',
            'pa',
            'pl',
            'ps',
            'pt',
            'qu',
            'rm',
            'rn',
            'ro',
            'ru',
            'rw',
            'sa',
            'sd',
            'sg',
            'sh',
            'si',
            'sk',
            'sl',
            'sm',
            'sn',
            'so',
            'sq',
            'sr',
            'ss',
            'st',
            'su',
            'sv',
            'sw',
            'ta',
            'te',
            'tg',
            'th',
            'ti',
            'tk',
            'tl',
            'tn',
            'to',
            'tr',
            'ts',
            'tt',
            'tw',
            'ug',
            'uk',
            'ur',
            'uz',
            'vi',
            'vo',
            'wo',
            'xh',
            'yi',
            'yo',
            'za',
            'zh',
            'zu',
        ]);
        $this->initVar('type', XOBJ_DTYPE_ENUM, 'mixed', false, false, false, ['mixed', 'recent', 'popular']);
        $this->initVar('uid', XOBJ_DTYPE_INT, null, false);
        $this->initVar('retweets', XOBJ_DTYPE_INT, null, false);
        $this->initVar('searched', XOBJ_DTYPE_INT, null, false);
        $this->initVar('created', XOBJ_DTYPE_INT, null, false);
        $this->initVar('updated', XOBJ_DTYPE_TXTBOX, false, false, 500);
        $this->initVar('actioned', XOBJ_DTYPE_INT, null, false);
        $this->initVar('retweeted', XOBJ_DTYPE_INT, null, false);
    }

    public function plusRetweets()
    {
        $sql = "UPDATE " . $GLOBALS['xoopsDB']->prefix('twitterbomb_retweet') . ' SET `retweets` = `retweets` + 1, `retweeted` = "' . time() . '" WHERE `rid` = ' . $this->getVar('rid');
        $GLOBALS['xoopsDB']->queryF($sql);
        $this->var['retweets']['value']  = $this->var['retweets']['value'] + 1;
        $this->var['retweeted']['value'] = time();
    }

    public function getForm()
    {
        return tweetbomb_retweet_get_form($this);
    }

    public function toArray()
    {
        $ret = parent::toArray();
        if ($this->getVar('searched') <> 0) {
            $ret['searched_datetime'] = date(_DATESTRING, $this->getVar('searched'));
        }
        if ($this->getVar('created') <> 0) {
            $ret['created_datetime'] = date(_DATESTRING, $this->getVar('created'));
        }
        if ($this->getVar('updated') <> 0) {
            $ret['updated_datetime'] = date(_DATESTRING, $this->getVar('updated'));
        }
        if ($this->getVar('actioned') <> 0) {
            $ret['actioned_datetime'] = date(_DATESTRING, $this->getVar('actioned'));
        }
        if ($this->getVar('retweeted') <> 0) {
            $ret['retweeted_datetime'] = date(_DATESTRING, $this->getVar('retweeted'));
        }

        $ele                = [];
        $ele['id']          = new XoopsFormHidden('id[' . $ret['rid'] . ']', $this->getVar('rid'));
        $ele['search']      = new XoopsFormText('', $ret['rid'] . '[search]', 26, 64, $this->getVar('search'));
        $ele['skip']        = new XoopsFormText('', $ret['rid'] . '[skip]', 26, 64, $this->getVar('skip'));
        $ele['geocode']     = new XoopsFormRadioYN('', $ret['rid'] . '[geocode]', $this->getVar('geocode'));
        $ele['longitude']   = new XoopsFormText('', $ret['rid'] . '[longitude]', 10, 24, $this->getVar('longitude'));
        $ele['latitude']    = new XoopsFormText('', $ret['rid'] . '[latitude]', 10, 24, $this->getVar('latitude'));
        $ele['radius']      = new XoopsFormText('', $ret['rid'] . '[radius]', 8, 24, $this->getVar('radius'));
        $ele['measurement'] = new TwitterbombFormSelectMeasurement('', $ret['rid'] . '[measurement]', $this->getVar('measurement'));
        $ele['language']    = new TwitterbombFormSelectLanguage('', $ret['rid'] . '[language]', $this->getVar('language'));
        $ele['type']        = new TwitterbombFormSelectRetweetType('', $ret['rid'] . '[type]', $this->getVar('type'));

        if ($ret['uid'] > 0) {
            $member_handler = xoops_gethandler('member');
            $user           = $member_handler->getUser($ret['uid']);
            $ele['uid']     = new XoopsFormLabel('', '<a href="' . XOOPS_URL . '/userinfo.php?uid=' . $ret['uid'] . '">' . $user->getVar('uname') . '</a>');
        } else {
            $ele['uid'] = new XoopsFormLabel('', _MI_TWEETBOMB_ANONYMOUS);
        }

        foreach ($ele as $key => $obj) {
            $ret['form'][$key] = $ele[$key]->render();
        }

        $ret['geocode']     = ($this->getVar('geocode') == true ? _YES : _NO);
        $ret['measurement'] = defined('_MI_TWEETBOMB_MEASUREMENT_' . strtoupper($this->getVar('measurement'))) ? constant('_MI_TWEETBOMB_MEASUREMENT_' . strtoupper($this->getVar('measurement'))) : $this->getVar('measurement');
        $ret['language']    = defined('_MI_TWEETBOMB_LANGUAGE_' . strtoupper($this->getVar('language'))) ? constant('_MI_TWEETBOMB_LANGUAGE_' . strtoupper($this->getVar('language'))) : $this->getVar('language');
        $ret['type']        = defined('_MI_TWEETBOMB_RETWEET_TYPE_' . strtoupper($this->getVar('type'))) ? constant('_MI_TWEETBOMB_RETWEET_TYPE_' . strtoupper($this->getVar('type'))) : $this->getVar('type');

        foreach ($ret as $key => $value) {
            $ret[str_replace('-', '_', $key)] = $value;
        }

        return $ret;
    }

    public function runPrePlugin()
    {
        include_once($GLOBALS['xoops']->path('/modules/twitterbomb/plugins/' . $this->getVar('type') . '.php'));

        switch ($this->getVar('type')) {
            case 'mixed':
            case 'recent':
            case 'poopular':
                $func = ucfirst($this->getVar('type')) . ucfirst($this->getVar('language')) . 'RetweetPreHook';
                break;
            default:
                return $this;
                break;
        }

        if (function_exists($func)) {
            return @$func($this);
        }
        return $this;
    }

    public function runPostPlugin($rid)
    {
        include_once($GLOBALS['xoops']->path('/modules/twitterbomb/plugins/' . $this->getVar('type') . '.php'));

        switch ($this->getVar('type')) {
            case 'mixed':
            case 'recent':
            case 'poopular':
                $func = ucfirst($this->getVar('type')) . ucfirst($this->getVar('language')) . 'RetweetPostHook';
                break;
            default:
                return $rid;
                break;
        }

        if (function_exists($func)) {
            return @$func($this, $rid);
        }
        return $rid;
    }

    public function runGetPlugin()
    {
        include_once($GLOBALS['xoops']->path('/modules/twitterbomb/plugins/' . $this->getVar('type') . '.php'));

        switch ($this->getVar('type')) {
            case 'mixed':
            case 'recent':
            case 'poopular':
                $func = ucfirst($this->getVar('type')) . ucfirst($this->getVar('language')) . 'RetweetGetHook';
                break;
            default:
                return $this;
                break;
        }

        if (function_exists($func)) {
            $object = @$func($this);
            return is_object($object) ? $object : $this;
        }
        return $this;
    }
}

/**
 * XOOPS TwitterBomb Retweet handler class.
* This class is responsible for providing data access mechanisms to the data source
* of XOOPS user class objects.
*
* @author  Simon Roberts <simon@chronolabs.coop>
* @package kernel
*/
class TwitterBombRetweetHandler extends XoopsPersistableObjectHandler
{
    var $_mod       = null;
    var $_modConfig = [];

    public function __construct($db)
    {
        $this->db = $db;
        parent::__construct($db, 'twitterbomb_retweet', 'TwitterBombRetweet', "rid", "search");

        $module_handler   = xoops_gethandler('module');
        $config_handler   = xoops_gethandler('config');
        $this->_mod       = $module_handler->getByDirname('twitterbomb');
        $this->_modConfig = $config_handler->getConfigList($this->_mod->getVar('mid'));
    }

    public function getFilterCriteria($filter)
    {
        $parts    = explode('|', $filter);
        $criteria = new CriteriaCompo();
        foreach ($parts as $part) {
            $var = explode(',', $part);
            if (!empty($var[1]) && !is_numeric($var[0])) {
                $object = $this->create();
                if ($object->vars[$var[0]]['data_type'] == XOBJ_DTYPE_TXTBOX || $object->vars[$var[0]]['data_type'] == XOBJ_DTYPE_TXTAREA) {
                    $criteria->add(new Criteria('`' . $var[0] . '`', '%' . $var[1] . '%', (isset($var[2]) ? $var[2] : 'LIKE')));
                } elseif ($object->vars[$var[0]]['data_type'] == XOBJ_DTYPE_INT || $object->vars[$var[0]]['data_type'] == XOBJ_DTYPE_DECIMAL || $object->vars[$var[0]]['data_type'] == XOBJ_DTYPE_FLOAT) {
                    $criteria->add(new Criteria('`' . $var[0] . '`', $var[1], (isset($var[2]) ? $var[2] : '=')));
                } elseif ($object->vars[$var[0]]['data_type'] == XOBJ_DTYPE_ENUM) {
                    $criteria->add(new Criteria('`' . $var[0] . '`', $var[1], (isset($var[2]) ? $var[2] : '=')));
                } elseif ($object->vars[$var[0]]['data_type'] == XOBJ_DTYPE_ARRAY) {
                    $criteria->add(new Criteria('`' . $var[0] . '`', '%"' . $var[1] . '";%', (isset($var[2]) ? $var[2] : 'LIKE')));
                }
            } elseif (!empty($var[1]) && is_numeric($var[0])) {
                $criteria->add(new Criteria($var[0], $var[1]));
            }
        }
        return $criteria;
    }

    public function getFilterForm($filter, $field, $sort = 'created', $op = 'retweet', $fct = 'list')
    {
        $ele = tweetbomb_getFilterElement($filter, $field, $sort, $op, $fct);
        if (is_object($ele)) {
            return $ele->render();
        } else {
            return '&nbsp;';
        }
    }

    public function doSearchForTweet($cid, $catid, $rids)
    {
        if (is_array($rids)) {
            $criteria = new CriteriaCompo(new Criteria('rid', '(' . implode(',', $rids) . ')', 'IN'));
        } else {
            $criteria = new CriteriaCompo(new Criteria('rid', $rids));
        }
        $criteria->setSort('RAND()');
        $criteria->setOrder('ASC');

        $log_handler = xoops_getmodulehandler('log', 'twitterbomb');
        $ret         = [];
        $terms       = $this->getObjects($criteria, true);
        foreach ($terms as $rid => $retweet) {
            // Get Since ID
            $criteria_log = new CriteriaCompo(new Criteria('rid', $rid));
            $criteria_log->add(new Criteria('cid', $cid));
            $criteria_log->add(new Criteria('catid', $catid));
            $criteria_log->setSort('`date`');
            $criteria_log->setOrder('DESC');
            $criteria_log->setStart(0);
            $criteria_log->setLimit(1);
            $logs = $log_handler->getObjects($criteria_log, false);
            if (is_object($logs[0])) {
                $since_id = $logs[0]->getVar('id');
            } else {
                $since_id = '';
            }

            // Do Search
            $ret[$rid] = twitterbomb_searchtwitter(
                $this->_modConfig['gather_on_search'],
                $retweet->getVar('search'),
                explode(' ', trim($retweet->getVar('skip'))),
                ($retweet->getVar('geocode') == true ? $retweet->getVar('longitude') . ',' . $retweet->getVar('latitude') . ',' . $retweet->getVar('radius') . $retweet->getVar('measurement') : ''),
                $retweet->getVar('language'),
                1,
                $retweet->getVar('type'),
                ($this->_modConfig['gather_on_search'] < 100 ? $this->_modConfig['gather_on_search'] : '100'),
                'true',
                '',
                $since_id
            );

            // Set Search Timer
            $retweet->setVar('searched', time());
            $this->insert($retweet, true);
        }

        return count($ret) > 0 ? $ret : false;
    }

    public function setReweeted($rid)
    {
        $sql = 'UPDATE ' . $GLOBALS['xoopsDB']->prefix('twitterbomb_retweet') . ' SET `retweets` = `retweets` + 1, `retweeted` = "' . time() . '" WHERE `rid` = ' . $rid;
        return $GLOBALS['xoopsDB']->queryF($sql);
    }

    public function insert($object, $force = true)
    {
        if ($object->isNew()) {
            $object->setVar('created', time());
            if (is_object($GLOBALS['xoopsUser'])) {
                $object->setVar('uid', $GLOBALS['xoopsUser']->getVar('uid'));
            } else {
                $object->setVar('uid', 0);
            }
        } else {
            $object->setVar('updated', time());
        }

        $run_plugin_action = false;
        if ($object->vars['type']['changed'] == true || $object->vars['language']['changed'] == true) {
            $run_plugin_action = true;
            $object->setVar('actioned', time());
        }
        if ($run_plugin_action) {
            if ($object->runPrePlugin()) {
                $rid = parent::insert($object, $force);
            } else {
                return false;
            }
        } else {
            $rid = parent::insert($object, $force);
        }
        if ($run_plugin_action) {
            return $object->runPostPlugin($rid);
        } else {
            return $rid;
        }
    }

    public function get($id, $fields = '*')
    {
        $obj = parent::get($id, $fields);
        if (is_object($obj)) {
            return $obj->runGetPlugin();
        }
    }

    public function getObjects($criteria, $id_as_key = false, $as_object = true)
    {
        $objs = parent::getObjects($criteria, $id_as_key, $as_object);
        foreach ($objs as $id => $obj) {
            if (is_object($obj)) {
                $objs[$id] = $obj->runGetPlugin();
            }
        }
        return $objs;
    }

    public function delete($id_or_object, $force = true)
    {
        if (is_numeric($id_or_object)) {
            return parent::deleteAll(new Criteria('`' . $this->keyName . '`', $id_or_object), $force);
        } elseif (is_object($id_or_object)) {
            return parent::delete($id_or_object, $force);
        }
    }
}

?>
