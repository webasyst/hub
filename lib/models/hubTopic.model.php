<?php

class hubTopicModel extends waModel
{
    protected $table = 'hub_topic';

    /**
     * @deprecated
     */
    const TYPE_ARTICLE = 'article';
    /**
     * @deprecated
     */
    const TYPE_QUESTION = 'question';
    /**
     * @deprecated
     */
    const TYPE_BUG = 'bug';
    /**
     * @deprecated
     */
    const TYPE_IDEA = 'idea';
    /**
     * @deprecated
     */
    const TYPE_NOTE = 'note';

    public function deleteByField($field, $value = null)
    {
        /**
         * @var mixed $ids
         * int or list of ints, when deleting by simple condition, by topic_id
         * false when condition is not that simple.
         */

        if (is_array($field)) {
            $ids = (count($field) == 1) && (key($field) == $this->id);
            $where = $this->getWhereByField($field, true);
            if ($ids) {
                $ids = reset($field);
            }
        } else {
            $ids = ($field == $this->id);
            $where = $this->getWhereByField($field, $value, true);
            if ($ids) {
                $ids = $value;
            }
        }

        if (!is_array($ids)) {
            $ids = array($ids);
        }

        // Data to update hub_category.topics_count (only in simplest case)
        if ($ids && count($ids) == 1) {
            $topic = $this->getById($ids[0]);
            if (!$topic) {
                return;
            }
        }

        // All (hub_id, contact_id) pairs to update stats after deletion
        $update_hub_topic_count = array();
        $update_comment_stats = array();
        $update_topic_stats = array();

        // Authors from hub_topic table
        $sql = "SELECT DISTINCT hub_id, contact_id FROM `{$this->table}` WHERE $where";
        foreach ($this->query($sql) as $row) {
            $update_hub_topic_count[$row['hub_id']] = true;
            $update_topic_stats[] = $row;
        }
        $update_hub_topic_count = array_keys($update_hub_topic_count);

        // Make sure there's something to delete at all
        if (empty($update_topic_stats)) {
            return false;
        }

        // Authors from hub_comment table
        $sql = "SELECT DISTINCT c.hub_id, c.contact_id
                FROM hub_comment AS c
                    JOIN `{$this->table}`
                        ON c.topic_id=`{$this->table}`.id
                WHERE $where";
        foreach ($this->query($sql) as $row) {
            $update_comment_stats[] = $row;
        }

        // Delete comments
        $comment_model = new hubCommentModel();
        if ($ids) {
            $comment_model->deleteByCondition('topic_id', $ids);
        } else {
            $comment_model->deleteByCondition(
                null,
                null,
                array(
                    'model' => $this,
                    'field' => 'topic_id',
                    'where' => $where,
                )
            );
        }

        // delete data at related tables by topic_id values/joined values
        $models = array(
            new hubVoteModel(),
            new hubFollowingModel(),
            new hubTopicTagsModel(),
            new hubTopicCategoriesModel(),
        );
        foreach ($models as $model) {
            /**
             * @var waModel $model
             */
            if ($ids) { // simply delete by topic_id
                $model->deleteByField('topic_id', $ids);
            } else {
                $sql = "DELETE `{$model->getTableName()}`
                        FROM `{$model->getTableName()}`
                            JOIN `{$this->table}` ON (`{$this->table}`.`{$this->id}`=`{$model->getTableName()}`.`topic_id`)
                        WHERE {$where}";
                $model->exec($sql);
            }
        }

        // Delete topics
        $result = parent::deleteByField($field, $value);

        // update stats of users whose topics and comments we deleted
        $author_model = new hubAuthorModel();
        $author_model->updateCounts('topics', $update_topic_stats);
        $update_comment_stats && $author_model->updateCounts('comments', $update_comment_stats);

        // Update hub topics count
        $hub_model = new hubHubModel();
        $hub_model->updateTopicsCount($update_hub_topic_count);

        if (!empty($topic)) {
            $topic['status'] = 0;
            $category_model = new hubCategoryModel();
            $category_model->updateCategoriesStats($topic, -1);
        }

        return $result;
    }


    /**
     * @deprecated
     * @param int|int[] $id
     * @return bool
     * @throws waException
     */
    public function delete($id)
    {
        return !!$this->deleteByField('id', $id);
    }

