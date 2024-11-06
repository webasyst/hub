<?php

class hubFrontendTopicAction extends hubFrontendAction
{
    public function execute()
    {
        $id = waRequest::param('id');
        $topic_model = new hubTopicModel();
        $topic = $topic_model->getById($id);
        $comment_model = new hubCommentModel();

        if (!$topic) {
            throw new waException(_w('Topic not found'), 404);
        }

        $newest_comment = $comment_model->select('datetime')
            ->where("`topic_id` = {$id} AND `datetime` > '{$topic['update_datetime']}'")
            ->order('datetime DESC')->fetchField('datetime');
        $topic_update_datetime = ifempty($newest_comment, $topic['update_datetime']);
        $this->getResponse()->setLastModified($topic_update_datetime);

        if (!$topic['status']) {
            $app_settings_model = new waAppSettingsModel();
            $hash = $app_settings_model->get('hub', 'preview_hash');
            if (!$hash || md5($hash) != waRequest::get('preview')) {
                throw new waException(_w('Topic not found'), 404);
            }
        }

        if ($topic['hub_id'] != waRequest::param('hub_id') || waRequest::param('topic_url') != $topic['url']) {
            $url = wa()->getRouteUrl('/frontend/topic', array(
                'id' => $topic['id'],
                'topic_url' => $topic['url'],
                'hub_id' => $topic['hub_id']
            ));
            if ($url && $url != wa()->getConfig()->getRequestUrl(false, true)) {
                wa()->getResponse()->redirect($url);
            }
            throw new waException(_w('Topic not found'), 404);
        }

        $topic_params_model = new hubTopicParamsModel();
        $topic['params'] = $topic_params_model->getByTopic($topic['id']);

        $topic_tags_model = new hubTopicTagsModel();
        $tags = $topic_tags_model->getTags($topic['id']);
        $tag_url = wa()->getRouteUrl('/frontend/tag', array('tag' => '%TAG%'));
        foreach ($tags as &$t) {
            $t['url'] = str_replace('%TAG%', urlencode($t['name']), $tag_url);
        }
        unset($t);

        $topic['author'] = hubHelper::getAuthor($topic['contact_id']);

        if ($topic['badge']) {
            $topic['badge'] = hubHelper::getBadge($topic['badge']);
        }

        $voted = 0;
        $following = 0;
        if (wa()->getUser()->getId()) {
            // User voted already?
            $vote_model = new hubVoteModel();
            $voted = $vote_model->getVote(wa()->getUser()->getId(), 'topic', $id);

            $following_model = new hubFollowingModel();
            if ($following_model->getByField(array('topic_id' => $id, 'contact_id' => $this->getUser()->getId()))) {
                $following = 1;
            }
        }

        $topic_type = isset($this->types[$topic['type_id']]) ? $this->types[$topic['type_id']]['type'] : 'custom';

        $base_types = hubHelper::getBaseTypes();

        if (!empty($base_types[$topic_type]['solution'])) {
            $comments = $comment_model->getFullTree($topic['id'], '*,author,vote,my_vote', 'datetime', true);
        } elseif ($topic_type == 'forum') {
            $limit = $this->getConfig()->getOption('comments_per_page');
            if (!$limit) {
                $limit = 20;
            }
            $page = waRequest::get('page', 1, 'int');
            if ($page < 1) {
                $page = 1;
            }
            $offset = ($page - 1) * $limit;

            $comments = $comment_model->getList('*,author,vote,my_vote', array(
                'offset' => $offset,
                'limit' => $limit,
                'order' => 'left_key',
                'where' => array(
                    'topic_id' => $topic['id'],
                    'status' => hubCommentModel::STATUS_PUBLISHED
                )
            ), $count);

            if (!$count) {
                $pages_count = 1;
            } else {
                $pages_count = ceil((float)$count / $limit);
            }
            $this->view->assign('pages_count', $pages_count);
        } else {
            $comments = $comment_model->getFullTree($topic['id'], '*,author,vote,my_vote', 'left_key', true);
        }

        // comment.editable for smarty
        $is_admin = wa()->getUser()->isAdmin('hub');
        foreach($comments as &$c) {
            $current_user_is_author = $c['contact_id'] == wa()->getUser()->getId();
            $edit_time_is_relevant = time() - strtotime($c['datetime']) < 15 * 60;
            $c['editable'] = $is_admin || ($current_user_is_author && $edit_time_is_relevant);
        }
        unset($c);

        $is_archived = ifset($topic, 'badge', 'id', null) === 'archived';

        /**
         * @event frontend_comments
         * @param array $comments
         */
        wa()->event('frontend_comments', $comments);

        $topic_og_model = new hubTopicOgModel();
        $route = wa()->getRouting()->getRoute();
        $og = $topic_og_model->get($topic['id']) + array(
            'site_name'   => ifset($route, 'og_site_name', ''),
            'locale'      => ifset($route, 'og_locale', wa()->getLocale()),
            'type'        => 'website',
            'url'         => wa()->getConfig()->getHostUrl() . wa()->getConfig()->getRequestUrl(false, true),
        );

        $this->getResponse()->setTitle(ifempty($topic['meta_title'], $topic['title']).($is_archived ? ' ['._w('Archived').']' : ''));
        $this->getResponse()->setMeta('keywords', $topic['meta_keywords']);
        $this->getResponse()->setMeta('description', $topic['meta_description']);
        if (!isset($og['title']) && !isset($og['description'])) {
            $og['title'] = $topic['meta_title'];
            $og['description'] = $topic['meta_description'];
        }
        foreach ($og as $property => $content) {
            if (strlen($content)) {
                $this->getResponse()->setOGMeta('og:'.$property, $content);
            }
        }

        $this->extendByTopicRights($topic);

        if ($topic['editable']) {
            $topic['edit_url'] = wa()->getRouteUrl('/frontend/topicEdit', array('id' => $topic['id'], 'topic_url' => $topic['url']));
        } else {
            $topic['edit_url'] = 'javascript:void(0)';
        }

        if ($topic['deletable']) {
            $topic['delete_url'] = wa()->getRouteUrl('/frontend/topicDelete', array('id' => $topic['id'], 'topic_url' => $topic['url']));
        } else {
            $topic['delete_url'] = 'javascript:void(0)';
        }

        $category_model = new hubCategoryModel();
        $categories = $category_model->getByTopic($topic);

        $topic_type = isset($this->types[$topic['type_id']]) ? $this->types[$topic['type_id']] : null;
        $comments_allowed = !$is_archived && ifset($this->types, $topic['type_id'], 'settings', 'commenting', 1) != 0;
        $this->view->assign(array(
            'tags' => $tags,
            'topic' => $topic,
            'voted' => $voted,
            'comments' => $comments,
            'following' => $following,
            'topic_type' => $topic_type,
            'categories' => $categories,
            'comments_count' => count($comments),
            'comments_allowed' => $comments_allowed,
            'breadcrumbs' => $this->getBreadcrumbs($topic, $categories),
        ));

        /**
         * @event frontend_topic
         * @param array $topic
         * @return array[string][string]string $return[%plugin_id%]['title_suffix'] html output
         * @return array[string][string]string $return[%plugin_id%]['body'] html output
         * @return array[string][string]string $return[%plugin_id%]['comments'] html output
         */
        $frontend_topic = wa()->event('frontend_topic', $topic, array('title_suffix', 'body', 'comments'));
        $this->view->assign('frontend_topic', $frontend_topic);

        $this->setThemeTemplate('topic.html');
    }

