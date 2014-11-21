<?php
class hubContactsDeleteHandler extends waEventHandler
{
    /**
     * @param int[] $params Deleted contact_id
     * @see waEventHandler::execute()
     * @return void
     */
    public function execute(&$params)
    {
        $contact_ids = $params;
        $m = new waModel();
        foreach(array(
            array('hub_author', 'contact_id'),
            array('hub_staff', 'contact_id'),           // !!! no index
            array('hub_vote', 'contact_id'),            // !!! no index
            array('hub_following', 'contact_id'),
            //array('hub_comment', 'contact_id'),       // !!! no index
            //array('hub_topic', 'contact_id'),         // !!! no index
        ) as $data) {
            list($table, $field) = $data;
            $sql = "DELETE FROM $table
                    WHERE $field IN (".implode(',', $contact_ids).")";
            $m->exec($sql);
        }
    }
}

