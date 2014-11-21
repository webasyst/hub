<?php

class hubTypeModel extends waModel
{
    protected $table = 'hub_type';

    public function deleteById($value)
    {
        $hub_types_model = new hubHubTypesModel();
        $hub_types_model->deleteByField('type_id', $value);

        $topic_model = new hubTopicModel();
        $topic_model->deleteByField('type_id', $value);

        $category_model = new hubCategoryModel();
        foreach ((array)$value as $type_id) {
            $category_model->deleteByField('conditions', sprintf('type_id=%d', $type_id));
        }
        return parent::deleteById($value);
    }
}
