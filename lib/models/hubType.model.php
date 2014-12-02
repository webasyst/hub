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

    // Update `sort` of $id so that $id goes right next to $prev_id,
    // without changing relative order of any other items.
    public function sortMove($id, $prev_id)
    {
        if ($prev_id && $prev = $this->getById($prev_id)) {
            $sort = $prev['sort'] + 1;
        } else {
            $sort = 0;
        }

        $this->exec(
            "UPDATE ".$this->table."
             SET sort = sort + 1
             WHERE sort >= ?",
            $sort
        );

        $this->updateById($id, array('sort' => $sort));
    }

    public function getTypes()
    {
        $sql = "SELECT * FROM {$this->table} ORDER BY sort, id";
        return $this->query($sql)->fetchAll('id');
    }
}

