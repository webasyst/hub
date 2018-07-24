<?php

class hubViewHelper extends waAppViewHelper
{

    /**
     * @param int $limit
     * @return array
     */
    public function comments($limit = 10, $hub_id = null)
    {
        $hub_id = (int)ifempty($hub_id, waRequest::param('hub_id', 0, 'int'));
        $comment_model = new hubCommentModel();
        return $comment_model->getList(
            '*,is_updated,contact,vote,topic,parent,my_vote',
            array(
                'offset' => 0,
                'limit'  => $limit,
                'where'  => array(
                    'hub_id' => $hub_id,
                    'status' => 'approved',
                ),
                'order'  => 'datetime DESC'
            )
        );
    }

    /**
     * @param int $id
     * @return array
     */
    public function topic($id)
    {
        $topic_model = new hubTopicModel();
        $topic = $topic_model->getById($id);
        // check hub status
        if (wa()->getEnv() == 'frontend') {
            $hub_model = new hubHubModel();
            $hub = $hub_model->getById($topic['hub_id']);
            if (!$hub['status']) {
                return array();
            }
        }
        if ($topic) {
            $topic['author'] = hubHelper::getAuthor($topic['contact_id']);
        }
        return $topic;
    }

    /**
     * @return array
     */
    public function staff($hub_id = null)
    {
        $hub_id = (int)ifempty($hub_id, waRequest::param('hub_id', 0, 'int'));
        $staff_model = new hubStaffModel();
        $contact_ids = $staff_model->select('contact_id')->where('hub_id = '.$hub_id)->fetchAll(null, true);
        return hubHelper::getAuthor($contact_ids);
    }

    /**
     * @param bool $priority_topics
     * @return array
     */
    public function categories($priority_topics = false, $hub_id = null)
    {
        $hub_id = (int)ifempty($hub_id, waRequest::param('hub_id', 0, 'int'));

        $category_model = new hubCategoryModel();
        $cats = $category_model->getByHub($hub_id);
        $category_model->checkForNew($cats);
        $url = wa()->getRouteUrl('hub/frontend/category', array('category_url' => '%URL%', 'hub_id' => $hub_id));
        $logo_url = wa()->getDataUrl('categories/', true, 'hub');
        foreach ($cats as &$c) {
            $c['url'] = str_replace('%URL%', $c['url'], $url);
            if (!empty($c['logo'])) {
                $c['logo_url'] = $logo_url.$c['id'].'/'.$c['logo'];
            }
            unset($c);
        }
        if ($priority_topics) {
            $topic_categories_model = new hubTopicCategoriesModel();
            $priority_topics = $topic_categories_model->getPriorityTopicIds(array_keys($cats));

            $tc = new hubTopicsCollection('search/priority=1');
            $topics = $tc->getTopics('*,url,author,params');
            foreach ($cats as &$c) {
                $c['priority_topics'] = array();
                if (isset($priority_topics[$c['id']])) {
                    foreach ($priority_topics[$c['id']] as $topic_id) {
                        if (isset($topics[$topic_id])) {
                            $c['priority_topics'][$topic_id] = $topics[$topic_id];
                        }
                    }
                }
                unset($c);
            }
        }
        return $cats;
    }

    /**
     * Get data array from product collection
     * @param string $hash selector hash
     * @param int $offset optional parameter
     * @param int $limit optional parameter
     *
     * If $limit is omitted but $offset is not than $offset is interpreted as 'limit' and method returns first 'limit' items
     * If $limit and $offset are omitted that method returns first 500 items
     *
     * @return array
     */
    public function topics($hash, $offset = null, $limit = null, $hub_id = null)
    {
        $hub_id = (int)ifempty($hub_id, waRequest::param('hub_id', 0, 'int'));

        if (!$limit && $offset) {
            $limit = $offset;
            $offset = 0;
        }
        if (!$offset && !$limit) {
            $offset = 0;
            $limit = 500;
        }

        $old_hub_id = waRequest::param('hub_id');
        waRequest::setParam('hub_id', (int)$hub_id);
        $c = new hubTopicsCollection($hash, array('hub_id' => (int)$hub_id));
        $result = $c->getTopics('*,url,tags,author,is_updated,follow,params', $offset, $limit);
        waRequest::setParam('hub_id', $old_hub_id);
        return $result;
    }

    /**
     * Tag cloud for given hub_id. Defaults to current frontend hub if no hub_id is given.
     */
    public function tags($limit = 50, $hub_id = null)
    {
        $hub_id = (int)ifempty($hub_id, waRequest::param('hub_id', 0, 'int'));
        if ( ( $cache = $this->wa()->getCache())) {
            $cache_key = 'tags'.(int)$hub_id.'l'.$limit;
            $tags = $cache->get($cache_key);
            if ($tags !== null) {
                return $tags;
            }
        }
        $tag_model = new hubTagModel();
        $tags = $tag_model->getCloud($hub_id, $limit);
        $cache && $cache->set($cache_key, $tags, 7200);
        return $tags;
    }

    public function authors($limit = 10, $hub_id = null)
    {
        $hub_id = (int)ifempty($hub_id, waRequest::param('hub_id', 0, 'int'));
        $author_model = new hubAuthorModel();
        $result = $author_model->getList('*,badge', array(
            'hub_id' => $hub_id,
            'limit' => $limit,
        ));
        return $result;
    }
}
