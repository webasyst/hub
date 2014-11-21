<?php

class hubTopicsBulkMoveController extends waJsonController
{
    private $target_category_id;
    private $target_hub_id;

    public function execute()
    {

        $ids = waRequest::post('topic_ids');
        if (!is_array($ids)) {
            $ids = explode(',', $ids);
        }
        if ($ids = array_filter(array_map('intval', array_map('trim', $ids)))) {
            $this->initTarget();
            $topics = hubHelper::checkTopicRights($ids, $this->getUser(), $this->target_hub_id);
            if ($topics = array_filter($topics)) {
                $ids = array_keys($topics);
                $topic_model = new hubTopicModel();
                $topic_model->changeHub(null, $this->target_hub_id, $ids);

                $topic_categories_model = new hubTopicCategoriesModel();
                $topic_categories_model->deleteByField('topic_id', $ids);
                if ($this->target_category_id) {
                    $data = array();
                    foreach ($ids as $topic_id) {
                        $data[] = array(
                            'topic_id'    => $topic_id,
                            'category_id' => $this->target_category_id,
                            'sort'        => 1,
                        );
                    }

                    $topic_categories_model->multipleInsert($data);

                }
            } else {
                //nothing to move
            }
        }
    }

    private function initTarget()
    {
        if ($this->target_category_id = waRequest::post('category_id', 0, waRequest::TYPE_INT)) {
            $category_model = new hubCategoryModel();
            if ($category = $category_model->getById($this->target_category_id)) {
                $this->target_hub_id = (int)$category['hub_id'];
            } else {
                throw new waException('Category not found', 404);
            }
        } else {
            $this->target_hub_id = waRequest::post('hub_id', 0, waRequest::TYPE_INT);
        }
        if (empty($this->target_hub_id) || empty($this->target_hub_id)) {
            throw new waException('Empty required post data');
        }
    }
}
