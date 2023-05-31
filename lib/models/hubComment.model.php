<?php

class hubCommentModel extends waNestedSetModel
{
    protected $table = 'hub_comment';

    protected $left = 'left_key';
    protected $right = 'right_key';
    protected $depth = 'depth';
    protected $parent = 'parent_id';
    protected $root = 'topic_id';

    const STATUS_DELETED = 'deleted';
    const STATUS_PUBLISHED = 'approved';

    private function getListDefaultOptions()
    {
        return array(
            'offset'       => 0,
            'limit'        => 50,
            'where'        => array(),
            'order'        => "{$this->left}",
            'check_rights' => true,
            'escape'       => false,
            'updated_only' => false,
        );
    }

    public function getList($fields = '*,is_updated,contact,vote,topic,parent', $options = array(), &$total_rows = null)
    {
        $main_fields = '';
        $post_fields = '';

        foreach (explode(',', $fields) as $name) {
            if ($this->fieldExists($name) || $name == '*') {
                $main_fields .= ',hub_comment.'.$name;
            } else {
                $post_fields .= ','.$name;
            }
        }

        $main_fields = substr($main_fields, 1);
        $post_fields = substr($post_fields, 1);

        $options += $this->getListDefaultOptions();

        $updated_only_sql = '';
        if (!empty($options['where']['updated_only'])) {
            $updated_only_sql = " AND datetime > '".$this->escape(date('Y-m-d H:i:s', wa('hub')->getConfig()->getLastDatetime()))."'";
            unset($options['where']['updated_only']);
        }

        $where = $this->getWhereByField($options['where'], true);
        $where .= $updated_only_sql;

        $limit_str = '';
        if ($options['limit'] !== false) {
            $limit_str = " LIMIT ".($options['offset'] ? $options['offset'].',' : '').(int)$options['limit'];
        }

        $rights_sql = '';
        if ($options['check_rights'] && !wa()->getUser()->isAdmin('hub')) {
            $main_fields .= ',t.hub_id';
            $hub_ids = join(',', array_keys(wa('hub')->getConfig()->getAvailableHubs(hubRightConfig::RIGHT_READ)));
            if (!$hub_ids) {
                return array();
            }
            $rights_sql = " JOIN hub_topic AS t ON t.id=hub_comment.topic_id AND t.hub_id IN ({$hub_ids}) ";
        }

        $sql = "SELECT ".(func_num_args() > 2 ? 'SQL_CALC_FOUND_ROWS' : '')." $main_fields
                FROM `{$this->table}`".
            $rights_sql.
            ($where ? " WHERE $where" : "").
            " ORDER BY ".$options['order'].
            $limit_str;

        $data = $this->query($sql)->fetchAll('id');
        if (!$data) {
            return $data;
        }
        foreach ($data as &$item) {
            $item['datetime_ts'] = strtotime($item['datetime']);
            $item['ip'] = long2ip($item['ip']);
        }
        unset($item);

        if (func_num_args() > 2) {
            $total_rows = (int)$this->query('SELECT FOUND_ROWS()')->fetchField();
        }

        $this->workupList($data, $post_fields, $options['escape']);

        return $data;

    }

    public function getComment($id, $ext_fields = 'contact,vote,topic,parent', $escape = false)
    {
        $item = $this->getByField('id', $id);
        if (!$item) {
            return array();
        }
        $item['datetime_ts'] = strtotime($item['datetime']);
        $item['ip'] = long2ip($item['ip']);
        $items = array($item['id'] => $item);
        $this->workupList($items, $ext_fields, $escape);
        return $items[$item['id']];
    }

