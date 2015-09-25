<?php

class hubTagModel extends waModel
{
    protected $table = 'hub_tag';

    public function getByName($name, $hub_id, $return_id = false)
    {
        $hub_id = (int)$hub_id;
        $sql = "SELECT * FROM ".$this->table." WHERE name LIKE '".$this->escape($name, 'like')."' AND hub_id = {$hub_id}";
        $row = $this->query($sql)->fetch();
        return $return_id ? (isset($row['id']) ? $row['id'] : null) : $row;
    }

    public function getIds($tags, $hub_id)
    {
        $result = array();
        foreach ($tags as $t) {
            $t = trim($t);
            if ($id = $this->getByName($t, $hub_id, true)) {
                $result[] = $id;
            } else {
                $result[] = $this->insert(array('name' => $t, 'hub_id' => $hub_id, 'count' => 0));
            }
        }
        return $result;
    }

    const CLOUD_MAX_SIZE = 120;
    const CLOUD_MIN_SIZE = 80;
    const CLOUD_MAX_OPACITY = 100;
    const CLOUD_MIN_OPACITY = 30;

    public function getCloud($hub_id, $limit = 100)
    {
        $sql = "SELECT id, name, COUNT(*) as count
                FROM ".$this->table." t
                    JOIN hub_topic_tags tt ON t.id = tt.tag_id
                WHERE t.hub_id = i:0
                GROUP BY t.id
                ORDER BY count DESC";
        if ($limit) {
            $sql .= ' LIMIT '.(int)$limit;
        }
        $tags = $this->query($sql, (int)$hub_id)->fetchAll();
        if (!empty($tags)) {
            $first = current($tags);
            $max_count = $min_count = $first['count'];
            foreach ($tags as $tag) {
                if ($tag['count'] > $max_count) {
                    $max_count = $tag['count'];
                }
                if ($tag['count'] < $min_count) {
                    $min_count = $tag['count'];
                }
            }
            $diff = $max_count - $min_count;
            $diff = $diff <= 0 ? 1 : $diff;
            $step_size = (self::CLOUD_MAX_SIZE - self::CLOUD_MIN_SIZE) / $diff;
            $step_opacity = (self::CLOUD_MAX_OPACITY - self::CLOUD_MIN_OPACITY) / $diff;
            foreach ($tags as &$tag) {
                $tag['size'] = ceil(self::CLOUD_MIN_SIZE + ($tag['count'] - $min_count) * $step_size);
                $tag['opacity'] = number_format((self::CLOUD_MIN_OPACITY + ($tag['count'] - $min_count) * $step_opacity) / 100, 2, '.', '');
            }
            unset($tag);
        }
        return $tags;
    }

    /**
     *
     * @param int|array|null $hub_id
     */
    public function recount($hub_id = null)
    {
        $ext_cond = "";
        if ($hub_id) {
            $hub_id = array_map('intval', (array)$hub_id);
            $ext_cond = "IN(".implode(',', $hub_id).")";
        }
        $select = "`{$this->table}` t
            LEFT JOIN (
                SELECT tag_id, count(tag_id) cnt FROM `hub_topic_tags` GROUP BY tag_id
            ) tt
            ON t.id = tt.tag_id";

        $sql = "SELECT t.id FROM {$select} WHERE tt.tag_id IS NULL".
            ($ext_cond ? " AND t.hub_id {$ext_cond}" : "");
        $ids = array_keys($this->query($sql)->fetchAll('id'));

        if ($ids) {
            // delete tags that has no assignments
            $sql = "DELETE FROM `{$this->table}` WHERE id IN (".implode(',', $ids).")";
            $this->exec($sql);
        }

        // update counters
        $sql = "UPDATE {$select} SET t.count = tt.cnt".($ext_cond ? " WHERE t.hub_id {$ext_cond}" : "");
        $this->exec($sql);
    }

    public function deleteByField($field, $value = null)
    {
        $topic_tags_model = new hubTopicTagsModel();
        switch ($field) {
            case $this->id:
                $topic_tags_model->deleteByField('tag_id', $value);
                break;
            default:
                $where = $this->getWhereByField($field, $value, true);
                $sql = <<<SQL
DELETE {$topic_tags_model->getTableName()} FROM {$topic_tags_model->getTableName()}
JOIN {$this->table} ON ({$this->table}.id = {$topic_tags_model->getTableName()}.tag_id)
WHERE $where
SQL;
                $topic_tags_model->exec($sql);
                break;
        }

        return parent::deleteByField($field, $value);
    }
}