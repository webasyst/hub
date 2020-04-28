<?php

class hubFrontendAddAction extends hubFrontendAction
{
    protected $categories = array();

    public function execute()
    {
        if (!$this->hub_id) {
            throw new waException('No hub', 404);
        }

        $hub_types_model = new hubHubTypesModel();
        $types = $hub_types_model->getTypes($this->hub_id, true);
        if (!$types) {
            throw new waException('No types');
        }

        $this->view->assign('types', $types);
        $this->view->assign('type_id', key($types));


        if (!wa()->getUser()->getId()) {
            $this->view->assign('hub_id', $this->hub_id);
            $this->setThemeTemplate('add.html');
            return;
        }

        // Fetch categories
        $this->categories = array();
        $category_model = new hubCategoryModel();
        // static and dynamic by type
        foreach ($category_model->getByHub($this->hub_id) as $c) {
            if (!$c['type'] || (preg_match("/^type_id\=(\d+)$/uis", $c['conditions'], $match))) {
                if ($c['type']) {
                    $c['type_id'] = $match[1];
                }
                $this->categories[$c['id']] = $c;
            }
        }

        // Process POST
        $errors = array();
        if (($data = waRequest::post('data'))) {
            if (waRequest::post('_csrf') != waRequest::cookie('_csrf')) {
                throw new waException('CSRF Protection', 403);
            }
            $result = $this->save($data, $errors);
            if ($result && is_array($result)) {
                $this->redirect(wa()->getRouteUrl('hub/frontend/topic', $result));
            } elseif ($result) {
                $this->view->assign('preview', $result);
            }
        } else {
            // Tags and category may be passed via GET
            $data = array(
                'tags'        => array_filter(array_map('trim', explode(',', waRequest::get('tags', '', 'string')))),
                'category_id' => waRequest::get('category', 0, 'int'),
            );

            // If the category provided is a dynamic category filtered by topic type,
            // preselect the topic type in the editor.
            if (!empty($this->categories[$data['category_id']]['type_id'])) {
                $data['type_id'] = $this->categories[$data['category_id']]['type_id'];
                if (!empty($types[$data['type_id']])) {
                    $this->view->assign('type_id', $data['type_id']);
                }
            }
        }

        $this->view->assign('categories', $this->categories);
        $this->view->assign('errors', $errors);
        $this->view->assign('hub_id', $this->hub_id);
        $this->view->assign('data', $data);

        /**
         * @event frontend_topic_add
         * @param array $topic
         * @return array[string][string]string $return[%plugin_id%]['top_block'] html output
         * @return array[string][string]string $return[%plugin_id%]['bottom_block'] html output
         */
        $this->view->assign('frontend_topic_add', wa()->event('frontend_topic_add', $data, array('top_block', 'bottom_block')));

        wa()->getResponse()->setTitle(_w('New topic'));
        $this->setThemeTemplate('add.html');
    }


    protected function save(&$data, &$errors = array())
    {
        // Sanitize and validate
        $data += array(
            'title'       => '',
            'content'     => '',
            'tags'        => array(),
            'params'      => array(),
            'category_id' => null,
        );
        if (!is_array($data['tags'])) {
            $data['tags'] = array_filter(array_map('trim', explode(',', $data['tags'])));
        }
        if (is_array($data['params'])) {
            foreach($data['params'] as $k => $v) {
                if (!$k || $k[0] == '_' || strpos($k, '=') !== false) {
                    unset($data['params'][$k]);
                }
            }
        } else {
            $data['params'] = array();
        }

        $data['title'] = (string)$data['title'];
        if (!strlen($data['title'])) {
            $errors['title'] = true;
        }
        $data['content'] = (string)$data['content'];
        if (!strlen(trim(strip_tags($data['content'], '<img>')))) {
            $errors['content'] = true;
        }
        if (!wa_is_int($data['category_id']) || empty($this->categories[$data['category_id']]) || $this->categories[$data['category_id']]['type']) {
            $data['category_id'] = null;
        }

        // Prepare HTML for saving or preview
        if ($data['content'] && (!$errors || waRequest::request('preview'))) {
            $sanitized_content = hubHelper::sanitizeHtml($data['content']);
        }

        // Save new question
        if (!$errors && !waRequest::request('preview')) {
            $tm = new hubTopicModel();
            $url = hubHelper::transliterate($data['title'], 1);
            $topic_id = $tm->add(
                array(
                    'type_id'         => $data['type_id'],
                    'hub_id'          => $this->hub_id,
                    'create_datetime' => date('Y-m-d H:i:s'),
                    'contact_id'      => wa()->getUser()->getId(),
                    'content'         => ifset($sanitized_content),
                    'title'           => $data['title'],
                    'url'             => $url,
                    'params'          => $data['params'],
                    'tags'            => $data['tags'],
                )
            );

            // follow
            $following_model = new hubFollowingModel();
            $following_model->addFollower($topic_id);

            // +1 for this topic from author
            $vm = new hubVoteModel();
            $vm->vote($this->getUserId(), $topic_id, 'topic', 1);

            // Save category if specified
            if ($data['category_id']) {
                $tcm = new hubTopicCategoriesModel();
                $tcm->assign($topic_id, array($data['category_id']), true);
            }

            // Add author to Hub contacts category
            if (wa()->getUser()->getId()) {
                wa()->getUser()->addToCategory($this->getAppId());
            }

            return array('id' => $topic_id, 'topic_url' => $url, 'hub_id' => $this->hub_id);
        } elseif (waRequest::request('preview')) {
            return ifset($sanitized_content);
        }
        return false;
    }
}
