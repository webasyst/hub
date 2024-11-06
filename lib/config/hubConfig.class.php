<?php

class hubConfig extends waAppConfig
{
    const VIRTUAL_NO_BADGE_ID = '_no_badge_';

    public function onInit()
    {
        $wa = wa();
        $id = $wa->getUser()->getId();
        if ($id && ($wa->getApp() == 'hub') && ($wa->getEnv() == 'backend')) {
            $this->setCount($this->onCount());
            $this->maybeArchiveTopics();
        }
    }

    /**
     * Return timestamp of last user activity in previous user session.
     */
    public function getLastDatetime()
    {
        // Return memory-cached result if present
        static $prev_session_last_datetime = null;
        if ($prev_session_last_datetime) {
            return $prev_session_last_datetime;
        }

        // In wa_contact_settings we store timestamp of last user activity.
        if (wa()->getUser()->getId()) {
            $contact_settings_model = new waContactSettingsModel();
            $last_datetime = (int)$contact_settings_model->getOne(wa()->getUser()->getId(), 'hub', 'hub_last_datetime');
            if (empty($last_datetime)) {
                // This is the first time this user opened this app.
                // Set up default settings for new user.
                $this->setupFirstLogin();
            }
        }
        if (empty($last_datetime)) {
            $last_datetime = time();
        }

        // In session we store time of last user activity in previous session.
        $storage = wa()->getStorage();
        $prev_session_last_datetime = $storage->get('hub_last_datetime');

        // When our key is absent from session, it means that a new session has just started.
        // So, we take time from DB as the last activity from previous session.
        if (!$prev_session_last_datetime) {
            $prev_session_last_datetime = $last_datetime;
            $storage->set('hub_last_datetime', $prev_session_last_datetime);
            $storage->set('hub_visited_comments', array());
            $storage->set('hub_visited_topics', array());
        }

        // Once every couple of minutes update last user activity time in DB.
        if (wa()->getUser()->getId() && time() - $last_datetime > 120) {
            $contact_settings_model->set(wa()->getUser()->getId(), 'hub', 'hub_last_datetime', time());
        }

        return $prev_session_last_datetime;
    }

    public function onCount()
    {
        $storage = wa()->getStorage();
        $type = [];
        $type_items_count = wa()->getUser()->getSettings('hub', 'type_items_count');
        if (!empty($type_items_count)) {
            $type = explode(',', $type_items_count);
            $type = array_filter(array_map('trim', $type), 'strlen');
        }
        $count = 0;

        $url = $this->getBackendUrl(true).$this->application.'/';
        if (in_array('comments', $type)) {
            $m = new hubCommentModel();
            $count += $m->countNewToMyFollowing();

            if ($count) {
                $url = $this->getBackendUrl(true).$this->application.'/#/following/updated/';
            }
        }

        if (in_array('topics', $type)) {
            $m = new hubTopicModel();
            $cnt = $m->countNew();
            if (!empty($cnt['all'])) {
                $count += $cnt['all'];
                $url = $this->getBackendUrl(true).$this->application.'/#/recent/';
            }
        }

        return array(
            'count' => $count,
            'url' => $url,
        );
    }

    public function markAsRead($topic_ids=array(), $comment_ids=array())
    {
        $hub_visited_topics = wa()->getStorage()->get('hub_visited_topics');
        foreach($topic_ids as $id) {
            $hub_visited_topics[$id] = $id;
        }
        wa()->getStorage()->set('hub_visited_topics', $hub_visited_topics);

        $hub_visited_comments = wa()->getStorage()->get('hub_visited_comments');
        foreach($comment_ids as $id) {
            $hub_visited_comments[$id] = $id;
        }
        wa()->getStorage()->set('hub_visited_comments', $hub_visited_comments);
    }

    public function setupFirstLogin()
    {
        wa()->getUser()->setSettings('hub', 'hub_last_datetime', time());
        wa()->getUser()->setSettings('hub', 'type_items_count', 'comments,topics');
        wa()->getUser()->setSettings('hub', 'email_following', 1);
    }

    public function checkRights($module, $action)
    {
        if (wa()->getUser()->isAdmin('hub')) {
            return true;
        }

        // Some modules are only allowed for admins
        if (in_array($module, array('categories', 'dialog'))) {
            return false;
        }

        // Modules with specific access control rights
        if ($module == 'design') {
            return !!wa()->getUser()->getRights('hub','design');
        }
        if ($module == 'pages' && $action != 'uploadimage') {
            return !!wa()->getUser()->getRights('hub', 'pages');
        }

        // Some settings are available to everybody, and some are admin-only
        if ($module == 'settings') {
            // Personal settings
            if ($action === null) {
                return true;
            }
            // Edit (personal) filter settings
            if (strpos($action, 'filter') === 0) {
                return true;
            }

            // Hub, hub type settings, etc.
            return false;
        }

        // Everything else is available to everybody
        return true;
    }

