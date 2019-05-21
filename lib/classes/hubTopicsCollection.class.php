<?php

class hubTopicsCollection
{
    protected $filtered = false;
    protected $prepared;
    protected $title = '';

    protected $order_by = 't.votes_sum DESC';
    protected $where = array();
    protected $fields = array();
    protected $other_fields = array();
    protected $joins = array();
    protected $join_index = array();
    protected $options = array(
        'hub_id' => 0,
        'check_rights' => true,
    );
    protected $info = array();
    protected $hash;
    protected $count;

    public function __construct($hash = '', $options = array())
    {
        foreach ($options as $k => $v) {
            $this->options[$k] = $v;
        }
        $this->setHash($hash);
    }


    protected function setHash($hash)
    {
        if (is_array($hash)) {
            $hash = '/id/'.implode(',', $hash);
        }
        if (substr($hash, 0, 1) == '#') {
            $hash = substr($hash, 1);
        }
        $this->hash = trim($hash, '/');
        if ($this->hash == 'all') {
            $this->hash = '';
        }
        $this->hash = explode('/', $this->hash, 2);
    }

    public function addTitle($title, $delim = ', ')
    {
        if (!$title) {
            return;
        }
        if ($this->title) {
            $this->title .= $delim;
        }
        $this->title .= $title;
    }

    public function getTitle()
    {
        return $this->title;
    }

    public function getInfo()
    {
        if (!$this->prepared) {
            $this->prepare();
        }
        return $this->info;
    }

    protected function prepare($add = false, $auto_title = true)
    {
        if (!$this->prepared || $add) {
            $type = $this->hash[0];
            if ($type != 'drafts') {
                $this->where['status'] = 't.status = 1';
            }

            if ((wa()->getEnv() == 'frontend') && empty($this->options['search_all'])) {
                $hub = hubHelper::getHub();
                if (!empty($hub['status'])) {
                    $this->where[] = 't.hub_id = ' . (int)waRequest::param('hub_id');
                } else {
                    $this->where[] = '0';
                }
            } else {
                if ($this->options['check_rights'] && !wa()->getUser()->isAdmin('hub')) {
                    $hubs_read = wa('hub')->getConfig()->getAvailableHubs(hubRightConfig::RIGHT_READ);
                    if ($hubs_read) {
                        $this->where['check_rights'] = 't.hub_id IN (' . join(',', array_keys($hubs_read)) . ')';
                    } else {
                        $this->where['check_rights'] = '1=0';
                    }
                }
            }

            if ($type) {
                $method = strtolower($type).'Prepare';
                if (method_exists($this, $method)) {
                    $this->$method(isset($this->hash[1]) ? $this->hash[1] : '', $auto_title);
                } else {
                    $this->where[] = '0';
                }
            } else {
                if ($auto_title) {
                    $this->addTitle(_w('All topics'));
                }
                if ($this->options['hub_id']) {
                    $this->where[] = 't.hub_id = '.(int)$this->options['hub_id'];
                }
            }

            if (isset($this->options['sort'])) {
                $sort = $this->options['sort'];
            } else {
                $sort = waRequest::get('sort');
            }
            if ($sort && $type != 'search') {
                switch ($sort) {
                    case 'manual':
                        if ($this->info && !empty($this->info['hub_id']) && isset($this->info['type']) && $this->info['type'] == 0) {
                            $this->order_by = $this->getTableAlias('hub_topic_categories').'1.sort DESC';
                            break;
                        }
                    case 'recent':
                        $this->order_by = 't.create_datetime DESC';
                        break;
                    case 'popular':
                        $this->order_by = 't.votes_sum DESC';
                        break;
                    case 'unanswered':
                        $this->order_by = 't.votes_sum DESC';
                        $this->where[] = "(t.badge != 'answered' OR t.badge IS NULL)";
                        break;
                    case 'updated':
                        $this->order_by = 't.update_datetime DESC';
                        break;
                    case 'archive':
                        $this->where[] = "t.badge = 'archived'";
                        break;
                }
            }


            if ($this->prepared) {
                return;
            }
            $this->prepared = true;
        }
    }