    public function getFullTree($topic_id, $fields = '*,contact,vote', $order = null, $escape = false)
    {
        $topic_id = (int)$topic_id;
        $options = array(
            'limit'  => false,
            'escape' => $escape
        );

        if (!$order) {
            $options['where']['topic_id'] = $topic_id;
            return $this->getList($fields, $options);
        }

        $main_fields = '';
        $post_fields = '';

        foreach (explode(',', $fields) as $name) {
            if ($this->fieldExists($name) || $name == '*') {
                $main_fields .= ','.$name;
            } else {
                $post_fields .= ','.$name;
            }
        }

        $main_fields = substr($main_fields, 1);
        $post_fields = substr($post_fields, 1);

        $sql = "SELECT {$main_fields} FROM `{$this->table}` WHERE topic_id = {$topic_id} AND parent_id = 0 ORDER BY {$order}";
        $parents = $this->query($sql)->fetchAll('id');
        if (!$parents) {
            return array();
        }

        $sql = "SELECT {$main_fields} FROM `{$this->table}` WHERE topic_id = {$topic_id} ORDER BY `{$this->left}`";
        $indexed = array();
        foreach ($this->query($sql) as $item) {
            if ($item['parent_id'] == 0) {
                $parent_id = $item['id'];
                continue;
            }
            $indexed[$parent_id][$item['id']] = $item;
        }
        $items = array();
        foreach ($parents as $id => $parent) {
            $items[$id] = $parent;
            if (!empty($indexed[$id])) {
                $items += $indexed[$id];
            }
        }

        foreach ($items as &$item) {
            $item['datetime_ts'] = strtotime($item['datetime']);
            $item['ip'] = long2ip($item['ip']);
        }
        unset($item);

        if (wa('hub')->getEnv() == 'frontend') {
            $this->cutOffDeleted($items);
        }

        $this->workupList($items, $post_fields, $escape);
        return $items;
    }

    private function cutOffDeleted(&$items)
    {
        // need for cutting deleted reviews and its children in frontend
        $max_depth = 1000;
        if (!empty($items)) {
            $depth = $max_depth;
            foreach ($items as $id => $item) {
                if ($item['status'] == self::STATUS_DELETED) {
                    if ($item[$this->depth] < $depth) {
                        $depth = $item[$this->depth];
                    }
                    unset($items[$id]);
                    continue;
                }
                if ($item[$this->depth] > $depth) {
                    unset($items[$id]);
                } else {
                    $depth = $max_depth;
                }
            }
        }
    }

