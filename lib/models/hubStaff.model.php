<?php

class hubStaffModel extends waModel
{
    protected $table = 'hub_staff';
    protected $id = 'hub_id';

    /**
     * @param int $hub_id
     * @return array
     */
    public function getStaff($hub_id)
    {
        $rows = $this->getByField('hub_id', (int)$hub_id, true);
        $staff = array();
        foreach ($rows as $row) {
            $staff[$row['contact_id']] = array(
                'id' => $row['contact_id'],
                'name'        => $row['name'],
                'badge'       => $row['badge'],
                'badge_color' => $row['badge_color'],
            );
        }
        if ($staff) {
            $contacts_collection = new waContactsCollection('id/'.implode(',', array_keys($staff)), array('photo_url_2x' => true));
            $contacts = $contacts_collection->getContacts('*,email,photo_url_96,photo_url_50,photo_url_32,photo_url_20');
            foreach ($staff as $contact_id => $row) {
                if (isset($contacts[$contact_id])) {
                    $c = $contacts[$contact_id];
                    $staff[$contact_id]['contact'] = new waContact($c);
                    if ($c['email']) {
                        foreach (array(20, 50, 32, 96) as $size) {
                            $staff[$contact_id]['photo_url_'.$size] = hubHelper::getGravatarUrl($c['email'][0], $size, $c['photo_url_20']);
                        }
                    }
                } else {
                    unset($staff[$contact_id]);
                }
            }
        }
        return $staff;
    }

    public function deleteStaff($hub_id, $actual_contact_ids = array())
    {
        $sql = <<<SQL
        DELETE FROM `{$this->table}` WHERE `hub_id` = i:hub_id
SQL;
        if ($actual_contact_ids) {
            $sql .= ' AND `contact_id` NOT IN (i:actual_contact_ids)';
        }
        return $this->exec($sql, compact('hub_id', 'actual_contact_ids'));
    }
}
