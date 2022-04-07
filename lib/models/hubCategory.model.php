<?php

class hubCategoryModel extends waModel
{
    protected $table = 'hub_category';
    protected $context = 'hub_id';

    const TYPE_STATIC = 0;
    const TYPE_DYNAMIC = 1;

    public function getByHub($hub_id)
    {
        return $this->where('hub_id = ?', $hub_id)->order('sort, id')->fetchAll('id');
    }

    /**
     * @param int $hub_id
     * @return array
     * @deprecated
     */
    public function getFullTree($hub_id = null)
    {
        $hub_id = (int)$hub_id;
        if ($hub_id) {
            $sql = "SELECT * FROM `{$this->table}` WHERE `hub_id` = {$hub_id} ORDER BY `sort`, `id`";
        } else {
            $sql = "SELECT * FROM `{$this->table}` ORDER BY `hub_id`,`sort`,`id`";
        }
        return $this->query($sql)->fetchAll('id');
    }

    /**
     * Insert new item to on some level (parent)
     * @param array $data
     * @return bool|int|resource
     */
    public function add($data)
    {
        if (empty($data) || empty($data['hub_id'])) {
            return false;
        }

        if (empty($data['url'])) {
            if ($url = hubHelper::transliterate($data['name'], false)) {
                $data['url'] = $this->suggestUniqueUrl($url, $data['hub_id']);
            } else {
                $data['url'] = $this->suggestUniqueUrl(time(), $data['hub_id']);
            }
        }
        if (!isset($data['name'])) {
            $data['name'] = '';
        }


        if (!isset($data['create_datetime'])) {
            $data['create_datetime'] = date('Y-m-d H:i:s');
        }

        if (!empty($data['url'])) {
            $data['url'] = $this->suggestUniqueUrl($data['url'], $data['hub_id'], null);
        }
        if (empty($data['sort'])) {
            $data['sort'] = 1 + $this->query("SELECT MAX(sort) FROM {$this->table} WHERE hub_id=?", (int)$data['hub_id'])->fetchField();
        }

        if ($data['type'] == self::TYPE_DYNAMIC) {
            $collection = new hubTopicsCollection('/search/'.$data['conditions']);
            $collection->orderBy('update_datetime', 'DESC');
            $topics = $collection->getTopics('*', 0, 1);
            if ($topic = reset($topics)) {
                $data['update_datetime'] = $topic['update_datetime'];
            }
        }
        $id = $this->insert($data);
        if (!$id) {
            return false;
        }
        return $id;
    }

    public function deleteByField($field, $value = null)
    {
        $topic_categories_model = new hubTopicCategoriesModel();
        switch ($field) {
            case 'category_id':
                $topic_categories_model->deleteByField('category_id', $value);
                break;
            default:
                $where = $this->getWhereByField($field, $value, true);
                $sql = <<<SQL
DELETE {$topic_categories_model->getTableName()} FROM {$topic_categories_model->getTableName()}
JOIN {$this->table} ON ({$this->table}.id = {$topic_categories_model->getTableName()}.category_id)
WHERE $where
SQL;
                $topic_categories_model->exec($sql);

        }
        return parent::deleteByField($field, $value);
    }


    public function move($id, $before_id)
    {
        $row = $this->getById($id);
        if (!$row) {
            return false;
        }

        if ($before_id && ( $before = $this->getById($before_id))) {
            $sort = $before['sort'] + 1;
        } else {
            $sort = 0;
        }

        $this->exec(
            "UPDATE ".$this->table."
             SET sort = sort + IF(sort > i:1 OR (sort = i:1 AND id > i:2), 2, 0)
             WHERE hub_id = i:0
                AND sort >= i:1",
            (int)$row['hub_id'],
            $sort,
            $before_id
        );
        $this->updateById($id, array('sort' => $sort));
        return true;
    }

    /**
     * Update with checking uniqueness of url
     * @param int $id
     * @param array $data
     * @param string[] $errors
     * @return bool
     */
    public function update($id, $data, &$errors = array())
    {
        $result = false;
        if ($item = $this->getById($id)) {
            $result = true;

            if (empty($data['hub_id'])) {
                $data['hub_id'] = $item['hub_id'];
            }
            if (!empty($data['url'])) {
                //validate
                if ($this->urlExists($data['url'], $item['hub_id'], $item['id'])) {
                    $errors['url'] = _w('The URL is in use.');
                    $result = false;
                }
            } elseif (isset($data['url'])) {
                if ($url = hubHelper::transliterate($data['name'], false)) {
                    $data['hub_id'] = ifempty($data['hub_id'], $item['hub_id']);
                    $data['url'] = $this->suggestUniqueUrl($url, $data['hub_id'], $id);
                } else {
                    $data['url'] = $this->suggestUniqueUrl($id, $data['hub_id'], $id);
                }
            } elseif (!empty($data['hub_id']) && ($item['hub_id'] != $data['hub_id'])) {

                //update url on hub change
                $data['url'] = $this->suggestUniqueUrl(ifset($data['url'], $item['url']), $data['hub_id'], $id);
            }

            $data['edit_datetime'] = date('Y-m-d H:i:s');
            if ($result) {
                $result = $this->updateById($id, $data);
            }
        }

        return $result;
    }

