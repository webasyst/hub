<?php

class hubFrontendCategoryAction extends hubFrontendAction
{
    public function execute()
    {
        $category_model = new hubCategoryModel();
        $category = $category_model->getByField(array('url' => waRequest::param('category_url'), 'hub_id' => waRequest::param('hub_id')));
        if (!$category) {
            throw new waException(_w('Category not found'), 404);
        }

        $sorting = hubHelper::getSorting(true);
        // Ordering
        $order = waRequest::request('sort', '', 'string_trim');
        if (!$order && $category['sorting']) {
            $order = $category['sorting'];
        }
        if (empty($sorting[$order])) {
            $order = key($sorting);
        }

        if ($category['type'] && (preg_match("/^type_id\=(\d+)$/uis", $category['conditions'], $match))) {
            $type_id = $match[1];
            $type_model = new hubTypeModel();
            $type = $type_model->getById($type_id);
            $base_types = hubHelper::getBaseTypes();
            if ($type && isset($base_types[$type['type']])) {
                if (isset($base_types[$type['type']]['sorting'])) {
                    $tmp = array();
                    foreach ($base_types[$type['type']]['sorting'] as $k) {
                        $tmp[$k] = $sorting[$k];
                    }
                    $sorting = $tmp;
                } else {
                    $sorting = array();
                }
            }
        }
        $category_og_model = new hubCategoryOgModel();
        $route = wa()->getRouting()->getRoute();
        $og = $category_og_model->get($category['id']) + array(
            'site_name'   => ifset($route, 'og_site_name', ''),
            'locale'      => ifset($route, 'og_locale', wa()->getLocale()),
            'type'        => 'website',
            'url'         => wa()->getConfig()->getHostUrl() . wa()->getConfig()->getRequestUrl(false, true),
        );
        if (!isset($og['title']) && !isset($og['description'])) {
            $og['title'] = $category['meta_title'];
            $og['description'] = $category['meta_description'];
        }

        // All topics of this category
        $c = new hubTopicsCollection('category/'.$category['id']);
        $this->setCollection($c);

        $this->getResponse()->setTitle(ifempty($category['meta_title'], $category['name']));
        $this->getResponse()->setMeta('keywords', $category['meta_keywords']);
        $this->getResponse()->setMeta('description', $category['meta_description']);
        foreach ($og as $property => $content) {
            if (strlen($content)) {
                $this->getResponse()->setOGMeta('og:'.$property, $content);
            }
        }

        $this->view->assign('breadcrumbs', $this->breadcrumbs($category['id']));
        /**
         * @event frontend_category
         * @return array[string]string $return[%plugin_id%] html output for category
         */
        $this->view->assign('frontend_category', wa()->event('frontend_category', $category));
        $this->view->assign('category', $category);
        $this->view->assign('sorting', $sorting);
        $this->view->assign('sort', $order);

        $this->setThemeTemplate('category.html');
    }

    public function breadcrumbs($category_id)
    {
        return hubHelper::getBreadcrumbs($category_id);
    }
}
