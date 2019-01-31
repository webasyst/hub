<?php

class hubBackendAutocompleteController extends waController
{
    protected $limit = 10;

    public function execute()
    {
        $data = array();
        $q = waRequest::get('term', '', waRequest::TYPE_STRING_TRIM);
        if ($q) {
            $type = preg_replace('@[^a-z]+@', '', waRequest::get('type', 'contact', waRequest::TYPE_STRING_TRIM));
            $method = $type.'Autocomplete';
            if (method_exists($this, $method)) {
                $data = $this->{$method}($q);
            } else {
                $type = null;
            }
            $data = $this->formatData($data, $type);
        }
        echo json_encode($data);
    }

    private function formatData($data, $type)
    {
        return $data;
    }

    private function usergroupAutocomplete($q)
    {
        $m = new waModel();
        $sqls = array();

        $sqls[] = "SELECT g.* FROM wa_group AS g WHERE g.name LIKE '%".$m->escape($q, 'like')."%' LIMIT {LIMIT}";

        // Try to find users first. If not enough, look for groups.
        $result = $this->contactAutocomplete($q);
        $limit = 5;
        $term_safe = htmlspecialchars($q);
        $group_ids = array();
        foreach ($sqls as $sql) {
            if (count($result) >= $limit) {
                break;
            }
            foreach ($m->query(str_replace('{LIMIT}', $limit, $sql)) as $g) {
                if (empty($result[-$g['id']])) {
                    $g['name'] = waContactNameField::formatName($g);
                    $name = $this->prepare($g['name'], $term_safe);
                    $result[-$g['id']] = array(
                        'id'    => -$g['id'],
                        'value' => -$g['id'],
                        'name'  => $g['name'],
                        'label' => _ws('Group').': '.$name,
                        'users' => array(),
                    );
                    $group_ids[] = $g['id'];
                    if (count($result) >= $limit) {
                        break 2;
                    }
                }
            }
        }

        // Find users by group id
        if ($group_ids && waRequest::request('fullgroups')) {
            $sql = "SELECT c.*, ug.group_id
                    FROM wa_contact AS c
                        JOIN wa_user_groups AS ug
                            ON c.id=ug.contact_id
                    WHERE ug.group_id IN (?)
                        AND c.is_user=1";
            foreach ($m->query($sql, array($group_ids)) as $row) {
                $result[-$row['group_id']]['users'][] = array(
                    'id' => $row['id'],
                    'name' => $row['name'],
                    'userpic20' => waContact::getPhotoUrl($row['id'], $row['photo'], 20, 20, 'person'),
                );
            }
        }

        return array_values($result);
    }

    private function contactAutocomplete($q, $limit = null)
    {
        $m = new waModel();

        // The plan is: try queries one by one (starting with fast ones),
        // until we find 5 rows total.
        $sqls = array();

        // Name starts with requested string
        $sqls[] = "SELECT c.*
                   FROM wa_contact AS c
                   WHERE c.is_user AND c.name LIKE '".$m->escape($q, 'like')."%'
                   LIMIT {LIMIT}";

        // Email starts with requested string
        $sqls[] = "SELECT c.*, e.email
                   FROM wa_contact AS c
                       JOIN wa_contact_emails AS e
                           ON e.contact_id=c.id
                   WHERE c.is_user AND  e.email LIKE '".$m->escape($q, 'like')."%'
                   LIMIT {LIMIT}";

        // Phone contains requested string
        if (preg_match('~^[wp0-9\-\+\#\*\(\)\. ]+$~', $q)) {
            $dq = preg_replace("/[^\d]+/", '', $q);
            $sqls[] = "SELECT c.*, d.value as phone
                       FROM wa_contact AS c
                           JOIN wa_contact_data AS d
                               ON d.contact_id=c.id AND d.field='phone'
                       WHERE c.is_user AND d.value LIKE '%".$m->escape($dq, 'like')."%'
                       LIMIT {LIMIT}";
        }

        // Name contains requested string
        $name_ar = preg_split('/\s+/', $q);
        if (count($name_ar) == 2) {
            $name_condition =
                "((c.firstname LIKE '%".$m->escape($name_ar[0], 'like')."%' AND c.lastname LIKE '%".$m->escape($name_ar[1], 'like')."%')
                    OR (c.firstname LIKE '%".$m->escape($name_ar[1], 'like')."%' AND c.lastname LIKE '%".$m->escape($name_ar[0], 'like')."%'))";
        } else {
            $name_condition = "c.name LIKE '_%".$m->escape($q, 'like')."%'";
        }
        $sqls[] = "SELECT c.*
                   FROM wa_contact AS c
                   WHERE $name_condition
                    AND c.is_user
                   LIMIT {LIMIT}";

        // Email contains requested string
        $sqls[] = "SELECT c.*, e.email
                   FROM wa_contact AS c
                       JOIN wa_contact_emails AS e
                           ON e.contact_id=c.id
                   WHERE c.is_user AND e.email LIKE '_%".$m->escape($q, 'like')."%'
                   LIMIT {LIMIT}";

        $limit = $limit !== null ? $limit : 5;
        $result = array();
        $term_safe = htmlspecialchars($q);
        foreach ($sqls as $sql) {
            if (count($result) >= $limit) {
                break;
            }
            foreach ($m->query(str_replace('{LIMIT}', $limit, $sql)) as $c) {
                if (empty($result[$c['id']])) {
                    $c['name'] = waContactNameField::formatName($c);
                    $name = $this->prepare($c['name'], $term_safe);
                    $email = $this->prepare(ifset($c['email'], ''), $term_safe);
                    $phone = $this->prepare(ifset($c['phone'], ''), $term_safe);
                    $phone && $phone = '<i class="icon16 phone"></i>'.$phone;
                    $email && $email = '<i class="icon16 email"></i>'.$email;
                    $photo_url = waContact::getPhotoUrl($c['id'], $c['photo'], 20, 20, 'person');
                    $result[$c['id']] = array(
                        'id'    => $c['id'],
                        'value' => $c['id'],
                        'name'  => $c['name'],
                        'userpic20' => $photo_url,
                        'label' => '<i class="icon16 userpic20" style="background-image: url('.$photo_url.');"></i>'.
                                        implode(' ', array_filter(array($name, $email, $phone))),
                    );
                    if (count($result) >= $limit) {
                        break 2;
                    }
                }
            }
        }

        return array_values($result);
    }

    // Helper for contactsAutocomplete()
    protected function prepare($str, $term_safe)
    {
        return preg_replace('~('.preg_quote($term_safe, '~').')~ui', '<span class="bold highlighted">\1</span>', htmlspecialchars($str));
    }
}
