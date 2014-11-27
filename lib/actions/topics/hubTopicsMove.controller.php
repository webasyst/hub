<?php
/**
 * Saves manual ordering of topics inside static category.
 */
class hubTopicsMoveController extends waJsonController
{
    public function execute()
    {
        $tc_model = new hubTopicCategoriesModel();
        $topic_id = waRequest::post('id');
        $category_id = waRequest::post('category_id');
        $before_id = waRequest::post('before_id');
        $tc_model->move($topic_id, $before_id, $category_id);
    }
}
