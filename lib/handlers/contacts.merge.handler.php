<?php

class hubContactsMergeHandler extends waEventHandler
{
    public function execute(&$params)
    {
        $master_id = $params['id'];
        $merge_ids = $params['contacts'];
        if (!$master_id || !$merge_ids) {
            return null;
        }

        $m = new waModel();

        foreach(array(
            array('hub_author', 'contact_id'),
            array('hub_staff', 'contact_id'),           // !!! no index
            array('hub_vote', 'contact_id'),            // !!! no index
            array('hub_following', 'contact_id'),
            array('hub_comment', 'contact_id'),         // !!! no index
            array('hub_topic', 'contact_id'),           // !!! no index
        ) as $pair)
        {
            list($table, $field) = $pair;
            $sql = "UPDATE $table SET $field = :master WHERE $field in (:ids)";
            $m->exec($sql, array('master' => $master_id, 'ids' => $merge_ids));
        }

        return null;
    }
}