    public function update($id, $data)
    {
        $item = $this->getById($id);
        if (!$item) {
            return false;
        }
        if (isset($data['hub_id'])) {
            $hub_id = (int)$data['hub_id'];

            // hub is changed, delete old assigned info
            if ($item['hub_id'] != $hub_id) {

                // IMPORTANT: models with method assign
                foreach (array(
                             new hubTopicTagsModel(),
                             new hubTopicCategoriesModel()
                         ) as $m) {
                    // delete old related (assigned) info
                    /**
                     * @var hubTopicTagsModel|hubTopicCategoriesModel $m
                     */
                    $m->assign($id, array());
                }
                $this->changeHub($item['hub_id'], $hub_id, $id);

            }
        }

        if (isset($data['create_datetime']) && empty($data['create_datetime'])) {
            unset($data['create_datetime']);
        }

        $data['update_datetime'] = date('Y-m-d H:i:s');
        $this->updateById($id, $data);

        // IMPORTANT: models with method assign
        foreach (array(
                     'tags'       => new hubTopicTagsModel(),
                     'params'     => new hubTopicParamsModel(),
                     'categories' => new hubTopicCategoriesModel()
                 ) as $n => $m) {
            if (isset($data[$n])) {

                /**
                 * @var hubTopicTagsModel|hubTopicCategoriesModel $m
                 */
                $m->assign($id, $data[$n]);
            }
        }

        if (empty($item['status']) && !empty($data['status'])) {
            // Publish
            $this->updateStatsWhenPublished($id, $data + $item);
        } else if (isset($data['status']) && empty($data['status']) && !empty($item['status'])) {
            // Unpublish
            $this->updateStatsWhenUnpublished($id, $data + $item);
        } else if (!empty($item['status'])) {
            // Modification of published topic
            class_exists('waLogModel') || wa('webasyst');
            $log_model = new waLogModel();
            $log_model->add('topic_edit', $id);
        }
    }

    /** Move one or all topics from $hub_id to $target_hub_id */
    public function changeHub($hub_id, $target_hub_id, $topic_id = null)
    {
        if (empty($hub_id)) {
            $hubs = $this->select('DISTINCT hub_id')->where($this->getWhereByField($this->id, $topic_id))->limit(2)->fetchAll();

            if (count($hubs) > 1) {
                throw new waException('topics are from different hubs');
            }
            $hub = reset($hubs);

            $hub_id = (int)$hub['hub_id'];

        }
        if ($hub_id != $target_hub_id) {
            $data = array(
                'hub_id' => $hub_id,
            );
            if (!empty($topic_id)) {
                $data['topic_id'] = $topic_id;
            }

            $comment_model = new hubCommentModel();
            $comment_model->updateByField($data, array('hub_id' => $target_hub_id));

            //hub types (type_id@hub_topic) = delete or add? *IGNORED

            $topic_tags_model = new hubTopicTagsModel();
            if (!$topic_id) {
                $this->updateByField($data, array('hub_id' => $target_hub_id));
                $topic_tags_model->remap($target_hub_id, 'hub_id', $hub_id);
            } else {
                $this->updateById($topic_id, array('hub_id' => $target_hub_id));
                $topic_tags_model->remap($target_hub_id, 'topic_id', $topic_id);
            }

            $hub_model = new hubHubModel();
            $hub_model->updateTopicsCount(array($hub_id, $target_hub_id));

            $author_model = new hubAuthorModel();
            $author_model->updateCounts('all', $hub_id);
            $author_model->updateCounts('all', $target_hub_id);
        }
    }

    public function add($data)
    {
        $data['update_datetime'] = date('Y-m-d H:i:s');
        if (empty($data['create_datetime'])) {
            $data['create_datetime'] = date('Y-m-d H:i:s');
        }

        if (empty($data['contact_id'])) {
            $data['contact_id'] = wa()->getUser()->getId();
        }
        if (empty($data['url'])) {
            $data['url'] = hubHelper::transliterate($data['title']);
        }

        $id = $this->insert($data);
        if (!$id) {
            return false;
        }

        // IMPORTANT: models with method assign
        foreach (array(
                     'tags'       => new hubTopicTagsModel(),
                     'params'     => new hubTopicParamsModel(),
                     'categories' => new hubTopicCategoriesModel()
                 ) as $n => $m) {
            if (isset($data[$n])) {
                /**
                 * @var hubTopicTagsModel|hubTopicCategoriesModel $m
                 */
                $m->assign($id, $data[$n], true);
            }
        }

        if (!empty($data['status']) || !isset($data['status'])) {
            $this->updateStatsWhenPublished($id, $data);
        }

        return $id;
    }

