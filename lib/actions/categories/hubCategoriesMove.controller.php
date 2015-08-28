<?php

class hubCategoriesMoveController  extends waJsonController
{
    public function execute()
    {
        $id = waRequest::post('id', 0, 'int');
        $before_id = waRequest::post('before_id', 0, 'int');

        $model = new hubCategoryModel();
        if (!$id || !$model->move($id, $before_id)) {
            $this->errors = array(_w('Error when move'));
        }
    }
}
