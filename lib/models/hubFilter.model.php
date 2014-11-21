<?php

class hubFilterModel extends waModel
{
    protected $table = 'hub_filter';

    public function add($data)
    {
        if (!isset($data['contact_id'])) {
            $data['contact_id'] = -(int)wa()->getUser()->getId();
        }
        $data['conditions'] = json_encode($data['conditions']);
        $data['update_datetime'] = date('Y-m-d H:i:s');
        return $this->insert($data);
    }

    /**
     * @param int $id
     * @param waUser $user
     * @param bool $can_edit
     * @throws waException if filter not found
     * @throws waRightsException if access denied to filter
     * @return array
     */
    public function get($id, $user = null,$can_edit = false)
    {
        if ($user === null) {
            $user = wa()->getUser();
        }
        $data = $this->getById($id);
        if (empty($data)) {
            throw new waException('Filter not found', 404);
        }
        if (
            //it's personal filter of other user
            (($data['contact_id'] < 0) && ($data['contact_id'] != -(int)$user->getId()))
            ||
            //it's common filter but user isn't admin and edit mode
            ($can_edit && ($data['contact_id'] >= 0) && (!$user->isAdmin('hub')))
        ) {
            throw new waRightsException('Access denied');
        }
        $data['conditions'] = (array)json_decode($data['conditions'], true);
        return $data;
    }

    public function update($id, $data)
    {
        $data['conditions'] = json_encode($data['conditions']);
        $data['update_datetime'] = date('Y-m-d H:i:s');
        return $this->updateById($id, $data);
    }

    public function getFilters($user = null, $check_groups = true)
    {
        if ($user === null) {
            $user = wa()->getUser();
        }

        $group_ids = array(-$user->getId());
        if ($check_groups) {
            $group_ids[] = 0; // everybody

            if (!$user->isAdmin('hub')) {
                // contact groups
                $user_groups_model = new waUserGroupsModel();
                $group_ids = array_merge($group_ids, $user_groups_model->getGroupIds($user->getId()));
            } else {
                //all contact groups
                $group_ids = array_merge($group_ids, array_keys(hubHelper::getGroups()));
            }

        }

        return $this->where('contact_id IN (?)', $group_ids)->order($this->id)->fetchAll($this->id);
    }
}