    private function workupList(&$data, $fields, $escape)
    {
        $fields = array_fill_keys(explode(',', $fields), 1);

        if (isset($fields['contact']) || isset($fields['author'])) {
            $contact_ids = array();
            foreach ($data as $item) {
                $contact_ids[] = $item['contact_id'];
            }
            $contact_ids = array_unique($contact_ids);
            $contacts = hubHelper::getAuthor($contact_ids);

            foreach ($data as &$item) {
                if (isset($contacts[$item['contact_id']])) {
                    $item['author'] = $contacts[$item['contact_id']];
                    if ($escape) {
                        $item['author']['name'] = htmlspecialchars($item['author']['name']);
                    }
                } else {
                    $item['author'] = array();
                }
            }
            unset($item);
        }

        if (isset($fields['my_vote'])) {
            $votes = array();
            $contact_id = wa()->getUser()->getId();
            if ($contact_id) {
                $vote_model = new hubVoteModel();
                $votes = $vote_model->getVotes(array($contact_id), array_keys($data));
            }
            foreach ($data as &$item) {
                $item['my_vote'] = ifset($votes[$contact_id][$item['id']]['vote'], 0);
            }
            unset($item, $votes);
        }

        if (!empty($fields['is_updated']) || !empty($fields['is_new'])) {
            $this->checkForNew($data);
        }

        if (!empty($fields['vote'])) {
            foreach ($data as &$item) {
                $item['vote'] = ifset($item['votes_sum'], 0);
            }
            unset($item);
        }

        if (!empty($fields['topic'])) {
            $topic_ids = array();
            foreach ($data as $item) {
                $topic_ids[] = $item['topic_id'];
            }
            $topic_ids = array_unique($topic_ids);
            $topic_model = new hubTopicModel();
            if ($topic_ids) {
                $types = hubHelper::getTypes();
                $topics = $topic_model->select('id,title,type_id,hub_id,url')->where("id IN(".implode(',', $topic_ids).")")->fetchAll('id');
                foreach ($topics as &$t) {
                    $t['type'] = ifset($types[$t['type_id']], array('id' => $t['type_id']));
                }
                unset($t);

                foreach ($data as &$item) {
                    $item['topic'] = $topics[$item['topic_id']];
                }
                unset($item);
            }
        }

        if (!empty($fields['parent'])) {
            $parent_ids = array();
            foreach ($data as $item) {
                if ($item['parent_id']) {
                    $parent_ids[] = $item['parent_id'];
                }
            }
            $parent_ids = array_unique($parent_ids);
            if ($parent_ids) {
                $parents = $this->select('*')->where("id IN(".implode(',', $parent_ids).")")->fetchAll('id');

                $parent_contact_ids = array();
                foreach ($data as $item) {
                    $parent_contact_ids[] = $item['contact_id'];
                }
                $parent_contact_ids = array_unique($parent_contact_ids);
                $parent_contacts = hubHelper::getAuthor($parent_contact_ids);

                foreach ($parents as &$parent) {
                    $parent['ip'] = long2ip($parent['ip']);
                    if (isset($contacts[$parent['contact_id']])) {
                        $parent['author'] = $parent_contacts[$parent['contact_id']];
                        if ($escape) {
                            $parent['author']['name'] = htmlspecialchars($parent['author']['name']);
                        }
                    } else {
                        $parent['author'] = array();
                    }
                }
                unset($parent);

                foreach ($data as &$item) {
                    if ($item['parent_id']) {
                        $item['parent'] = $parents[$item['parent_id']];
                    }
                }
                unset($item);
            }
        }

        if (!empty($fields['can_delete'])) {
            $contact_id = wa()->getUser()->getId();
            $hubs = wa('hub')->getConfig()->getAvailableHubs(hubRightConfig::RIGHT_FULL);
            foreach ($data as &$item) {
                $hub_id = ifset($item['hub_id']);
                if (!$hub_id && isset($item['parent'])) {
                    $hub_id = $item['parent']['hub_id'];
                }

                $item['can_delete'] = $item['contact_id'] == $contact_id || ($hub_id && !empty($hubs[$hub_id]));
            }
            unset($item);
        }
    }

    /**
     * @param $field
     * @param null $value
     * @param array $join
     * @return bool|resource
     * @throws waException
     */
    public function deleteByCondition($field, $value = null, $join = array())
    {
        /**
         * $field hub_id|topic_id
         */
        $where = array();
        if (is_array($field)) {
            $ids = empty($join) && (count($field) == 1) && ($this->id == key($field));
            $where[] = $this->getWhereByField($field, !$ids);
            if ($ids) {
                $ids = reset($field);
            }
        } elseif ($field) {
            $ids = empty($join) && ($field == $this->id);
            $where[] = $this->getWhereByField($field, $value, !$ids);
            $ids = $value;
        } else {
            $ids = null;
        }
        if ($join) {
            if (!empty($join['where'])) {
                $where[] = $join['where'];
            }
            $model = $join['model'];
            /**
             * @var waModel $model
             */
            $join_condition = sprintf('JOIN `%1$s` ON (`%1$s`.`%2$s`=`%3$s`.`%4$s`)', $model->getTableName(), $model->getTableId(), $this->table, $join['field']);
        } else {
            $join_condition = '';
        }


        // Remember users to later update author stats
        $where_count = $where;
        $where_count[] = $this->getWhereByField('status', self::STATUS_PUBLISHED, true);
        $where_count = implode(' AND ', $where_count);
        $sql = "SELECT DISTINCT hub_comment.contact_id, hub_comment.hub_id
                FROM hub_comment
                    {$join_condition}
                WHERE {$where_count}";
        $update_counts_data = $this->query($sql)->fetchAll();

        // Delete from hub_vote table
        $vote_model = new hubVoteModel();
        $where = implode(' AND ', $where);
        if ($ids) {
            $vote_model->deleteByField('comment_id', $ids);
        } else {
            $sql = "DELETE hub_vote
                    FROM hub_vote
                        JOIN hub_comment
                            ON hub_vote.comment_id=hub_comment.id
                        {$join_condition}
                    WHERE {$where}";

            $vote_model->exec($sql);
        }

        // Finally, delete from hub_comment
        $sql = "DELETE hub_comment
                FROM hub_comment {$join_condition}
                WHERE {$where}";
        $result = $this->exec($sql);

        // Update author stats
        $author_model = new hubAuthorModel();
        $author_model->updateCounts('comments', $update_counts_data);

        return $result;
    }

