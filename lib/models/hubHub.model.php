<?php

class hubHubModel extends waModel
{
    protected $table = 'hub_hub';

    public function add($data)
    {
        $data['create_datetime'] = date('Y-m-d H:i:s');
        $data += array(
            'glyph' => '',
        );
        return $this->insert($data);
    }

    public function getNames($public_only = false)
    {
        $q = $this->select('id,name');
        if ($public_only) {
            $q->where('status = 1');
        }
        return $q->fetchAll('id', true);
    }

    /**
     * Delete hub and related data
     * @param $value
     * @return bool
     * @throws waException
     */
    public function deleteById($value)
    {
        $models = array(
            new hubTopicModel(),
            new hubHubParamsModel(),
            new hubHubTypesModel(),
            new hubCategoryModel(),
            new hubTagModel(),
            new hubStaffModel(),
        );
        foreach ($models as $model) {
            /**
             * @var waModel $model
             */
            $model->deleteByField('hub_id', $value);
        }

        return parent::deleteById($value);
    }


    public function updateTopicsCount($hub_id)
    {
        if(empty($hub_id)) {
            return false;
        }
        if(!is_array($hub_id)) {
            $hub_id = array($hub_id);
        }

        $sql = "UPDATE `{$this->table}` AS h
                SET topics_count =
                    (SELECT count(*) FROM hub_topic AS t
                     WHERE t.hub_id = h.id
                         AND status = 1)
                WHERE h.id IN (?)";
        return $this->exec($sql, array($hub_id));
    }
}
