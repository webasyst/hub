<?php

class hubTopicsSaveController extends waJsonController
{
    public function execute()
    {
        $id = waRequest::get('id');
        $data = $this->getData();

        $topic_model = new hubTopicModel();
        if ($id) {
            $topic = $topic_model->getById($id);
            if (!$topic) {
                throw new waException('Topic not found', 404);
            }

            // Does user have access to hub where topic used to be?
            $access_level = wa()->getUser()->getRights('hub', 'hub.'.$topic['hub_id']);
            if ($access_level < hubRightConfig::RIGHT_READ_WRITE || ($access_level == hubRightConfig::RIGHT_READ_WRITE && $topic['contact_id'] != wa()->getUser()->getId())) {
                throw new waRightsException('Access denied');
            }
        }

        // Does user have access to the hub where topic is going to be put?
        $hub_id = ifempty($data['hub_id']);
        if (!$hub_id) {
            throw new waException('Unknown hub_id');
        }
        $access_level = wa()->getUser()->getRights('hub', 'hub.'.$hub_id);
        if ($access_level < hubRightConfig::RIGHT_READ_WRITE || ($access_level == hubRightConfig::RIGHT_READ_WRITE && $id && $topic['contact_id'] != wa()->getUser()->getId())) {
            throw new waRightsException('Access denied');
        }

        // Save the topic
        $just_published = false;
        if ($id) {
            $topic_model->update($id, $data);
            if (!$topic['status'] && $data['status']) {
                $just_published = true;
            }
        } else {
            $id = $topic_model->add($data);
            // follow
            $following_model = new hubFollowingModel();
            $following_model->addFollower($id);

            if ($data['status']) {
                $just_published = true;
            }

            $this->response['add'] = 1;

        }

        $topic = $topic_model->getById($id);

        // Notify users about published topic
        if ($just_published && ( $users_to_notify = waRequest::post('users_to_notify'))) {
            $users_to_notify = array_map('intval', explode(',', $users_to_notify));
            $collection = new waContactsCollection('id/'.join(',', $users_to_notify));

            $view = wa()->getView();
            $subject = _w('New topic').($topic['title'] ? ': ' : ' ').$topic['title'];
            $message = waRequest::request('notification_message', '', 'string');

            foreach($collection->getContacts('*') as $c) {
                try {
                    $c = new waContact($c);
                    $email = $c->get('email', 'default');
                    if ($email) {
                        $view->assign(array(
                            'topic' => $topic,
                            'contact' => $c,
                            'message' => $message,
                            'sender' => wa()->getUser(),
                        ));

                        $m = new waMailMessage();
                        $m->setSubject($subject);
                        $m->setBody($view->fetch(wa()->getAppPath('templates/mail/NewTopic.html')));
                        $m->setTo($email, $c->getName());
                        $m->send();
                    }
                } catch (Exception $e) {
                    waLog::log('Unable to send notification email to '.$email.': '.$e->getMessage());
                }
            }
        }

        // Prepare data for JS
        $this->response['topic'] = $topic;
        $this->response['topic']['title'] = htmlspecialchars($this->response['topic']['title']);
        if (!$this->response['topic']['status']) {
            $c = new waContact($this->response['topic']['contact_id']);
            $this->response['contact'] = array(
                'photo' => $c->getPhoto(20)
            );
        }
        $this->response['topic']['datetime'] = wa_date('humandate', $this->response['topic']['create_datetime']);
    }

    public function getData()
    {
        $data = waRequest::post('topic');
        if (isset($data['tags'])) {
            $data['tags'] = $this->toArray($data['tags']);
        }
        if (isset($data['categories'])) {
            $data['categories'] = $this->toArray($data['categories']);
        }
        $data['status'] = waRequest::post('draft') ? 0 : 1;

        $ts = time();
        if (!empty($data['create_date']) && (int)$data['create_date']) {
            $ts = round($data['create_date'] / 1000);
            if (!empty($data['create_time'])) {
                @list($h, $m) = explode(':', $data['create_time'], 2);
                $ts += (int)$h * 3600 + (int)$m * 60;
            }
            $ts = self::convertToServerTime($ts);
        }
        $data['create_datetime'] = date('Y-m-d H:i:s', $ts);
        unset($data['create_date'], $data['create_time']);

        return $data;
    }

    public static function convertToServerTime($timestamp)
    {
        $timezone = wa()->getUser()->getTimezone();
        $default_timezone = waDateTime::getDefaultTimeZone();
        if ($timezone && $timezone != $default_timezone) {
            $date_time = new DateTime(date('Y-m-d H:i:s', $timestamp), new DateTimeZone($timezone));
            $date_time->setTimezone(new DateTimeZone($default_timezone));
            $timestamp = (int) $date_time->format('U');
        }
        return $timestamp;
    }

    public function toArray($string)
    {
        if (!$string) {
            return array();
        }
        if (!is_array($string)) {
            $string = explode(',', $string);
        }
        return array_map('trim', $string);
    }

}