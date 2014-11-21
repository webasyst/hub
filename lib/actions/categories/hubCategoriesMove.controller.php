<?php

class hubCategoriesMoveController  extends waJsonController
{
    public function execute()
    {
        $id = (int) waRequest::post('id');
        $before_id = (int) waRequest::post('before_id');

        $model = new hubCategoryModel();
        
        if (!$model->move($id, $before_id)) {
            $this->errors = array(_w('Error when move'));
        }
    }
}
