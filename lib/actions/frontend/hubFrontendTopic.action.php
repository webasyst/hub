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

        if ($topic_type == 'question') {
            $comments = $comment_model->getFullTree($topic['id'], '*,author,vote', 'solution DESC, votes_sum DESC', true);
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

            $comments = $comment_model->getList('*,author,vote', array(
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
            $comments = $comment_model->getFullTree($topic['id'], '*,author,vote', 'left_key', true);
        }

        // comment.editable for smarty
        foreach($comments as &$c) {
            $c['editable'] = wa()->getUser()->isAdmin('hub')
                                || ($c['contact_id'] == wa()->getUser()->getId() && time() - strtotime($c['datetime']) < 15 * 60);
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

        if ($topic['contact_id'] == $this->getUserId() && (time() - strtotime($topic['create_datetime'])) <= 15 * 60) {
            $topic['editable'] = true;
            $topic['edit_url'] = wa()->getRouteUrl('/frontend/topicEdit', array('id' => $topic['id'], 'topic_url' => $topic['url']));
            $topic['delete_url'] = wa()->getRouteUrl('/frontend/topicDelete', array('id' => $topic['id'], 'topic_url' => $topic['url']));
        } else {
            $topic['editable'] = false;
        }

        $topic_type = isset($this->types[$topic['type_id']]) ? $this->types[$topic['type_id']] : null;
        $this->view->assign(array(
            'tags' => $tags,
            'topic' => $topic,
            'voted' => $voted,
            'comments' => $comments,
            'following' => $following,
            'topic_type' => $topic_type,
            'comments_count' => count($comments),
            'comments_allowed' => $topic['badge'] != 'archived' && ifset($topic_type['settings']['commenting'], 1) != 0,
            'breadcrumbs' => $this->getBreadcrumbs($id),
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

    public function getBreadcrumbs($topic_id)
    {
        $topic_categories_model = new hubTopicCategoriesModel();
        $category_id = $topic_categories_model->select('category_id')->
                where('topic_id = i:topic_id', array('topic_id' => $topic_id))->
                order('sort')->
                limit(1)->
                fetchField();
        return hubHelper::getBreadcrumbs($category_id, true);
    }
}
