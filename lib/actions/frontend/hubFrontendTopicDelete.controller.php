<?php

class hubFrontendTopicDeleteController extends waJsonController
{
    public function execute()
    {
        if (waRequest::method() == 'post') {
            $id = waRequest::param('id');
            $topic_model = new hubTopicModel();
            $topic = $topic_model->getById($id);

            if (!$topic || $topic['contact_id'] != $this->getUserId() ||
                (time() - strtotime($topic['create_datetime']) > 15 * 60)
            ) {

                wa_dump(!$topic, $topic['contact_id'] != $this->getUserId(), time() - strtotime($topic['create_datetime']) > 15 * 60); // !!!
                throw new waRightsException(_ws('Access denied'));
            }

            // get main category
            $topic_categories_model = new hubTopicCategoriesModel();
            $category_id = $topic_categories_model
                ->select('category_id')
                ->where('topic_id = i:topic_id', array('topic_id' => $id))
                ->order('sort')
                ->limit(1)
                ->fetchField();

            // delete topic
            $topic_model->deleteById($id);

            // redirect to category or main page
            if ($category_id) {
                $category_model = new hubCategoryModel();
                $category = $category_model->getById($category_id);
                $url = wa()->getRouteUrl('/frontend/category', array('category_url' => $category['url'], 'hub_id' => $topic['hub_id']));
            } else {
                $url = wa()->getRouteUrl('/frontend', array('hub_id' => $topic['hub_id']));
            }
            $this->response = $url;
        } else {
            throw new waRightsException(_ws('Access denied'));
        }
    }
}