    /**
     * Add `is_updated` field to every comment in $items:
     * whether a comment has been created or changed since last time user logged in.
     */
    public function checkForNew(&$items)
    {
        $datetime = wa('hub')->getConfig()->getLastDatetime();
        $hub_visited_comments = wa()->getStorage()->get('hub_visited_comments');
        foreach ($items as &$item) {
            $item['datetime_ts'] = ifset($item['datetime_ts'], strtotime($item['datetime']));
            $item['is_new'] = $item['is_updated'] = empty($hub_visited_comments[$item['id']]) && $item['datetime_ts'] > $datetime;
        }
        unset($item);
    }

    public function countNew($recalc = false)
    {
        $sql = "SELECT id
                FROM `{$this->table}`
                WHERE datetime > ?
                    AND status='approved'";

        $result = 0;
        $hub_visited_comments = wa()->getStorage()->get('hub_visited_comments');
        foreach ($this->query($sql, date('Y-m-d H:i:s', wa('hub')->getConfig()->getLastDatetime())) as $row) {
            if (empty($hub_visited_comments[$row['id']])) {
                $result++;
            }
        }
        return $result;
    }

    public function countNewToMyFollowing()
    {
        $sql = "SELECT id
                FROM `{$this->table}` AS c
                    JOIN hub_following AS f
                        ON f.topic_id=c.topic_id
                WHERE c.datetime > ?
                    AND f.contact_id = ?
                    AND status='approved'";

        $result = 0;
        $rows = $this->query($sql, array(
            date('Y-m-d H:i:s', wa('hub')->getConfig()->getLastDatetime()),
            wa()->getUser()->getId()
        ));
        $hub_visited_comments = wa()->getStorage()->get('hub_visited_comments');
        foreach ($rows as $row) {
            if (empty($hub_visited_comments[$row['id']])) {
                $result++;
            }
        }
        return $result;
    }

    public function add($data, $parent_id = null, $before_id = null)
    {
        $data['text'] = hubHelper::sanitizeHtml($data['text']);
        if ($parent_id) {
            $parent = $this->getById($parent_id);
            if (!$parent) {
                return false;
            }
            if (empty($data['topic_id'])) {
                $data['topic_id'] = $parent['topic_id'];
            }
        }

        if (empty($data['topic_id'])) {
            return false;
        }

        $topic_model = new hubTopicModel();
        $topic = $topic_model->getById($data['topic_id']);
        if (!$topic) {
            return false;
        }

        $data['hub_id'] = $topic['hub_id'];

        if (empty($data['ip']) && ($ip = waRequest::getIp())) {
            $ip = ip2long($ip);
            if ($ip > 2147483647) {
                $ip -= 4294967296;
            }
            $data['ip'] = $ip;
        }

        if (!empty($data['contact_id'])) {
            if ($data['contact_id'] == wa('hub')->getUser()->getId()) {
                $user = wa('hub')->getUser();
            } else {
                try {
                    $user = new waContact($data['contact_id']);
                    $user->getName();
                } catch (Exception $e) {
                    $user = null;
                    $data['contact_id'] = null;
                }
            }
            if ($user && $user->getId() && !$user->get('is_user')) {
                $user->addToCategory(wa('hub')->getApp());
            }
        }

        if (empty($data['datetime'])) {
            $data['datetime'] = date('Y-m-d H:i:s');
        }

        $before_id = null;
        $id = parent::add($data, $parent_id, $before_id);
        if (!$id) {
            return false;
        }

        // Write to wa_log
        class_exists('waLogModel') || wa('webasyst');
        $log_model = new waLogModel();
        $log_model->add('comment_add', $id);

        $update = array(
            'update_datetime' => date('Y-m-d H:i:s'),
            'comments_count'  => $this->countByField(array(
                'status'   => self::STATUS_PUBLISHED,
                'topic_id' => $data['topic_id'],
            )),
        );
        $topic_model->updateById($data['topic_id'], $update);
        $topic = $update + $topic;

        $category_model = new hubCategoryModel();
        $category_model->updateCategoriesStats($topic);

        // Instant vote for own comment
        if (!empty($data['contact_id'])) {
            $vote_model = new hubVoteModel();
            $vote_model->vote($data['contact_id'], $id, 'comment', +1);
        }

        // Update author stats
        if (!empty($data['contact_id']) && !empty($data['hub_id'])) {
            $author_model = new hubAuthorModel();

            // New comments and topics does not change own kudos.
            if ($data['contact_id'] != wa()->getUser()->getId()) {
                $author_model->receiveVote('comment', $data['hub_id'], $data['contact_id'], +1);
            }

            $author_model->updateCounts('comments', $data['hub_id'], $data['contact_id']);
        }

        return $id;
    }

