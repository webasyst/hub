<?php

class hubCommentsAddController extends waJsonController
{
    /**
     * @var hubCommentModel
     */
    protected $model;
    /**
     * @var waUser
     */
    protected $author;

    /**
     * @var waSmarty3View
     */
    protected $view;

    public function __construct()
    {
        $this->model = new hubCommentModel();
        $this->author = $this->getUser();
        $this->view = wa()->getView();
    }

    public function execute()
    {
        $data = $this->getData();

        $this->errors = $this->model->validate($data);
        if ($this->errors) {
            return false;
        }

        $topic = null;
        if (!empty($data['topic_id'])) {
            $topic_model = new hubTopicModel();
            $topic = $topic_model->getById($data['topic_id']);
            $topic['type'] = null;
            if (empty($topic)) {
                throw new waException('Topic not found', 404);
            }

            // Make sure comments are allowed for this topic type
            $types = hubHelper::getTypes();
            if (!empty($types[$topic['type_id']])) {
                $type = $types[$topic['type_id']];
                $topic['type'] = $type;
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
            }
        }
        $this->view->assign('topic', $topic);

        $id = $this->model->add($data, $data['parent_id']);
        if (!$id) {
            throw new waException(_w("Error in adding comment"));
        }

        $data['id'] = $id;

        $comment = $this->model->getComment($id, 'author,vote,can_delete,my_vote');
        $comment['topic'] = $topic;
        $this->view->assign('comment', $comment);

        $this->response['id'] = $data['id'];
        $this->response['parent_id'] = $data['parent_id'];
        $this->response['html'] = $this->view->fetch('templates/actions/comments/include.comment.html');
        if ($data['topic_id']) {
            $this->response['comments_count_str'] = _w(
                '%d comment',
                '%d comments',
                $this->model->count($data['topic_id'])
            );
        }

        try {
            if (!isset($comment['topic'])) {
                $comment['topic'] = $topic;
            }
            $this->sendNotifications($comment);
        } catch (waException $e) {
            waLog::log($e->getMessage());
        }
    }

    protected function sendNotifications($comment)
    {
        $topic = $comment['topic'];

        $topic['url'] = wa()->getRouteUrl(
            'hub/frontend/topic',
            array('id' => $topic['id'], 'topic_url' => $topic['url'], 'hub_id' => $topic['hub_id']),
            true
        );
        if (!$topic['url']) {
            return;
        }
        $this->view->assign('topic', $topic);

        $m = new waMailMessage();

        $following_model = new hubFollowingModel();
        $contact_id = $this->getUser()->getId();
        foreach ($following_model->getFollowers($comment['topic']['id'], true) as $c) {
            if ($c['id'] == $contact_id || empty($c['email'])) {
                continue;
            }
            $this->view->assign('contact', $c);

            $subject = 'New comment';
            $body = $this->view->fetch('templates/mail/Following.html');
            $m->setSubject($subject);
            $m->setBody($body);
            $m->setTo($c['email'], $c['name']);
            $m->send();
        }
    }

    protected function getData()
    {
        $parent_id = waRequest::post('parent_id', 0, waRequest::TYPE_INT);
        $topic_id = waRequest::post('topic_id', 0, waRequest::TYPE_INT);
        if (!$topic_id) {
            $parent = $this->model->getById($parent_id);
            if (!$parent) {
                throw new waException(_w("Unknown parent comment"));
            }
            $topic_id = $parent['topic_id'];
        }

        $text = waRequest::post('text', null, waRequest::TYPE_STRING_TRIM);
        return array(
            'topic_id'   => $topic_id,
            'parent_id'  => $parent_id,
            'text'       => $text,
            'contact_id' => $this->author->getId()
        );
    }
}
