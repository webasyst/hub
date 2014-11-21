<?php

class hubViewHelper extends waAppViewHelper
{

    /**
     * @param int $limit
     * @return array
     */
    public function comments($limit = 10)
    {
        $hub_id = waRequest::param('hub_id');
        $comment_model = new hubCommentModel();
        return $comment_model->getList(
            '*,is_updated,contact,vote,topic,parent',
            array(
                'offset' => 0,
                'limit'  => $limit,
                'where'  => array(
                    'hub_id' => $hub_id
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
    public function staff()
    {
        $hub_id = waRequest::param('hub_id');

        $staff_model = new hubStaffModel();
        return $staff_model->getStaff($hub_id);
    }

    /**
     * @return array
     */
    public function categories()
    {
        $hub_id = waRequest::param('hub_id');

        $category_model = new hubCategoryModel();
        $cats = $category_model->getByHub($hub_id);
        $category_model->checkForNew($cats);
        $url = $this->wa->getRouteUrl('hub/frontend/category', array('category_url' => '%URL%', 'hub_id' => $hub_id));
        $logo_url = $this->wa->getDataUrl('categories/', true, 'hub');
        foreach ($cats as &$c) {
            $c['url'] = str_replace('%URL%', $c['url'], $url);
            if (!empty($c['logo'])) {
                $c['logo_url'] = $logo_url.$c['id'].'/'.$c['logo'];
            }
            unset($c);
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
    public function topics($hash, $offset = null, $limit = null)
    {
        if (!$limit && $offset) {
            $limit = $offset;
            $offset = 0;
        }
        if (!$offset && !$limit) {
            $offset = 0;
            $limit = 500;
        }

        $c = new hubTopicsCollection($hash);
        return $c->getTopics('*,url,tags', $offset, $limit);
    }
}