    public function getJoinedAlias($table)
    {
        $alias = $this->getTableAlias($table);
        return $alias.$this->join_index[$alias];
    }

    protected function getTableAlias($table)
    {
        $t = explode('_', $table);
        $alias = '';
        foreach ($t as $tp) {
            if ($tp == 'hub') {
                continue;
            }
            $alias .= substr($tp, 0, 1);
        }
        if (!$alias) {
            $alias = $table;
        }
        return $alias;
    }

    public function addJoin($table, $on = null, $where = null)
    {
        $type = '';
        if (is_array($table)) {
            if (isset($table['on'])) {
                $on = $table['on'];
            }
            if (isset($table['where'])) {
                $where = $table['where'];
            }
            if (isset($table['type'])) {
                $type = $table['type'];
            }
            $table = $table['table'];
        }

        $alias = $this->getTableAlias($table);

        if (!isset($this->join_index[$alias])) {
            $this->join_index[$alias] = 1;
        } else {
            $this->join_index[$alias]++;
        }
        $alias .= $this->join_index[$alias];

        $join = array(
            'table' => $table,
            'alias' => $alias,
            'type'  => $type
        );
        if ($on) {
            $join['on'] = str_replace(':table', $alias, $on);
        }
        $this->joins[] = $join;
        if ($where) {
            $this->where[] = str_replace(':table', $alias, $where);
        }

        $this->filtered = true;

        return $alias;
    }

    public function addWhere($condition)
    {
        $this->where[] = $condition;
        $this->filtered=true;
        return $this;
    }

    public function getSQL()
    {
        $this->prepare();
        $sql = "FROM hub_topic t";

        if ($this->joins) {
            foreach ($this->joins as $join) {
                $alias = isset($join['alias']) ? $join['alias'] : '';
                if (isset($join['on'])) {
                    $on = $join['on'];
                } else {
                    $on = "t.id = ".($alias ? $alias : $join['table']).".topic_id";
                }
                $sql .= (!empty($join['type']) ? " ".$join['type'] : '')." JOIN ".$join['table']." ".$alias." ON ".$on;
            }
        }

        if ($this->where) {
            $sql .= " WHERE ".implode(" AND ", $this->where);
        }

        return $sql;
    }

    public function getFields($fields)
    {
        if ($fields == '*') {
            return 't.*'.($this->fields ? ",".implode(",", $this->fields) : '');
        }

        if (!is_array($fields)) {
            $fields = explode(",", $fields);
            $fields = array_map('trim', $fields);
        }
        foreach ($fields as $i => $f) {
            if ($f == '*') {
                $fields[$i] = 't.*';
                continue;
            } else {
                if ($f == 'hub_color' && !in_array('*', $fields) && !in_array('hub_id', $fields)) {
                    $this->fields[] = 't.hub_id';
                }
                $this->other_fields[] = $f;
                unset($fields[$i]);
            }
        }
        if ($this->fields) {
            foreach ($this->fields as $f) {
                $fields[] = $f;
            }
        }
        return implode(",", $fields);
    }

