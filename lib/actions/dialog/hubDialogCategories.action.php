<?php

class hubDialogCategoriesAction extends waViewAction
{
    public function execute()
    {
        $hub_id = waRequest::get('hub_id', null, 'int');
        $this->view->assign('categories', $this->getCategories($hub_id));
    }

    public function getCategories($hub_id)
    {
        $category_model = new hubCategoryModel();
        $categories = $category_model->getByHub($hub_id);

        return $categories;
    }
}
