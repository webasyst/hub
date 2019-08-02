<?php

class hubFrontendTopicAction extends hubFrontendAction
{
    public function execute()
    {
        $id = waRequest::param('id');
        $topic_model = new hubTopicModel();
        $topic = $topic_model->getById($id);

        if (!$topic) {
            throw new waException(_w('Topic not found'), 404);
        }

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

        $comment_model = new hubCommentModel();
        $topic_type = isset($this->types[$topic['type_id']]) ? $this->types[$topic['type_id']]['type'] : 'custom';

        $base_types = hubHelper::getBaseTypes();

        if (!empty($base_types[$topic_type]['solution'])) {
            $comments = $comment_model->getFullTree($topic['id'], '*,author,vote,my_vote', 'solution DESC, votes_sum DESC', true);
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

        /**
         * @event frontend_comments
         * @param array $comments
         */
        wa()->event('frontend_comments', $comments);

        $this->getResponse()->setMeta('keywords', $topic['meta_keywords']);
        $this->getResponse()->setMeta('description', $topic['meta_description']);
        $this->getResponse()->setTitle(ifempty($topic['meta_title'], $topic['title']).($topic['badge']['id'] == 'archived' ? ' ['._w('Archived').']' : ''));

        if ($topic['contact_id'] == $this->getUserId() && (wa()->getUser()->isAdmin('hub') || time() - strtotime($topic['create_datetime']) <= 45 * 60)) {
            $topic['editable'] = true;
            $topic['edit_url'] = wa()->getRouteUrl('/frontend/topicEdit', array('id' => $topic['id'], 'topic_url' => $topic['url']));
            $topic['delete_url'] = wa()->getRouteUrl('/frontend/topicDelete', array('id' => $topic['id'], 'topic_url' => $topic['url']));
        } else {
            $topic['editable'] = false;
        }

        $category_model = new hubCategoryModel();
        $categories = $category_model->getByTopic($topic);

        $topic_type = isset($this->types[$topic['type_id']]) ? $this->types[$topic['type_id']] : null;
        $comments_allowed = $topic['badge']['id'] != 'archived' && ifset($this->types[$topic['type_id']]['settings']['commenting'], 1) != 0;
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
}