    public function validate($comment)
    {
        $errors = array();
        if (empty($comment['text'])) {
            $errors['text'] = _w('A comment cannot be empty.');
        }
        if (empty($comment['id']) && !empty($comment['parent_id'])) {
            $parent = $this->getById($comment['parent_id']);
            if (!$parent || $parent['status'] != 'approved') {
                $errors['text'] = _w('You cannot reply to a removed comment.');
            }
        }
        return $errors;
    }

    public function count($topic_id = null)
    {
        $where = array();
        if ($topic_id) {
            $where[] = 'topic_id = '.(int)$topic_id;
        }
        $sql = "SELECT COUNT(id) cnt FROM `{$this->table}` ";
        if ($where) {
            $sql .= "WHERE ".implode(' AND ', $where);
        }
        return $this->query($sql)->fetchField('cnt');
    }

    public function changeStatus($comment_id, $status)
    {
        $comment = $this->getById($comment_id);
        if (!$comment) {
            return false;
        }
        if ($status == $comment['status']) {
            return true;
        }
        if ($status != self::STATUS_DELETED && $status != self::STATUS_PUBLISHED) {
            return false;
        }

        if (isset($comment['topic_id'])) {
            $this->query("UPDATE {$this->table} SET status='{$status}' WHERE `{$this->left}` >= i:left AND `{$this->right}` <= i:right AND `topic_id` = i:topic_id",
                array(
                    'left'     => $comment['left_key'],
                    'right'    => $comment['right_key'],
                    'topic_id' => $comment['topic_id'],
                ));
            // Write to wa_log
            class_exists('waLogModel') || wa('webasyst');
            $log_model = new waLogModel();
            $log_model->add($status == self::STATUS_DELETED ? 'comment_delete' : 'comment_restore', $comment_id);

            // Update stats in hub_author
            $author_model = new hubAuthorModel();
            $author_model->updateCounts('comments', $comment['hub_id'], $comment['contact_id']);

            // Update stats in hub_topic
            $topic_model = new hubTopicModel();
            $topic_model->updateById($comment['topic_id'], array(
                'comments_count' => $this->countByField(array(
                    'status'   => self::STATUS_PUBLISHED,
                    'topic_id' => $comment['topic_id'],
                ))
            ));
        }

        return true;
    }

    public function changeSolution($comment_id, $solution)
    {
        $comment = $this->getComment($comment_id);
        if (!$comment) {
            return false;
        }
        $this->updateById($comment_id, array('solution' => $solution ? 1 : 0));

        $answered = $this->select('id')->where("solution = 1 AND topic_id = {$comment['topic_id']}")->limit(1)->fetchField();
        $topic_model = new hubTopicModel();
        $topic_model->updateById($comment['topic_id'], array('badge' => $answered ? 'answered' : null));

        return true;
    }
}
