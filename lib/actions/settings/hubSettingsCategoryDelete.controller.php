<?php

class hubSettingsCategoryDeleteController extends waJsonController
{
    public function execute()
    {
        $id = (int)waRequest::post('id');
        $category_model = new hubCategoryModel();
        if ($category = $category_model->getById($id)) {
            if ($this->getUser()->getRights($this->getApp(), 'hub.'.$category['hub_id']) < hubRightConfig::RIGHT_FULL) {
                throw new waRightsException('Access denied');
            }
        } else {
            throw new waException('Category not found', 404);
        }

        switch (waRequest::post('delete')) {
            case 'move':
                $topic_categories_model = new hubTopicCategoriesModel();
                $target_category_id = waRequest::post('target_category', 0, waRequest::TYPE_INT);
                $topic_categories_model->updateByField('category_id', $id, array('category_id' => $target_category_id));
                break;
            default:
                break;

        }

        if (!$category_model->deleteById($id)) {
            $this->errors = array(_w('Error when deleting'));
        }
    }
}
