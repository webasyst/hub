<?php

class hubTopicCategoriesModel extends waModel
{
    protected $table = 'hub_topic_categories';

    public function assign($topic_id, $category_ids, $top = false)
    {
        $category_ids = array_filter((array)$category_ids, 'intval');

        $topic_model = new hubTopicModel();
        $topic = $topic_model->getById($topic_id);
        if (!$topic) {
            return false;
        }

        $old = $this->select('category_id')->where('topic_id = ?', $topic_id)->fetchAll(null, true);

        $add = array_diff($category_ids, $old);
        $del = array_diff($old, $category_ids);

        if ($del) {
            $this->deleteByField(array('topic_id' => $topic_id, 'category_id' => $del));
        }
        if ($add) {
            $data = array();
            $sorts = $this->getMaxSort($category_ids);
            foreach ($category_ids as $c_id) {
                $data[] = array(
                    'topic_id' => $topic_id,
                    'category_id' => $c_id,
                    'sort' => ifset($sorts[$c_id], 0) + 1,
                );
            }

            $this->multipleInsert($data);
        }

        return true;
    }

    public function getMaxSort($category_id)
    {
        if (is_array($category_id)) {
            return $this->query('SELECT category_id, MAX(sort) FROM '.$this->table.' WHERE category_id IN (i:ids)
                GROUP BY category_id', array('ids' => $category_id))->fetchAll('category_id', true);
        } else {
            return $this->query('SELECT MAX(sort) FROM '.$this->table.' WHERE category_id = i:0', $category_id)->fetchField();
        }
    }

    /**
     *
     * @param int $id
     * @param int|null $before_id
     * @param int $category_id
     * @return boolean
     */
    public function move($id, $before_id, $category_id)
    {
        $key = array(
            'topic_id' => $id,
            'category_id' => $category_id
        );
        $row = $this->getByField($key);
        if (!$row) {
            return;
        }

        $key['topic_id'] = $before_id;
        if ($before_id && $before = $this->getByField($key)) {
            $sort = $before['sort'] + 1;
        } else {
            $sort = 0;
        }

        // Insert topic $id between $before_id.sort (or 0) and $before_id.sort+1 (or 1).
        // To do that, move everything starting from $before_id.sort+1 up one point.
        $this->exec(
            "UPDATE ".$this->table." SET sort = sort + 1
            WHERE category_id = i:0 AND sort >= i:1",
            $category_id,
            $sort
        );

        $key['topic_id'] = $id;
        $this->updateByField($key, array('sort' => $sort));
        return true;
    }


    /**
     * @param array $category_ids
     * @return array
     */
    public function getPriorityTopicIds($category_ids)
    {
        if (!$category_ids) {
            return array();
        }
        $sql = 'SELECT tc.category_id, tc.topic_id
                    FROM '.$this->table.' tc
                        JOIN hub_topic t
                            ON tc.topic_id = t.id
                WHERE t.status = 1
                    AND t.priority = 1
                    AND tc.category_id IN (i:ids)
                ORDER BY tc.sort DESC, t.id DESC';
        $result = array();
        $q = $this->query($sql, array('ids' => $category_ids));
        foreach ($q as $row) {
            $result[$row['category_id']][] = $row['topic_id'];
        }
        return $result;
    }
}