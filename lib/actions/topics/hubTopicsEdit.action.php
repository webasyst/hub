<?php

class hubTopicsEditAction extends waViewAction
{
    public function execute()
    {
        $id = waRequest::get('id');

        $hubs = wa()->getConfig()->getAvailableHubs(hubRightConfig::RIGHT_READ_WRITE);

        $topic_model = new hubTopicModel();
        $topic_types = hubHelper::getTypes();

        if ($id) {
            $topic = $topic_model->getById($id);
            if (!$topic) {
                throw new waException(_w('Topic not found'), 404);
            }
            $topic['tags'] = $this->getTags($topic);
            $topic['categories'] = self::getCategories($id);
            $hub_id = $topic['hub_id'];

            if ($topic['badge']) {
                $topic['badge'] = hubHelper::getBadge($topic['badge']);
            }

            $base_types = hubHelper::getBaseTypes();
            $topic['type'] = ifempty($topic_types[$topic['type_id']]);

            $possible_badges = hubHelper::getBadgesByType($topic['type']);

            if ($topic['contact_id']) {
                $contact_model = new waContactModel();
                $contact = $contact_model->getById($topic['contact_id']);
                if ($contact) {
                    $topic['author'] = $contact;
                    $topic['contact'] = new waContact($contact);
                }
            }

            $topic_params_model = new hubTopicParamsModel();
            $params = $topic_params_model->getByTopic($id);
            $params_string = array();
            foreach($params as $k => $v) {
                if ($k && $k[0] != '_' && strpos($k, '=') === false) {
                    $params_string[] = "{$k}={$v}";
                }
            }
            $params_string = join("\n", $params_string);

            $topic_og_model = new hubTopicOgModel();
            $og = $topic_og_model->get($id);
            $og += hubTopicOgModel::getEmptyData();

            $comment_model = new hubCommentModel();

            // Followers
            $following_model = new hubFollowingModel();
            $followers = $following_model->getFollowers($topic['id']);
            $follow = $followers && $followers[0]['id'] == $this->getUser()->getId();

            // Topic comments
            if (!empty($base_types[$topic['type']['type']]['solution'])) {
                $comments = $comment_model->getFullTree($topic['id'], '*,is_updated,contact,vote,can_delete,my_vote', 'solution DESC, votes_sum DESC');
            } else {
                $comments = $comment_model->getFullTree($topic['id'], '*,is_updated,contact,vote,can_delete,my_vote');
            }


            $is_admin = wa()->getUser()->isAdmin('hub');
            foreach ($comments as &$c) {
                $current_user_is_author = $c['contact_id'] == wa()->getUser()->getId();
                $edit_time_is_relevant = time() - strtotime($c['datetime']) < 15 * 60;
                $c['editable'] = $is_admin || ($current_user_is_author && $edit_time_is_relevant);

                $c['topic'] = $topic;
                if (!empty($c['parent_id']) && !empty($comments[$c['parent_id']])) {
                    $c['parent'] = $comments[$c['parent_id']];
                }
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

            $hub_url_templates = self::getHubPublicUrlTemplates();
            $topic_public_url = str_replace(
                ['%topic_id%', '%topic_url%'],
                [$topic['id'], $topic['url']],
                ifempty($hub_url_templates, $hub_id, '')
            );
        } else {

            $hub_id = waRequest::get('hub_id');
            if (!$hub_id && $hubs) {
                $hub_id = key($hubs);
            }

            reset($topic_types);
            $topic = array(
                    'contact_id' => wa()->getUser()->getId(),
                    'priority'   => 0,
                    'hub_id'     => $hub_id,
                    'type_id'    => key($topic_types),
                    'tags'       => array(),
                    'categories' => array(),
                    'status'     => 0
                ) + $topic_model->getEmptyRow();

            $params_string = '';

            $og = hubTopicOgModel::getEmptyData();
        }

        // Does user have access to hub where topic used to be?
        $access_level = wa()->getUser()->getRights('hub', 'hub.'.$topic['hub_id']);
        if ($access_level < hubRightConfig::RIGHT_READ_WRITE || ($access_level == hubRightConfig::RIGHT_READ_WRITE && $topic['contact_id'] != wa()->getUser()->getId())) {
            if (in_array((int) $access_level, [hubRightConfig::RIGHT_READ, hubRightConfig::RIGHT_READ_WRITE])) {
                echo '<script> document.location = "'.wa()->getUrl(true).'#/topic/'.$topic['id'].'/"; </script>';
                exit;
            }
            throw new waRightsException('Access denied');
        }

        // Hub colors
        $hub_params_model = new hubHubParamsModel();
        $hub_colors = $hub_params_model->getByField('name', 'color', 'hub_id');
        foreach ($hubs as &$h) {
            if (empty($hub_colors[$h['id']])) {
                $h['hub_color'] = 'white';
            } else {
                $h['hub_color'] = $hub_colors[$h['id']]['value'];
            };
        }
        unset($h);

        // All categories grouped by hub
        $categories = array();
        $category_model = new hubCategoryModel();
        foreach ($category_model->getFullTree() as $c) {
            if ($c['type'] == 1) {
                continue; // ignore dynamic categories
            }
            if (empty($categories[$c['hub_id']])) {
                $categories[$c['hub_id']] = array();
            }
            $categories[$c['hub_id']][] = array(
                'id'   => $c['id'],
                'name' => $c['name'],
            );
        }

        // All topic types grouped by hub
        $hub_type_ids = array_fill_keys(array_keys($hubs), array());
        $hub_types_model = new hubHubTypesModel();
        foreach ($hub_types_model->getAll() as $row) {
            isset($hub_type_ids[$row['hub_id']]) || $hub_type_ids[$row['hub_id']] = array();
            $hub_type_ids[$row['hub_id']][] = $row['type_id'];
        }

        $users = $this->getUsers('backend');
        $access_level = wa()->getUser()->getRights('hub', 'hub.'.$hub_id);
        $can_change_author = $access_level >= hubRightConfig::RIGHT_FULL;
        if (empty($users[$topic['contact_id']])) {
            try {
                $c = new waContact($topic['contact_id']);
                $users[$topic['contact_id']] = $c->getName();
            } catch(waException $e) {
                $users[$topic['contact_id']] = 'contact_id '.$topic['contact_id'];
            }
        }


        if ($id) {
            $this->view->assign(
                array(
                    'topic'              => $topic,
                    'voted'              => $voted,
                    'hub_id'             => $hub_id,
                    'hubs'               => $hubs,
                    'og'                 => $og,
                    'types'              => $topic_types,
                    'categories'         => $categories,
                    'hub_type_ids'       => $hub_type_ids,
                    'hub_url_templates'  => $hub_url_templates,
                    'users'              => $users,
                    'user_id'            => wa()->getUser()->getId(),
                    'can_change_author'  => $can_change_author,
                    'params_string'      => $params_string,
                    'allow_commenting'   => !$topic['type'] || empty($topic['type']['settings']) || !empty($topic['type']['settings']['commenting']),
                    'topic_public_url'   => $topic_public_url,
                    'comments'           => $comments,
                    'possible_badges'    => $possible_badges,
                    'comments_count'     => $topic['comments_count'],
                    'current_author'     => hubHelper::getAuthor($this->getUserId()),
                    'notifications_sent' => waRequest::request('notifications_sent'),
                    'follow'             => $follow,
                    'followers'          => $followers,
                )
            );
        } else {
            $this->view->assign(
                array(
                    'topic'              => $topic,
                    'hub_id'             => $hub_id,
                    'hubs'               => $hubs,
                    'og'                 => $og,
                    'types'              => $topic_types,
                    'categories'         => $categories,
                    'hub_type_ids'       => $hub_type_ids,
                    'hub_url_templates'  => self::getHubPublicUrlTemplates(),
                    'users'              => $users,
                    'user_id'            => wa()->getUser()->getId(),
                    'can_change_author'  => $can_change_author,
                    'params_string'      => $params_string,
                )
            );
        }
    }

    protected function getPreviewHash()
    {
        $hash = $this->appSettings('preview_hash');
        if ($hash) {
            $hash_parts = explode('.', $hash);
            if (time() - $hash_parts[1] > 14400) {
                $hash = '';
            }
        }
        if (!$hash) {
            $hash = uniqid().'.'.time();
            $app_settings_model = new waAppSettingsModel();
            $app_settings_model->set($this->getAppId(), 'preview_hash', $hash);
        }

        return md5($hash);
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
        $tags = $tag_model->getByField('id', $tag_ids, 'name');
        return array_keys($tags);
    }

    /**
     * @param int $topic_id
     * @return array|null
     * @throws waException
     */
    public static function getCategories($topic_id)
    {
        $topic_categories_model = new hubTopicCategoriesModel();
        $category_ids = array_keys($topic_categories_model->getByField('topic_id', $topic_id, 'category_id'));
        $category_model = new hubCategoryModel();
        return $category_model->getById($category_ids);
    }

    public function getUsers($rights)
    {
        $rights_model = new waContactRightsModel();
        $ids = $rights_model->getUsers('hub', $rights);
        $contact_model = new waContactModel();
        return $contact_model->getName($ids);
    }

    public static function getHubPublicUrlTemplates()
    {
        $routing = wa()->getRouting();

        // Routing manipulation will break frontend, so make sure this is never used there.
        if ($routing->getRoute() !== null) {
            throw new waException('getTopicPublicUrlTemplate() is only to be used in backend!');
        }

        // Prepare system stuff so that waRouting->getUrl() works correctly.
        $result = array();
        foreach ($routing->getByApp('hub') as $domain => $routes) {
            foreach ($routes as $r) {
                if (!empty($r['hub_id'])) {
                    $routing->setRoute($r, $domain);
                    $result[$r['hub_id']] = waIdna::dec($routing->getUrl(
                        '/frontend/topic',
                        array(
                            'id'        => '%topic_id%',
                            'topic_url' => '%topic_url%',
                        ),
                        true
                    ));
                }
            }
        }

        // Revert routing settings, just in case.
        $routing->setRoute(null);

        return $result;
    }
}
