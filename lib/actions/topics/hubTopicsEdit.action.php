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
            $topic['categories'] = $this->getCategories($id);
            $hub_id = $topic['hub_id'];
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
        }

        // Check access rights
        if (!$hubs || empty($hubs[$hub_id])) {
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

        $this->view->assign(
            array(
                'topic'             => $topic,
                'hub_id'            => $hub_id,
                'hubs'              => $hubs,
                'types'             => $topic_types,
                'categories'        => $categories,
                'hub_type_ids'      => $hub_type_ids,
                'hub_url_templates' => self::getHubPublicUrlTemplates(),
                'users'             => $this->getUsers('backend'),
                'user_id'           => wa()->getUser()->getId(),
            )
        );
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

    public function getCategories($topic_id)
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
                    $result[$r['hub_id']] = $routing->getUrl(
                        '/frontend/topic',
                        array(
                            'id'        => '%topic_id%',
                            'topic_url' => '%topic_url%',
                        ),
                        true
                    );
                }
            }
        }

        // Revert routing settings, just in case.
        $routing->setRoute(null);

        return $result;
    }
}
