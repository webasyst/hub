<?php

class hubTopicTagsModel extends waModel
{
    protected $table = 'hub_topic_tags';

    /**
     *
     * @param int $topic_id
     * @param array $tags array of strings
     * @return bool
     */
    public function assign($topic_id, $tags)
    {
        $topic_model = new hubTopicModel();
        $topic = $topic_model->getById($topic_id);
        if (!$topic) {
            return false;
        }
        $tag_model = new hubTagModel();
        $tag_ids = $tag_model->getIds($tags, $topic['hub_id']);

        // simple kind of realization: delete all, than assign all

        $this->deleteByField('topic_id', $topic_id);

        $data = array();
        foreach ($tag_ids as $tag_id) {
            $data[] = array(
                'topic_id' => $topic_id,
                'tag_id'   => $tag_id
            );
        }

        $this->multipleInsert($data);

        $tag_model->recount($topic['hub_id']);

        return true;
    }

    public function getTags($topic_id)
    {
        return $this->getTagsByField('topic_id', $topic_id);
    }

    public function getTagsByField($field, $value = null)
    {
        if ($field == 'hub_id') {
            $tag_model = new hubTagModel();
            $where = $tag_model->getWhereByField($field, $value, 't');
        } else {
            $where = is_array($field) ? $this->getWhereByField($field, 'tt') : $this->getWhereByField($field, $value, 'tt');
        }
        $sql = "SELECT t.* FROM {$this->table} tt JOIN hub_tag t ON tt.tag_id = t.id
                WHERE ".$where;
        return $this->query($sql)->fetchAll();
    }

    public function remap($hub_id, $field, $value = null)
    {
        $map = array();
        if ($tags = $this->getTagsByField($field, $value)) {
            $ids = array();
            $names = array();

            foreach ($tags as $tag) {
                $ids[] = $tag['id'];
                $names[] = $tag['name'];
            }

            $tag_model = new hubTagModel();
            $target_ids = $tag_model->getIds($names, $hub_id);
            $map = array_combine($ids, $target_ids);
            if (!is_array($field)) {
                $field = array(
                    $field => $value
                );
            }

            foreach ($map as $tag_id => $target_id) {
                if ($tag_id != $target_id) {
                    $field['tag_id'] = $tag_id;
                    if (isset($field['hub_id'])) {
                        $current_hub_id = $field['hub_id'];
                        unset($field['hub_id']);
                        $where = array(
                            $tag_model->getWhereByField('hub_id', $current_hub_id, true),
                            $this->getWhereByField($field, true),
                        );
                        $sql = "UPDATE {$this->table}
                        JOIN {$tag_model->getTableName()} ON (`{$this->table}`.`tag_id`=`{$tag_model->getTableName()}`.`id`)
                        SET `tag_id` = ".$this->getFieldValue('tag_id', $target_id)."
                        WHERE (\n\t".implode("\n)\n\tAND\n(\n\t", $where)."\n)";

                        $this->exec($sql);

                    } else {
                        $this->updateByField($field, array('tag_id' => $target_id));
                    }
                }
            }
        }
        return $map;
    }
}