    public function getTopics($fields = "*", $offset = 0, $limit = null, $escape = true)
    {
        if (is_bool($limit)) {
            $escape = $limit;
            $limit = null;
        }
        if ($limit === null) {
            if ($offset) {
                $limit = $offset;
                $offset = 0;
            } else {
                $limit = 50;
            }
        }

        $sql = $this->getSQL();
        $sql = "SELECT ".$this->getFields($fields)." ".$sql;
        $sql .= $this->getOrderBy();
        $sql .= " LIMIT ".($offset ? $offset.',' : '').(int)$limit;

        $data = $this->getModel()->query($sql)->fetchAll('id');

        if (!$data) {
            return array();
        }

        $topic_ids_solution = array();
        $types = hubHelper::getTypes();
        $badges = hubHelper::getBadges();
        foreach ($data as &$row) {
            if (!empty($row['badge'])) {
                if (isset($badges[$row['badge']])) {
                    $b = $badges[$row['badge']];
                    $b['id'] = $row['badge'];
                    $row['badge'] = $b;
                }
            }
            if (!empty($types[$row['type_id']]['solution'])) {
                $topic_ids_solution[] = $row['id'];
            }
        }
        unset($row);

        if ($topic_ids_solution) {
            $comment_model = new hubCommentModel();
            $solutions = $comment_model->getList('*,author', array(
                'where' => array('topic_id' => $topic_ids_solution, 'solution' => 1),
                'order' => 'votes_sum DESC',
                'escape' => true
            ));
            foreach ($solutions as $s) {
                if (!isset($data[$s['topic_id']]['solution'])) {
                    $data[$s['topic_id']]['solution'] = $s;
                }
            }
        }

        $ids = array_keys($data);

        $other_fields = array_fill_keys($this->other_fields, true);
        $defaults = array_fill_keys($this->other_fields, null);

        if (!empty($other_fields['url'])) {
            $topic_urls = array();
            foreach ($data as $t) {
                if (!isset($topic_urls[$t['hub_id']])) {
                    $topic_urls[$t['hub_id']] = wa()->getRouteUrl('hub/frontend/topic', array(
                        'id' => '%ID%',
                        'topic_url' => '%URL%',
                        'hub_id' => $t['hub_id']
                    ));
                }
            }
            foreach ($data as &$t) {
                $t['url'] = str_replace(array('%ID%', '%URL%'), array($t['id'], $t['url']), $topic_urls[$t['hub_id']]);
            }
            unset($t);
        }

        if (!empty($other_fields['tags'])) {
            $tag_ids = array();
            $defaults['tags'] = array();

            // Fetch all [topic_id => tag_id] pairs
            $topic_tags_model = new hubTopicTagsModel();
            $rows = $topic_tags_model->getByField('topic_id', $ids, true);
            foreach ($rows as $row) {
                $tag_ids[$row['tag_id']] = 1;
            }
            $tag_ids = array_keys($tag_ids);

            if ($tag_ids) {

                // Fetch tag info and prepare frontend URL for each one
                $tag_model = new hubTagModel();
                $tags = $tag_model->getById($tag_ids);
                foreach($tags as &$t) {
                    $tag['url'] = wa()->getRouteUrl('hub/frontend/tag', array(
                        'tag' => urlencode($t['name']),
                        'hub_id' => $t['hub_id'],
                    ));
                }
                unset($t);

                // Finally, assign tags to topics
                foreach ($rows as $row) {
                    $tag = $tags[$row['tag_id']];
                    $data[$row['topic_id']]['tags'][] = $tag;
                }
            }
        }

        if (!empty($other_fields['author']) || !empty($other_fields['contact'])) {
            $contact_ids = array();
            foreach ($data as $t) {
                $contact_ids[] = $t['contact_id'];
            }
            $contacts = hubHelper::getAuthor(array_unique($contact_ids));
            foreach ($data as &$t) {
                if (isset($contacts[$t['contact_id']])) {
                    $c = $contacts[$t['contact_id']];
                    if (!empty($other_fields['author'])) {
                        $t['author'] = $c;
                    }
                    if (!empty($other_fields['contact'])) {
                        $t['contact'] = $c;
                    }
                }
            }
            unset($t);
        }

        if (!empty($other_fields['hub_color'])) {
            $hub_params_model = new hubHubParamsModel();
            $hub_colors = $hub_params_model->getByField('name', 'color', 'hub_id');
            foreach ($data as &$t) {
                if (empty($hub_colors[$t['hub_id']])) {
                    $t['hub_color'] = 'white';
                } else {
                    $t['hub_color'] = $hub_colors[$t['hub_id']]['value'];
                };
            }
            unset($t);
        }

        if (!empty($other_fields['is_updated'])) {
            $this->getModel()->checkForNew($data);
        }

        if (!empty($other_fields['follow'])) {
            $following_model = new hubFollowingModel();
            $rows = $following_model->getByField(
                array(
                    'contact_id' => wa()->getUser()->getId(),
                    'topic_id'   => $ids,
                ),
                true
            );
            foreach ($rows as $row) {
                $data[$row['topic_id']]['follow'] = 1;
            }
        }

        if (!empty($other_fields['params'])) {
            $defaults['params'] = array();
            $topic_params_model = new hubTopicParamsModel();
            foreach($topic_params_model->getByTopic($ids) as $id => $params) {
                $data[$id]['params'] = $params;
            }
        }

        foreach ($data as &$t) {
            $t += $defaults;
        }
        unset($t);

        return $data;
    }

