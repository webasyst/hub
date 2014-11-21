<?php

class hubFrontendTopicEditAction extends hubFrontendAddAction
{
    protected $topic_id;
    protected $topic;

    public function execute()
    {
        $this->topic_id = waRequest::param('id');
        $topic_model = new hubTopicModel();
        $this->topic = $topic_model->getById($this->topic_id);

        if (!$this->topic || $this->topic['contact_id'] != $this->getUserId() ||
            (time() - strtotime($this->topic['create_datetime']) > 15 * 60)) {
            throw new waRightsException(_ws('Access denied'));
        }

        if ($this->topic['hub_id'] != waRequest::param('hub_id')) {
            $url = wa()->getRouteUrl('/frontend/topicEdit', array(
                'id' => $this->topic['id'],
                'topic_url' => $this->topic['url'],
                'hub_id' => $this->topic['hub_id']
            ));
            if ($url && $url != wa()->getConfig()->getRequestUrl(false, true)) {
                wa()->getResponse()->redirect($url);
            }
            throw new waException(_w('Topic not found'), 404);
        }

        // Process POST
        $errors = array();
        if (($data = waRequest::post('data'))) {
            $result = $this->save($data, $errors);
            if ($result && is_array($result)) {
                $this->redirect(wa()->getRouteUrl('hub/frontend/topic', $result));
            } elseif ($result) {
                $this->view->assign('preview', $result);
            }
            $data['id'] = $this->topic_id;
        } else {
            $topic_tags_model = new hubTopicTagsModel();
            $rows = $topic_tags_model->getTags($this->topic_id);
            $this->topic['tags'] = array();
            foreach ($rows as $row) {
                $this->topic['tags'][$row['id']] = $row['name'];
            }
            $data = $this->topic;
        }

        $this->view->assign('errors', $errors);
        $this->view->assign('hub_id', $this->hub_id);
        $this->view->assign('data', $data);

        /**
         * @event frontend_topic_edit
         * @param array $topic
         * @return array[string][string]string $return[%plugin_id%]['top_block'] html output
         * @return array[string][string]string $return[%plugin_id%]['bottom_block'] html output
         */
        $this->view->assign('frontend_topic_edit', wa()->event('frontend_topic_edit', $this->topic, array('top_block', 'bottom_block')));

        wa()->getResponse()->setTitle($this->topic['title']);
        $this->setThemeTemplate('add.html');
    }

    protected function save(&$data, &$errors = array())
    {
        if (!is_array($data['tags'])) {
            $data['tags'] = array_filter(array_map('trim', explode(',', $data['tags'])));
        }
        $data['title'] = (string) $data['title'];
        if (!strlen($data['title'])) {
            $errors['title'] = true;
        }
        $data['content'] = (string) $data['content'];
        if (!strlen($data['content'])) {
            $errors['content'] = true;
        }
        if ($data['content'] && (!$errors || waRequest::request('preview'))) {
            $sanitized_content = hubHelper::sanitizeHtml($data['content']);
        }
        if (!$errors && !waRequest::request('preview')) {
            $tm = new hubTopicModel();
            $tm->update($this->topic_id, array(
                'content' => ifset($sanitized_content),
                'title' => $data['title'],
                'tags' => $data['tags'],
            ));
            return array('id' => $this->topic_id, 'topic_url' => $this->topic['url'], 'hub_id' => $this->hub_id);
        } elseif (waRequest::request('preview')) {
            return ifset($sanitized_content);
        }
        return false;
    }
}
