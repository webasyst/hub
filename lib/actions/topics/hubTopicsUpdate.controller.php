<?php

class hubTopicsUpdateController extends waJsonController
{
    public function execute()
    {
        //
        $topic_ids = waRequest::post('ids');
        if (!is_array($topic_ids)) {
            $topic_ids = array_map('trim', explode(',', $topic_ids));
        }
        $topic_ids = array_unique(array_map('intval', $topic_ids));
        $topic_model = new hubTopicModel();

        $target_category_id = waRequest::post('category_id', 0, waRequest::TYPE_INT);
        $target_hub_id = waRequest::post('hub_id', 0, waRequest::TYPE_INT);

        $topic_model->changeHub(null, $target_hub_id, $topic_ids);
        $topic_categories_model = new hubTopicCategoriesModel();
        if ($target_category_id) {
            $topic_categories_model->updateByField(array('topic_id' => $topic_ids), array('category_id' => $target_category_id));
        } else {
            $topic_categories_model->deleteByField(array('topic_id' => $topic_ids));
        }
    }
}