    public function count()
    {
        if ($this->count !== null) {
            return $this->count;
        }
        $sql = $this->getSQL();
        $sql = "SELECT COUNT(".($this->joins ? 'DISTINCT ' : '')."t.id) ".$sql;
        $count = $this->count = (int)$this->getModel()->query($sql)->fetchField();

        // Update hub_hub.topics_count or hub_category.topics_count
        // (checking for `contact_id` foeld to make sure it's a hub or category, and not a filter)
        if ($this->info && isset($this->info['topics_count']) && !$this->filtered && !isset($this->info['contact_id'])) {
            if ($this->info['topics_count'] != $count) {
                $model = new hubCategoryModel();
                $model->updateById($this->info['id'], array('topics_count' => $count));
            }
        }

        return $count;
    }

    /**
     * @return hubTopicModel|waModel
     */
    public function getModel()
    {
        return new hubTopicModel();
    }

    public static function parseConditions($query)
    {
        $escapedBS = 'ESCAPED_BACKSLASH';
        while (false !== strpos($query, $escapedBS)) {
            $escapedBS .= rand(0, 9);
        }
        $escapedAmp = 'ESCAPED_AMPERSAND';
        while (false !== strpos($query, $escapedAmp)) {
            $escapedAmp .= rand(0, 9);
        }
        $query = str_replace('\\&', $escapedAmp, str_replace('\\\\', $escapedBS, $query));
        $query = explode('&', $query);
        $result = array();
        foreach ($query as $part) {
            if (!($part = trim($part))) {
                continue;
            }
            $part = str_replace(array($escapedBS, $escapedAmp), array('\\\\', '\\&'), $part);
            if ($temp = preg_split("/(\\\$=|\^=|\*=|==|!=|>=|<=|=|>|<)/uis", $part, 2, PREG_SPLIT_DELIM_CAPTURE)) {
                $name = array_shift($temp);
                if ($name == 'tag') {
                    $temp[1] = explode('||', $temp[1]);
                }
                if ($temp[0] == '>=') {
                    $result[$name][0] = $temp;
                } elseif ($temp[0] == '<=') {
                    $result[$name][1] = $temp;
                } else {
                    $result[$name] = $temp;
                }
            }
        }
        return $result;
    }

    /**
     * Returns expression for SQL
     *
     * @param string $op - operand ==, >=, etc
     * @param string $value - value
     * @return string
     */
    protected function getExpression($op, $value)
    {
        $model = $this->getModel();
        switch ($op) {
            case '>':
            case '>=':
            case '<':
            case '<=':
            case '!=':
                return " ".$op." '".$model->escape($value)."'";
            case "^=":
                return " LIKE '".$model->escape($value, 'like')."%'";
            case "$=":
                return " LIKE '%".$model->escape($value, 'like')."'";
            case "*=":
                return " LIKE '%".$model->escape($value, 'like')."%'";
            case "==":
            case "=";
            default:
                return " = '".$model->escape($value)."'";
        }
    }

    protected function idPrepare($ids, $auto_title = true)
    {
        $ids = explode(',', (string)$ids);
        foreach ($ids as $i => $id) {
            $ids[$i] = (int)trim($id);
            if (!$ids[$i]) {
                unset($ids[$i]);
            }
        }

        if ($ids) {
            $this->where[] = "t.id IN (".implode(',', $ids).")";
            $this->order_by = 'FIELD(t.id, '.implode(',', $ids).')';
        } else {
            $this->where[] = '0';
        }
    }