    /**
     * Suggest unique url by original url.
     * If not exists yet just return without changes, otherwise fit a number suffix and adding it to url.
     * @see urlExists
     *
     * @param string $original_url
     * @param int $hub_id
     * @param int $category_id Pass to urlExists method
     *
     * @return string
     */
    public function suggestUniqueUrl($original_url, $hub_id, $category_id = null)
    {
        $counter = 1;
        $url = $original_url;
        while ($this->urlExists($url, $hub_id, $category_id)) {
            $url = "{$original_url}_{$counter}";
            $counter++;
        }
        return $url;
    }

    /**
     * Check if same url exists already for any category in current level (parent_id), excepting this category
     *
     * @param string $url
     * @param int $hub_id
     * @param int $id Category id optional. If set than check urls excepting url of this category
     *
     * @return boolean
     */
    public function urlExists($url, $hub_id, $id = null)
    {
        $where = "url = s:url AND hub_id = i:hub_id";
        if ($id) {
            $where .= " AND id != i:id";
        }
        return !!$this->select('id')->where($where, compact('url', 'id', 'hub_id'))->limit(1)->fetch();
    }

    /**
     * @deprecated
     * @param $id
     * @param $url
     * @return bool
     */
    public function updateUrl($id, $url)
    {
        $item = $this->getById($id);

        if ($item['url'] != $url) {
            $this->updateById($id, compact('ur'));
        }

        return true;
    }


    /**
     * Change update_datetime of all categories given topic belongs to.
     * Ignores drafts all together.
     * @param $topic array|int
     * @param $count int        +1 or -1. If set, also change topics_count
     * @return mixed
     */
    public function updateCategoriesStats($topic, $count=0)
    {
        if (!is_array($topic)) {
            $topic_model = new hubTopicModel();
            $topic = $topic_model->getById($topic);
        }
        if (empty($topic) || empty($topic['id'])) {
            return array();
        }

        $set = array();
        $vars = array();

        if (!empty($topic['status'])) {
            $vars[] = date('Y-m-d H:i:s');
            $set[] = 'update_datetime=?';
        }
        $count = (int) $count;
        if ($count != 0) {
            $vars[] = $count;
            $set[] = 'topics_count=topics_count+(?)';
        }

        if ($set) {
            $categories = $this->getByTopic($topic);
            if ($categories) {
                $sql = "UPDATE {$this->table} SET ".join(', ', $set)." WHERE id IN (?)";
                $vars[] = array_keys($categories);
                $this->exec($sql, $vars);
            }
        }
    }

    /**
     * Return all categories this topic belongs to, including the dynamic ones.
     * @param $topic array|int
     * @return array category_id => category data
     */
    public function getByTopic($topic)
    {
        if (!is_array($topic)) {
            $topic = wao(new hubTopicModel())->getById($topic);
        }
        if (empty($topic) || empty($topic['id'])) {
            return array();
        }

        // All plain categories this topic belongs to will be filled if required in the following loop
        $plain_categories = null;

        // Tags attached to this topic will be fetched if required in the following loop
        $tags = null;

        $topic_categories = array();
        foreach ($this->getByHub($topic['hub_id']) as $category) {
            // Static filter?
            if ($category['type'] == hubCategoryModel::TYPE_STATIC) {
                if ($plain_categories === null) {
                    $plain_categories = wao(new hubTopicCategoriesModel())->getByField('topic_id', $topic['id'], 'category_id');
                }
                if (!empty($plain_categories[$category['id']])) {
                    $topic_categories[$category['id']] = $category;
                }
            } else {// Filter by question type?
                if (preg_match("~^type_id=(\d+)$~uis", $category['conditions'], $match)) {
                    if ($match[1] == $topic['type_id']) {
                        $topic_categories[$category['id']] = $category;
                    }
                } else { // Filter by tags?

                    if (preg_match('~^tag_id=(.+)$~', $category['conditions'], $matches)) {
                        if ($tags === null) {
                            $tags = array();
                            foreach (wao(new hubTopicTagsModel())->getTags($topic['id']) as $tag) {
                                $tags[] = $tag['id'];
                            }
                        }

                        $category_tag_ids = explode('||', $matches[1]);
                        if (array_intersect($category_tag_ids, $tags)) {
                            $topic_categories[$category['id']] = $category;
                        }
                    }
                }
            }
        }

        return $topic_categories;
    }

    /**
     * Add `is_updated` field to every category in $items:
     * whether a topic has been created, published, changed, or new comment added
     * since last time user logged in.
     */
    public function checkForNew(&$items)
    {
        $datetime = wa('hub')->getConfig()->getLastDatetime();
        foreach ($items as &$item) {
            $item['update_datetime_ts'] = ifset($item['update_datetime_ts'], strtotime($item['update_datetime']));
            $item['is_updated'] = $item['update_datetime_ts'] > $datetime;
        }
        unset($item);
    }
}
