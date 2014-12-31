<?php
/**
 * Saves manual ordering of topics inside static category.
 */
class hubTopicsMoveController extends waJsonController
{
    public function execute()
    {
        $topic_id = waRequest::post('id', 0, 'int');
        $category_id = waRequest::post('category_id', 0, 'int');
        $before_id = waRequest::post('before_id', 0, 'int');

        if ($topic_id && $category_id) {
            $tc_model = new hubTopicCategoriesModel();
            $tc_model->move($topic_id, $before_id, $category_id);
        }
    }
}