    protected function searchPrepare($query, $auto_title = true)
    {
        $query = urldecode($query);
        $i = $offset = 0;
        $query_parts = array();
        while (($j = strpos($query, '&', $offset)) !== false) {
            // escaped &
            if ($query[$j - 1] != '\\') {
                $query_parts[] = substr($query, $i, $j - $i);
                $i = $j + 1;
            }
            $offset = $j + 1;
        }
        $query_parts[] = substr($query, $i);

        $model = $this->getModel();
        $title = array();
        foreach ($query_parts as $part) {
            if (!($part = trim($part))) {
                continue;
            }
            $parts = preg_split("/(\\\$=|\^=|\*=|==|!=|>=|<=|=|>|<)/uis", $part, 2, PREG_SPLIT_DELIM_CAPTURE);
            if (count($parts) == 1) {
                $parts = array(
                    0 => 'query',
                    1 => '=',
                    2 => $part
                );
            }
            if ($parts) {
                if ($parts[0] == 'query') {
                    if (mb_strlen($parts[2]) <= 3) {
                        $this->where[] = "t.title LIKE '%".$model->escape($parts[2], 'like')."%'";
                        $this->order_by = 't.votes_sum DESC';
                    } else {
                        $this->where[] = "MATCH(t.title, t.content) AGAINST ('".$model->escape($parts[2])."' IN BOOLEAN MODE)";
                        $r = $this->getModel()->query('SELECT MAX(votes_sum) FROM hub_topic t WHERE '.implode(' AND ', $this->where))->fetchField();
                        if (!$r) {
                            $r = 1;
                        }
                        $this->fields[] = "2 * (t.votes_sum/".$r.") + (5 * MATCH(t.title) AGAINST ('".$model->escape($parts[2])."' IN BOOLEAN MODE) + MATCH(t.title, t.content) AGAINST ('".$model->escape($parts[2])."' IN BOOLEAN MODE)) AS relevance";
                        $this->order_by = 'relevance DESC';
                    }
                    if ($this->options['hub_id']) {
                        $this->where[] = 't.hub_id = '.(int)$this->options['hub_id'];
                    }
                    $this->info = $parts[2];
                    $title[] = $parts[2];
                } elseif ($parts[0] == 'tag') {
                    $tag_model = new hubTagModel();
                    if (strpos($parts[2], '||') !== false) {
                        $tags = explode('||', $parts[2]);
                        $tag_ids = $tag_model->getIds($tags, $this->options['hub_id']);
                    } else {
                        $sql = "SELECT id FROM ".$tag_model->getTableName()."
                                WHERE name".$this->getExpression($parts[1], $parts[2])." AND hub_id = ".(int)$this->options['hub_id'];
                        $tag_ids = $tag_model->query($sql)->fetchAll(null, true);
                    }
                    if ($tag_ids) {
                        $this->addJoin('hub_topic_tags', null, ":table.tag_id IN ('".implode("', '", $tag_ids)."')");
                    } else {
                        $this->where[] = "0";
                    }
                } elseif ($parts[0] == 'tag_id') {
                    $tag_ids = explode('||', $parts[2]);
                    $tag_ids = array_map('intval', $tag_ids);
                    $tag_ids = array_filter($tag_ids);
                    if ($tag_ids) {
                        $this->addJoin('hub_topic_tags', null, ":table.tag_id IN ('".implode("', '", $tag_ids)."')");
                    } else {
                        $this->where[] = "0";
                    }
                } elseif ($model->fieldExists($parts[0])) {
                    $title[] = $parts[0].$parts[1].$parts[2];
                    $this->where[] = 't.'.$parts[0].$this->getExpression($parts[1], $parts[2]);
                    if ($this->options['hub_id']) {
                        $this->where[] = 't.hub_id = '.(int)$this->options['hub_id'];
                    }
                }
            }
        }
        if ($title) {
            $title = implode(', ', $title);
            // Strip slashes from search title.
            $bs = '\\\\';
            $title = preg_replace("~{$bs}(_|%|&|{$bs})~", '\1', $title);
        }
        if ($auto_title) {
            $this->addTitle($title, ' ');
        }
    }

    protected function contactPrepare($contact_id, $auto_title = true)
    {
        $contact_model = new waContactModel();
        $contact = $contact_model->getById($contact_id);
        if ($contact) {
            $this->where[] = 't.contact_id = '.(int)$contact_id;
            if ($this->options['hub_id']) {
                $this->where[] = 't.hub_id = '.(int)$this->options['hub_id'];
            }
            if ($auto_title) {
                $this->addTitle(waContactNameField::formatName($contact));
            }
        } else {
            $this->where[] = '0';
        }
    }

