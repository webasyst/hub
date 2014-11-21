<?php

class hubTopicCategoriesModel extends waModel
{
    protected $table = 'hub_topic_categories';
    
    public function assign($topic_id, $category_ids, $top = false)
    {
        $category_ids = (array)$category_ids;
        
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
            if ($top) {
                $this->exec('UPDATE ' . $this->table . ' SET sort = sort + 1 WHERE category_id IN (i:ids)', array('ids' => $category_ids));
            } else {
                $sorts = $this->getMaxSort($category_ids);
            }
            foreach ($category_ids as $c_id) {
                $data[] = array(
                    'topic_id' => $topic_id,
                    'category_id' => $c_id,
                    'sort' => ($top || !isset($sorts[$c_id])) ? 1 : ($sorts[$c_id] + 1)
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
        $row = $this->getById($key);
        if (!$row) {
            return;
        }

        if ($before_id && $before = $this->getById($before_id)) {
            $sort = $before['sort'];
        } else {
            $sort = 0;
        }

        $this->exec(
            "UPDATE ".$this->table." SET sort = sort - 1
            WHERE category_id = i:0 AND sort > i:1",
            $category_id,
            $row['sort']
        );
        if ($sort > $row['sort']) {
            $sort--;
        }
        $this->exec(
            "UPDATE ".$this->table." SET sort = sort + 1
            WHERE category_id = i:0 AND sort >= i:1",
            $category_id,
            $sort
        );
        $this->updateById($id, array('sort' => $sort));
        return true;
    }
}