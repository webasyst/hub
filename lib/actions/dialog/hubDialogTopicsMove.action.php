<?php

class hubDialogTopicsMoveAction extends waViewAction
{
    public function execute()
    {
        $config = $this->getConfig();
        /**
         * @var hubConfig $config
         */
        $hubs = $config->getAvailableHubs(hubRightConfig::RIGHT_FULL);

        /* hub_id|category_id for users & filters also for $admin
         * */

        $category_model = new hubCategoryModel();
        $category_search = array(
            'hub_id' => array_keys($hubs),
            'type'   => hubCategoryModel::TYPE_STATIC,
        );
        $this->view->assign('current_category_id', waRequest::get('category_id', 0, waRequest::TYPE_INT));
        $this->view->assign('current_hub_id', waRequest::get('hub_id', 0, waRequest::TYPE_INT));
        foreach ($category_model->getByField($category_search, $category_model->getTableId()) as $id => $category) {
            $h = &$hubs[$category['hub_id']];
            if (!isset($h['categories'])) {
                $h['categories'] = array();
            }
            $h['categories'][$id] = $category;
            unset($h);
        }
        $this->view->assign('hubs', $hubs);
    }
}
