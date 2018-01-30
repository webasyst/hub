<?php

class hubContactsLinksHandler extends waEventHandler
{
    public function execute(&$params)
    {
        $result = array();
        $contacts = $params;
        if (is_array($contacts)) {
            $cs = array();
            foreach ($contacts as $v) {
                if ( ( $v = (int)$v)) {
                    $cs[] = $v;
                }
            }
            $contacts = $cs;
        } else {
            if ( ( $contacts = (int)$contacts)) {
                $contacts = array($contacts);
            }
        }

        if (!$contacts) {
            return null;
        }

        $m = new waModel();

        waLocale::loadByDomain('hub');

        foreach(array(
            //array('hub_author', 'contact_id', 'Author'),
            array('hub_comment', 'contact_id', _w('Comment author')),               // !!! no index
            array('hub_staff', 'contact_id', _w('Staff')),                          // !!! no index
            array('hub_topic', 'contact_id', _w('Topic author')),                   // !!! no index
            //array('hub_vote', 'contact_id', 'Voted for topics and comments'),   // !!! no index
            //array('hub_following', 'contact_id', 'Follows topics'),
        ) as $data) {
            list($table, $field, $role) = $data;
            $role = _wd('hub', $role);
            $sql = "SELECT $field AS id, count(*) AS n
                    FROM $table
                    WHERE $field IN (".implode(',', $contacts).")
                    GROUP BY $field";
            foreach ($m->query($sql) as $row) {
                $result[$row['id']][] = array(
                    'role' => $role,
                    'links_number' => $row['n'],
                );
            }
        }
        return $result ? $result : null;
    }
}

