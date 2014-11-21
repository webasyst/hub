<?php

class hubHubTypesModel extends waModel
{
    protected $table = 'hub_hub_types';

    /**
     * @param int $hub_id
     * @param array $type_ids
     */
    public function setTypes($hub_id, $type_ids)
    {
        $type_ids = (array)$type_ids;
        $old_type_ids = $this->select('type_id')->where('hub_id = ?', $hub_id)->fetchAll(null, true);
        $del = array_diff($old_type_ids, $type_ids);
        if ($del) {
            $this->deleteByField(array('hub_id' => $hub_id, 'type_id' => $del));
        }
        $add = array_diff($type_ids, $old_type_ids);
        if ($add) {
            $this->multipleInsert(array('hub_id' => $hub_id, 'type_id' => $add));
        }
    }

    /**
     * @param int $type_id
     * @param array $hub_ids
     */
    public function setHubs($type_id, $hub_ids)
    {
        $hub_ids = (array)$hub_ids;
        $old_hub_ids = $this->select('hub_id')->where('type_id = ?', $type_id)->fetchAll(null, true);
        $del = array_diff($old_hub_ids, $hub_ids);
        if ($del) {
            $this->deleteByField(array('type_id' => $type_id, 'hub_id' => $del));
        }
        $add = array_diff($hub_ids, $old_hub_ids);
        if ($add) {
            $this->multipleInsert(array('type_id' => $type_id, 'hub_id' => $add));
        }
    }

    /**
     * @param int $hub_id
     * @return array
     */
    public function getTypes($hub_id)
    {
        $sql = 'SELECT t.* FROM ' . $this->table . ' ht JOIN hub_type t ON ht.type_id = t.id
                WHERE ht.hub_id = i:0';
        return $this->query($sql, $hub_id)->fetchAll('id');
    }

    /**
     * @param int $hub_id
     * @return array
     */
    public function getTypeIds($hub_id)
    {
        $sql = 'SELECT t.id FROM ' . $this->table . ' ht JOIN hub_type t ON ht.type_id = t.id
                WHERE ht.hub_id = i:0';
        return $this->query($sql, $hub_id)->fetchAll(null, true);
    }


    /**
     * @param int $type_id
     * @return array
     */
    public function getHubs($type_id)
    {
        $sql = 'SELECT h.* FROM ' . $this->table . ' ht JOIN hub_hub h ON ht.type_id = h.id
                WHERE ht.type_id = i:0';
        return $this->query($sql, $type_id)->fetchAll('id');
    }

    /**
     * @param int $type_id
     * @return array
     */
    public function getHubIds($type_id)
    {
        $sql = 'SELECT h.id FROM ' . $this->table . ' ht JOIN hub_hub h ON ht.type_id = h.id
                WHERE ht.type_id = i:0';
        return $this->query($sql, $type_id)->fetchAll(null, true);
    }
}