    public function getBreadcrumbs($topic, $categories)
    {
        if (!$categories) {
            return array();
        }
        $c = reset($categories);
        return array(
            array(
                'url'  => wa()->getRouteUrl('/frontend/category', array('category_url' => $c['url'])),
                'name' => $c['name']
            )
        );
    }

    /**
     * Extend topic info by rights flags
     * @param array &$topic
     *    Topic will be extended by these 2 flags
     *      - bool $topic['editable']  - can current user edit topic?
     *      - bool $topic['deletable'] - can current user delete topic?
     * @throws waDbException
     * @throws waException
     *
     * @see hubFrontendTopicEditAction::hasAccess()
     * @see hubFrontendTopicDeleteController::hasAccess()
     */
    protected function extendByTopicRights(&$topic)
    {
        // admin has full access
        // same as in hubFrontendTopicEditAction::hasAccess() and hubFrontendTopicDeleteController::hasAccess()
        if ($this->isHubAppAdmin()) {
            $topic['editable'] = true;
            $topic['deletable'] = true;
            return;
        }

        $hub_params_model = new hubHubParamsModel();
        $hub_params = $hub_params_model->getParams($topic['hub_id']);
        $topic['editable'] = $this->isEditable($topic, $hub_params);
        $topic['deletable'] = $this->isDeletable($topic, $hub_params);
    }

    /**
     * Can current user edit topic?
     * @param array $topic
     * @param array $hub_params
     * @return bool
     * @throws waException
     * @see hubFrontendTopicEditAction::hasAccess()
     */
    protected function isEditable($topic, $hub_params = array())
    {
        // admin has full access
        // same as in hubFrontendTopicEditAction::hasAccess()
        if ($this->isHubAppAdmin()) {
            return true;
        }
        if (!$this->isOwnTopic($topic)) {
            return false;
        }
        return $this->isTopicNewlyCreated($topic) || !empty($hub_params['frontend_allow_edit_topic']);
    }

    /**
     * Can current user delete topic?
     * @param array $topic
     * @param array $hub_params
     * @return bool
     * @throws waException
     * @see hubFrontendTopicDeleteController::hasAccess()
     */
    protected function isDeletable($topic, $hub_params = array())
    {
        // admin has full access
        // same as in hubFrontendTopicDeleteController::hasAccess()
        if ($this->isHubAppAdmin()) {
            return true;
        }
        if (!$this->isOwnTopic($topic)) {
            return false;
        }
        return $this->isTopicNewlyCreated($topic) || !empty($hub_params['frontend_allow_delete_topic']);
    }

    /**
     * Current user is owner of topic?
     * @param array $topic
     * @return bool
     */
    protected function isOwnTopic($topic)
    {
        return $topic['contact_id'] == $this->getUserId();
    }

    /**
     * Has topic created recently?
     * @param array $topic
     * @return false|int
     */
    protected function isTopicNewlyCreated($topic)
    {
        return time() - strtotime($topic['create_datetime']) <= 60 * 60;    // 1h
    }

    /**
     * Is current user admin of hub application?
     * @return bool
     * @throws waException
     */
    protected function isHubAppAdmin()
    {
        return wa()->getUser()->isAdmin('hub');
    }
}