    protected function updateStatsWhenPublished($id, $data)
    {
        // Instant vote for own topic
        if (!empty($data['contact_id'])) {
            $vote_model = new hubVoteModel();
            $vote_updated = $vote_model->vote($data['contact_id'], $id, 'topic', +1);

            // Update author stats
            if (!empty($data['hub_id'])) {
                $author_model = new hubAuthorModel();
                $author_model->updateCounts('all', $data['hub_id'], $data['contact_id']);
                if ($vote_updated && $data['contact_id'] != wa()->getUser()->getId()) {
                    $author_model->receiveVote('topic', $data['hub_id'], $data['contact_id'], +1);
                }
            }
        }

        // Update hub topic counts
        if (!empty($data['hub_id'])) {
            $hub_model = new hubHubModel();
            $hub_model->updateTopicsCount($data['hub_id']);
        }

        // Update category
        $category_model = new hubCategoryModel();
        $category_model->updateCategoriesStats($id, +1);

        // Write to wa_log
        class_exists('waLogModel') || wa('webasyst');
        $log_model = new waLogModel();
        $log_model->add('topic_publish', $id);
    }

    protected function updateStatsWhenUnpublished($id, $data)
    {
        // Update author stats
        if (!empty($data['contact_id']) && !empty($data['hub_id'])) {
            $author_model = new hubAuthorModel();
            $author_model->updateCounts('all', $data['hub_id'], $data['contact_id']);
        }

        // Update hub topic counts
        if (!empty($data['hub_id'])) {
            $hub_model = new hubHubModel();
            $hub_model->updateTopicsCount($data['hub_id']);
        }

        // Update category
        $category_model = new hubCategoryModel();
        $category_model->updateCategoriesStats($id, -1);

        // Write to wa_log
        class_exists('waLogModel') || wa('webasyst');
        $log_model = new waLogModel();
        $log_model->add('topic_unpublish', $id);
    }

    /**
     * Add `is_updated` field to every topic in $items:
     * whether a topic has been created, published, changed, or new comment added
     * since last time user logged in.
     */
    public function checkForNew(&$items)
    {
        $datetime = wa('hub')->getConfig()->getLastDatetime();
        $hub_visited_topics = wa()->getStorage()->get('hub_visited_topics');
        foreach ($items as &$item) {
            $item['update_datetime_ts'] = ifset($item['update_datetime_ts'], strtotime($item['update_datetime']));
            $item['create_datetime_ts'] = ifset($item['create_datetime_ts'], strtotime($item['create_datetime']));
            $item['is_updated'] = empty($hub_visited_topics[$item['id']]) && $item['update_datetime_ts'] > $datetime;
            $item['is_new'] = empty($hub_visited_topics[$item['id']]) && $item['create_datetime_ts'] > $datetime;
        }
        unset($item);
    }

    /**
     * Returns the number of records stored in the table.
     *
     * @return int
     */
    public function countAll()
    {
        return $this->query("SELECT COUNT(*) FROM ".$this->table." WHERE status = 1")->fetchField();
    }

    /**
     * Number of updated topics since last time user logged in.
     * @return array hub_id => number of topics, 'all' => total number of updated topics.
     */
    public function countNew()
    {
        $sql = "SELECT hub_id, id AS topic_id
                FROM `{$this->table}`
                WHERE create_datetime > ?
                    AND status > 0
                GROUP BY hub_id";

        $cnt = array('all' => 0);
        $hub_visited_topics = wa()->getStorage()->get('hub_visited_topics');
        foreach($this->query($sql, date('Y-m-d H:i:s', wa('hub')->getConfig()->getLastDatetime())) as $row) {
            if (empty($hub_visited_topics[$row['topic_id']])) {
                $cnt[$row['hub_id']] = ifset($cnt[$row['hub_id']], 0) + 1;
                $cnt['all']++;
            }
        }

        return $cnt;
    }
}

