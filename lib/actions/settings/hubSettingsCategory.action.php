<?php

class hubSettingsCategoryAction extends waViewAction
{
    public function execute()
    {

        $category_model = new hubCategoryModel();

        if ($id = max(0, waRequest::request('id', 0, waRequest::TYPE_INT))) {
            $category = $category_model->getById($id);
            if (!$category) {
                throw new waException(_w("Topic not found", 404));
            }

            $category['conditions'] = hubHelper::parseConditions($category['conditions']);
            //force recount topics count
            $topic_categories_model = new hubTopicCategoriesModel();
            $category['topics_count'] = $topic_categories_model->countByField('category_id', $id);

            $category_og_model = new hubCategoryOgModel();
            $og = $category_og_model->get($id);
            $og += hubCategoryOgModel::getEmptyData();
        } else {
            $category = array(
                'description'     => '',
                'hub_id'          => waRequest::request('hub_id', 0, waRequest::TYPE_INT),
                'type'            => hubCategoryModel::TYPE_STATIC,
                'sorting'         => '',//TODO setup default sorting
                'sorting_enabled' => false,
                'glyph'           => '',
                'meta_title'      => '',
                'meta_keywords'   => '',
                'meta_description' => '',
            );
            $og = hubCategoryOgModel::getEmptyData();
        }

        $hub = hubHelper::getHub($category['hub_id']);
        if (!$hub) {
            throw new waException(_w('Hub not found', 404));
        }

        if ($id && $category['topics_count'] && ($category['type'] == hubCategoryModel::TYPE_STATIC)) {
            $hub['categories'] = $category_model->getByField(
                array(
                    'hub_id' => $hub['id'],
                    'type'   => hubCategoryModel::TYPE_STATIC,
                ),
                true
            );
        } else {
            $hub['categories'] = array();
        }

        $hub_types_model = new hubHubTypesModel();
        $this->view->assign('types', $hub_types_model->getTypes($category['hub_id']));

        // Tags assigned for this category
        $assigned_tags = array();
        $cloud = $this->getCloud($category);
        foreach ($cloud as $tag) {
            if ($tag['checked']) {
                $assigned_tags[$tag['id']] = $tag;
            }
        }

        $logo_base_url = wa()->getDataUrl(sprintf('categories/%d/', $id), true, $this->getAppId());

        // Frontend URLs for this category
        $routes = hubHelper::getUrls($category['hub_id'], '%url%');
        foreach ($routes as &$r) {
            $r['start'] = preg_replace('~%url%.*$~', '', $r['url']);
            $r['end'] = preg_replace('~^.*%url%~', '', $r['url']);
        }
        unset($r);

        $this->view->assign(compact('category', 'hub', 'cloud', 'assigned_tags', 'logo_base_url'));
        $this->view->assign('hub_context', $hub);
        $this->view->assign('hub_color', ifset($hub['params']['color']));
        $this->view->assign('routes', $routes);
        $this->view->assign('sorts', hubHelper::getSorting());
        $this->view->assign('base_types', hubHelper::getBaseTypes());
        $this->view->assign('og', $og);
    }

    private function getCloud($category)
    {
        if (empty($category['id']) || ($category['type'] == hubCategoryModel::TYPE_DYNAMIC)) {
            $tag_model = new hubTagModel();
            if ($cloud = $tag_model->getByField('hub_id', $category['hub_id'], $tag_model->getTableId())) {
                $tags = array_fill_keys((array)ifempty($category['conditions']['tag_id'], array()), true);
                foreach ($cloud as $id => &$item) {
                    $item['checked'] = !empty($tags[$id]);
                }
                unset($item);
            }
        } else {
            $cloud = array();
        }

        return $cloud;
    }
}
