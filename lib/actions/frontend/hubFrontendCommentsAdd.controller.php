<?php

class hubFrontendCommentsAddController extends waJsonController
{
    public function execute()
    {
        $data = $this->getData();
        $comment_model = new hubCommentModel();

        $this->errors = $comment_model->validate($data);
        if ($this->errors) {
            return false;
        }

        $id = $comment_model->add($data, $data['parent_id']);
        if (!$id) {
            throw new waException("Error in adding comment");
        }

        if (wa()->getUser()->getId()) {
            wa()->getUser()->addToCategory($this->getAppId());
        }

        $count = 0;

        $topic = $this->getTopic();

        $comment_count_str = '';
        $types = hubHelper::getTypes();
        if (!empty($types[$topic['type_id']])) {
            $type = $types[$topic['type_id']];
            $commenting_disabled = ifset($type['settings']['commenting'], 1) == 0;
            if ($commenting_disabled) {
                // For questions it means that users can not comment on answers.
                // For all other topic types it means that users can not comment at all.
                if (empty($data['parent_id']) && $type['type'] == 'question') {
                    // its ok
                } else {
                    throw new waRightsException('Commenting is disabled for this topic.');
                }
            }
            if ($type['type'] == 'question') {
                $count = $comment_model->countByField(
                    array(
                        'topic_id' => $topic['id'],
                        'depth'    => 0,
                    )
                );
                $comment_count_str = _w('%d answer', '%d answers', $count);
            }
        }
        if (empty($comment_count_str)) {
            $count = $comment_model->countByField(
                array(
                    'topic_id' => $topic['id'],
                    'status'   => hubCommentModel::STATUS_PUBLISHED,
                )
            );
            $comment_count_str = _w('%d comment', '%d comments', $count);
        }

        $comment = $comment_model->getComment($id, 'author,vote,topic,parent,my_vote', true);
        $comment['editable'] = true;

        $topic_type = isset($types[$topic['type_id']]) ? $types[$topic['type_id']] : null;
        $this->response = array(
            'id'                => $id,
            'topic'             => $topic,
            'parent_id'         => $this->getParentId(),
            'count'             => $count,
            'html'              => $this->renderTemplate(
                array(
                    'topic'            => $topic,
                    'comment'          => $comment,
                    'comments_allowed' => $topic['badge'] != 'archived' && ifset($topic_type['settings']['commenting'], 1) != 0,
                    'ajax_append'      => true,
                    'just_added'       => true,
                ),
                'file:comment.html'
            ),
            'comment_count_str' => $comment_count_str,
        );

        try {
            $this->sendNotifications($comment_model->getComment($id));
        } catch (waException $e) {
            waLog::log($e->getMessage());
        }
    }

    public function getTopic()
    {
        $id = waRequest::param('id');
        $topic_model = new hubTopicModel();
        $topic = $topic_model->getById($id);
        if (!$topic) {
            throw new waException(_w('Topic not found'), 404);
        }
        $topic['badge'] = hubHelper::getBadge($topic['badge']);
        return $topic;
    }

    public function getData()
    {
        $topic = $this->getTopic();
        $parent_id = $this->getParentId();
        $text = waRequest::post('text', null, waRequest::TYPE_STRING_TRIM);

        return array(
            'topic_id'   => $topic['id'],
            'parent_id'  => $parent_id,
            'text'       => $text,
            'contact_id' => (int)$this->getUser()->getId()
        );

    }

    private function getParentId()
    {
        return waRequest::post('parent_id', 0, waRequest::TYPE_INT);
    }

    protected function sendNotifications($comment)
    {
        $view = wa()->getView();

        $topic = $comment['topic'];

        $topic['url'] = wa()->getRouteUrl(
            'hub/frontend/topic',
            array('id' => $topic['id'], 'topic_url' => $topic['url'], 'hub_id' => $topic['hub_id']),
            true
        );
        if (!$topic['url']) {
            return;
        }
        $view->assign('topic', $topic);

        $m = new waMailMessage();

        $current_user = $this->getUser();

        $following_model = new hubFollowingModel();
        foreach ($following_model->getFollowers($comment['topic']['id'], true) as $c) {
            if (empty($c['email'])) {
                continue;
            }

            // if current user leave comment, ignore sending notification to her / him
            if ($c['id'] > 0 && $c['id'] === $current_user->getId()) {
                continue;
            }

            $view->assign('contact', $c);

            $subject = _w('New comment to a topic you are subscribed to');
            $body = $view->fetch(wa()->getAppPath('templates/mail/Following.html'));
            $m->setSubject($subject);
            $m->setBody($body);
            $m->setTo($c['email'], $c['name']);
            $m->send();
        }
    }

    private function renderTemplate($assign, $template)
    {
        $theme = waRequest::param('theme', 'default');
        $theme_path = wa()->getDataPath('themes', true).'/'.$theme;
        if (!file_exists($theme_path) || !file_exists($theme_path.'/theme.xml')) {
            $theme_path = wa()->getAppPath().'/themes/'.$theme;
        }

        $view = wa()->getView(array('template_dir' => $theme_path));
        $view->assign($assign);
        $view->assign('types', hubHelper::getTypes());
        return $view->fetch($template);
    }
}
