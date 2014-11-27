<?php

class hubConfig extends waAppConfig
{

    public function onInit()
    {
        $wa = wa();
        $id = $wa->getUser()->getId();
        if ($id && ($wa->getApp() == 'hub') && ($wa->getEnv() == 'backend')) {
            $this->setCount($this->onCount());
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
        }

        // Once every couple of minutes update last user activity time in DB.
        if (wa()->getUser()->getId() && time() - $last_datetime > 120) {
            $contact_settings_model->set(wa()->getUser()->getId(), 'hub', 'hub_last_datetime', time());
        }

        return $prev_session_last_datetime;
    }

    public function onCount()
    {
        $type = explode(',', wa()->getUser()->getSettings('hub', 'type_items_count'));
        $type = array_filter(array_map('trim', $type), 'strlen');
        $count = 0;
        if (in_array('topics', $type)) {
            $m = new hubTopicModel();
            $count += $m->countNew();
        }
        if (in_array('comments', $type)) {
            $m = new hubCommentModel();
            if (!in_array('comments_to_topics', $type)) {
                $count += $m->countNew();
            } else {
                $count += $m->countNewToMyFollowing();
            }
        }

        return $count;
    }

    public function setupFirstLogin()
    {
        wa()->getUser()->setSettings('hub', 'hub_last_datetime', time());
        wa()->getUser()->setSettings('hub', 'type_items_count', 'comments');
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
            return !!wa()->getUser()->getRights('design');
        }
        if ($module == 'pages') {
            return !!wa()->getUser()->getRights('pages');
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
            $hubs = $hub_model->getAll('id');
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
}