    protected function categoryPrepare($id, $auto_title = true)
    {
        $category_model = new hubCategoryModel();
        $category = $category_model->getById($id);
        if ($category) {
            $filtered = $this->filtered;
            if ($category['type']) {
                $hash = $this->hash;
                $alias = $this->addJoin(
                    array(
                        'table' => 'hub_topic_categories',
                        'type'  => 'LEFT',
                        'on'    => 't.id = :table.topic_id AND :table.category_id = '.$category['id']
                    )
                );
                $this->options['hub_id'] = $category['hub_id'];
                $this->setHash('/search/'.$category['conditions']);
                $this->prepare(true, false);
                $this->setHash(implode('/', $hash));
            } else {
                $alias = $this->addJoin('hub_topic_categories', null, ':table.category_id = '.(int)$category['id']);
            }

            $this->filtered = $filtered;

            // proper order_by will be set in ->prepare()
            $this->order_by = 't.priority DESC, '.$this->order_by;
            $this->options['sort'] = waRequest::get('sort', $category['sorting'], 'string');

            $this->info = $category;
            if ($auto_title) {
                $this->addTitle($category['name']);
            }
        }
    }

    protected function filterPrepare($id, $auto_title = true)
    {
        $filter_model = new hubFilterModel();
        $filter = $filter_model->get($id);
        if (empty($filter)) {
            throw new waException('filter not found', 404);
        }
        $conditions = $filter['conditions'];

        $user = wa()->getUser();
        if (!$user->isAdmin('hub')) {
            $hub_ids = $user->getRights('hub', 'hub.%');
            if (empty($hub_ids)) {
                throw new waRightsException('There no available hubs');
            } else {
                if (empty($conditions['hub_id'])) {
                    $conditions['hub_id'] = array_keys($hub_ids);
                } else {
                    $conditions['hub_id'] = array_map('intval', $conditions['hub_id']);
                    $conditions['hub_id'] = array_intersect($conditions['hub_id'], array_keys($hub_ids));
                    if (empty($hub_ids)) {
                        throw new waRightsException('There no available hubs');
                    }
                }
            }
        }

        if (!empty($conditions['hub_id'])) {
            $conditions['hub_id'] = array_map('intval', $conditions['hub_id']);
            $this->where[] = "(`t`.`hub_id` IN (".implode(',', $conditions['hub_id'])."))";
        }

        if (!empty($conditions['types'])) {
            $where = array();
            foreach ($conditions['types'] as $type_id => $type_conditions) {

                $where[$type_id] = '`t`.`type_id`='.intval($type_id);
                if (!empty($type_conditions['badge'])) {
                    $no_badge = false;
                    if (($key = array_search(hubConfig::VIRTUAL_NO_BADGE_ID, $type_conditions['badge'])) !== false) {
                        $no_badge = true;
                        unset($type_conditions['badge'][$key]);
                    }
                    $badge_where = "`t`.`badge` IN ('".implode("', '", $this->getModel()->escape($type_conditions['badge']))."')";
                    if ($no_badge) {
                        $badge_where = "({$badge_where} OR `t`.`badge` IS NULL)";
                    }
                    $where[$type_id] .= " AND {$badge_where}";
                }

                if (isset($type_conditions['comments_count']) && ($type_conditions['comments_count'] !== '')) {

                    $where[$type_id] .= " AND ".((int)$type_conditions['comments_count'] ? '' : 'NOT ').' `t`.`comments_count`';
                }
            }
            $this->where[] = "(\n\t(".implode(")\n OR \n\t(", $where).")\n)";
        }

        if (!empty($conditions['tag_name'])) {
            $query = array(
                'name' => $conditions['tag_name'],
            );
            if (!empty($conditions['hub_id'])) {
                $query['hub_id'] = $conditions['hub_id'];
            }
            $tag_model = new hubTagModel();
            $conditions['tag_id'] = array_keys($tag_model->getByField($query, 'id'));
            if (empty($conditions['tag_id'])) {
                $this->where[] = '1=0';
            }
        }
        if (!empty($conditions['tag_id'])) {
            $conditions['tag_id'] = implode(', ', array_map('intval', (array)$conditions['tag_id']));
            $this->addJoin('hub_topic_tags', '`t`.`id`=:table.topic_id', ':table.tag_id IN ('.$conditions['tag_id'].')');
        }

        $this->order_by = 't.priority DESC, '.$this->order_by;

        $this->info = $filter;
        if ($auto_title) {
            $this->addTitle($filter['name']);
        }
    }

