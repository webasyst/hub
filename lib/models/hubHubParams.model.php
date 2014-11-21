<?php

class hubHubParamsModel extends waModel
{
    protected $table = 'hub_hub_params';
    protected $id = 'hub_id';

    /**
     * @param int|int[] $hub_id
     * @return array
     */
    public function getParams($hub_id)
    {
        $rows = $this->getByField($this->id, $hub_id, true);
        $params = is_array($hub_id) ? array_fill_keys($hub_id, array()) : array();
        foreach ($rows as $row) {
            if (is_array($hub_id)) {
                $params[$row[$this->id]][$row['name']] = $row['value'];
            } else {
                $params[$row['name']] = $row['value'];
            }
        }
        return $params;
    }
}
