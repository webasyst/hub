<?php

class hubTopicsInfoAction extends waViewAction
{
    public function execute()
    {
        $id = waRequest::get('id');
        $topic_model = new hubTopicModel();
        $topic = $topic_model->getById($id);
        if (!$topic) {
            throw new waException('Topic not found', 404);
        }

        $access_level = wa()->getUser()->getRights('hub', 'hub.'.$topic['hub_id']);
        if ($access_level <= hubRightConfig::RIGHT_NONE) {
            throw new waRightsException('Access denied');
        }

        $topic['tags'] = $this->getTags($topic);

        if ($topic['badge']) {
            $topic['badge'] = hubHelper::getBadge($topic['badge']);
        }

        $routing = wa()->getRouting();
        $domain_routes = $routing->getByApp($this->getAppId());
        foreach ($domain_routes as $domain => $routes) {
            foreach ($routes as $r) {
                if ($r['hub_id'] == $topic['hub_id']) {
                    $routing->setRoute($r, $domain);
                    break 2;
                }
            }
        }

        $topic_public_url = $routing->getUrl(
            '/frontend/topic',
            array(
                'id'        => $topic['id'],
                'topic_url' => $topic['url']
            ),
            true
        );

        $comment_model = new hubCommentModel();

        $types = hubHelper::getTypes();
        $base_types = hubHelper::getBaseTypes();
        $topic['type'] = ifempty($types[$topic['type_id']]);
        $possible_badges = hubHelper::getBadgesByType($topic['type']);

        if ($topic['contact_id']) {
            $contact_model = new waContactModel();
            $contact = $contact_model->getById($topic['contact_id']);
            if ($contact) {
                $topic['contact'] = new waContact($contact);
            }
        }

        // Followers
        $following_model = new hubFollowingModel();
        $followers = $following_model->getFollowers($topic['id']);
        $follow = $followers && $followers[0]['id'] == $this->getUser()->getId();

        $hub = hubHelper::getHub($topic['hub_id']);

        // Topic comments
        if (!empty($base_types[$topic['type']['type']]['solution'])) {
            $comments = $comment_model->getFullTree($topic['id'], '*,is_updated,contact,vote,can_delete,my_vote', 'solution DESC, votes_sum DESC');
        } else {
            $comments = $comment_model->getFullTree($topic['id'], '*,is_updated,contact,vote,can_delete,my_vote');
        }
        foreach ($comments as &$c) {
            $c['topic'] = $topic;
        }
        unset($c);

        // User voted already?
        $vote_model = new hubVoteModel();
        $voted = $vote_model->getVote(wa()->getUser()->getId(), 'topic', $id);

        // Mark topic and comments as read in session
        $visited_comments = array();
        foreach($comments as $c) {
            if (!empty($c['is_updated']) || !empty($c['is_new'])) {
                $visited_comments[$c['id']] = $c['id'];
            }
        }
        wa('hub')->getConfig()->markAsRead(array($id), $visited_comments);

        $this->view->assign(array(
            'hub'                => $hub,
            'voted'              => $voted,
            'topic'              => $topic,
            'comments'           => $comments,
            'allow_commenting'   => !$topic['type'] || empty($topic['type']['settings']) || !empty($topic['type']['settings']['commenting']),
            'topic_public_url'   => $topic_public_url,
            'possible_badges'    => $possible_badges,
            'comments_count'     => $topic['comments_count'],
            'current_author'     => hubHelper::getAuthor($this->getUserId()),
            'notifications_sent' => waRequest::request('notifications_sent'),
            'can_edit_delete'    => ($topic['contact_id'] == wa()->getUser()->getId() && $access_level >= hubRightConfig::RIGHT_READ_WRITE)
                                        || $access_level >= hubRightConfig::RIGHT_FULL,
            'follow' => $follow,
            'followers' => $followers,
            'types' => hubHelper::getTypes()
        ));
    }

    /**
     *
     * @return array of strings
     */
    public function getTags($topic)
    {
        $topic_tags_model = new hubTopicTagsModel();
        $tag_ids = array_keys($topic_tags_model->getByField('topic_id', $topic['id'], 'tag_id'));
        $tag_model = new hubTagModel();
        return $tag_model->getByField('id', $tag_ids, 'id');
    }
}