    public function hubPrepare($id, $auto_title = true)
    {
        $hub_model = new hubHubModel();
        $hub = $hub_model->getById($id);

        if ($hub) {
            $this->where[] = 't.hub_id = '.(int)$id;
            $this->order_by = 't.priority DESC, '.$this->order_by;
            $this->info = $hub;
            if ($auto_title) {
                $this->addTitle($hub['name']);
            }
        }
    }

    public function typePrepare($id, $auto_title = true)
    {
        $type_model = new hubTypeModel();
        $type = $type_model->getById($id);
        if ($type) {
            $this->where[] = "t.type_id = ".(int)$id;
            $this->order_by = 't.priority DESC, '.$this->order_by;
            $this->info = $type;
            if ($auto_title) {
                $this->addTitle($type['name']);
            }
        }
    }

    public function draftsPrepare()
    {
        $this->where[] = "t.status = 0";
        if ($this->options['check_rights'] && !wa()->getUser()->isAdmin('hub')) {
            $hubs_full = wa()->getConfig()->getAvailableHubs(hubRightConfig::RIGHT_FULL);
            $hubs_write = wa()->getConfig()->getAvailableHubs(hubRightConfig::RIGHT_READ_WRITE);
            $hubs_write = array_diff_key($hubs_write, $hubs_full);

            $sql_full = 't.hub_id IN ('.join(',', array_keys($hubs_full)).')';
            $sql_write = 't.hub_id IN ('.join(',', array_keys($hubs_write)).') AND t.contact_id='.wa()->getUser()->getId();

            if ($hubs_full && $hubs_write) {
                $this->where['check_rights'] = "(({$sql_full}) OR ({$sql_write}))";
            } elseif ($hubs_full) {
                $this->where['check_rights'] = $sql_full;
            } elseif ($hubs_write) {
                $this->where['check_rights'] = $sql_write;
            } else {
                $this->where['check_rights'] = '1=0';

            }
        }
    }

    public function followingPrepare()
    {
        if (!waRequest::get('sort')) {
            $this->order_by = 't.update_datetime DESC';
        }
        $this->addJoin('hub_following', null, ':table.contact_id = '.(int)wa()->getUser()->getId());
        $this->addTitle(_w('Favorites'));
    }

    protected function tagPrepare($id, $auto_title = true)
    {
        $tag = false;
        $ids = array();
        $tag_model = new hubTagModel();
        if (is_numeric($id)) {
            $id = (int)$id;
            $tag = $tag_model->getById($id);
            $ids = array($id);
        }
        if (!$tag) {
            $name = $id;
            $hub_id = waRequest::param('hub_id');
            if (empty($this->options['search_all']) && $hub_id) {
                $tag = $tag_model->getByName($name, waRequest::param('hub_id'));
                if ($tag) {
                    $ids = array($tag['id']);
                }
            } else {
                $tags = $tag_model->getByField(array(
                    'name' => $name,
                ), 'id');
                $ids = array_keys($tags);
                $tag = reset($tags);
            }
        }

        if ($tag) {
            $this->info = $tag;
            $this->addJoin('hub_topic_tags', null, ':table.tag_id IN ('.join(',', $ids).')');
            if ($auto_title) {
                $this->addTitle($tag['name']);
            }
        } else {
            $this->where[] = '0';
        }
    }

    public function getType()
    {
        return $this->hash[0];
    }

    /**
     * Returns ORDER BY clause
     * @return string
     */
    protected function getOrderBy()
    {
        if ($this->order_by) {
            return " ORDER BY ".$this->order_by;
        } else {
            return "";
        }
    }

    public function orderBy($field, $order = 'ASC')
    {
        $alias = 't';
        if ($field == 'sort') {
            $alias = 'tc1';
        }
        $this->order_by = "{$alias}.{$field} {$order}";
    }
}