    /**
     * List of hub_ids available for current user at given permission level.
     * @param $level int e.g. hubRightConfig::RIGHT_READ
     * @return array hub_id => hub data
     */
    public function getAvailableHubs($level = hubRightConfig::RIGHT_READ)
    {
        static $result = array();
        if (isset($result[$level])) {
            return $result[$level];
        }

        static $hubs = null;
        if ($hubs === null) {
            $hub_model = new hubHubModel();
            $hubs = $hub_model->getHubs();
            foreach($hubs as &$h) {
                $h['params'] = array();
            }
            unset($h);

            $hub_params_model = new hubHubParamsModel();
            foreach($hub_params_model->getAll() as $row) {
                if (isset($hubs[$row['hub_id']])) {
                    $hubs[$row['hub_id']]['params'][$row['name']] = $row['value'];
                }
            }
        }

        $contact = wa()->getUser();
        if ($contact->isAdmin('hub')) {
            $result[$level] = $hubs;
            return $result[$level];
        }

        $rslt = array();

        // All hubs this user has access to in backend
        if (wa()->getEnv() == 'backend') {
            $rights = $contact->getRights('hub');
            if ($rights) {
                foreach ($rights as $name => $lvl) {
                    if (substr($name, 0, 4) == 'hub.' && $lvl >= $level) {
                        $id = (int)substr($name, 4);
                        if (!empty($hubs[$id])) {
                            $rslt[$id] = $hubs[$id];
                        }
                    }
                }
            }
        }

        // All public hubs have at least RIGHT_READ_WRITE for everyone
        if ($level <= hubRightConfig::RIGHT_READ_WRITE) {
            foreach ($hubs as $hub) {
                if ($hub['status'] > 0) {
                    $rslt[$hub['id']] = $hub;
                }
            }
        }

        $result[$level] = $rslt;
        return $result[$level];
    }

    public function maybeArchiveTopics()
    {
        $app_settings_model = new waAppSettingsModel();
        $archive_check = $app_settings_model->get('hub', 'archive_check', 0);
        if (time() - $archive_check < 3600*12) {
            return;
        }
        $app_settings_model->set('hub', 'archive_check', time());

        $m = new waModel();
        foreach(hubHelper::getTypes() as $type) {
            if (!empty($type['settings']['auto_archive']) && ifset($type['settings']['auto_archive_days']) > 0) {
                $update_datetime = time() - $type['settings']['auto_archive_days'] * 3600 * 24;
                $sql = "UPDATE hub_topic SET badge='archived' WHERE type_id=? AND update_datetime < ?";
                $m->exec($sql, $type['id'], date('Y-m-d H:i:s', $update_datetime));
            }
        }
    }

    public function explainLogs($logs)
    {
        $logs = parent::explainLogs($logs);
        $app_url = wa()->getConfig()->getBackendUrl(true).$this->getApplication().'/';

        $topic_ids = array();
        $comment_ids = array();
        foreach ($logs as $l_id => $l) {
            if (in_array($l['action'], array('topic_publish', 'topic_unpublish', 'topic_edit')) && $l['params']) {
                $topic_ids[$l['params']] = 1;
            } else if (in_array($l['action'], array('comment_add', 'comment_edit', 'comment_delete', 'comment_restore')) && $l['params']) {
                $comment_ids[$l['params']] = 1;
            }
        }
        if ($comment_ids) {
            $comment_model = new hubCommentModel();
            $comments = $comment_model->getById(array_keys($comment_ids));
            foreach($comments as $c) {
                $topic_ids[$c['topic_id']] = 1;
            }
        }
        if ($topic_ids) {
            $topic_model = new hubTopicModel();
            $topics = $topic_model->getById(array_keys($topic_ids));
        }
        foreach ($logs as $l_id => $l) {
            $c = $t = null;
            if (in_array($l['action'], array('topic_publish', 'topic_unpublish', 'topic_edit'))) {
                if (isset($topics[$l['params']])) {
                    $t = $topics[$l['params']];
                }
            } else if (in_array($l['action'], array('comment_add', 'comment_edit', 'comment_delete', 'comment_restore'))) {
                if (isset($comments[$l['params']])) {
                    $c = $comments[$l['params']];
                    if (isset($topics[$c['topic_id']])) {
                        $t = $topics[$c['topic_id']];
                    }
                }
            }
            $logs[$l_id]['params_html'] = '';
            if ($t) {
                $url = $app_url.'#/topic/'.$t['id'].'/';
                $logs[$l_id]['params_html'] .= '<div class="activity-target"><a href="'.$url.'">'.htmlspecialchars($t['title']).'</a></div>';
            }
            if (!empty($c)) {
                $logs[$l_id]['params_html'] .= '<div class="activity-body"><p'.(empty($c['status']) ? ' class="strike gray"' : '').'>'.nl2br(mb_substr(strip_tags($c['text']), 0, 512)).'</p></div>';
            }
        }
        return $logs;
    }
}

