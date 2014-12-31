<?php

class hubFrontendTopicDeleteController extends waJsonController
{
    public function execute()
    {
        $id = waRequest::param('id');
        $topic_model = new hubTopicModel();
        $topic = $topic_model->getById($id);
        if (!$topic) {
            throw new waException(_w('Topic not found'), 404);
        }

        if (!wa()->getUser()->isAdmin('hub')) {
            if ($topic['contact_id'] != $this->getUserId() ||
                (time() - strtotime($topic['create_datetime']) > 120 * 60)
            ) {
                throw new waRightsException(_ws('Access denied'));
            }
        }

        if (waRequest::method() != 'post') {
            $this->redirect(
                wa()->getRouteUrl('/frontend/topic', array(
                    'topic_url' => $topic['url'],
                    'id' => $topic['id'],
                ))
            );
        } else {

            // get main category
            $topic_categories_model = new hubTopicCategoriesModel();
            $category_id = $topic_categories_model
                ->select('category_id')
                ->where('topic_id = i:topic_id', array('topic_id' => $id))
                ->order('sort')
                ->limit(1)
                ->fetchField();

            // delete topic
            $topic_model->deleteById($id);

            // redirect to category or main page
            if ($category_id) {
                $category_model = new hubCategoryModel();
                $category = $category_model->getById($category_id);
                $url = wa()->getRouteUrl('hub/frontend/category', array('category_url' => $category['url'], 'hub_id' => $topic['hub_id']));
            } else {
                $url = wa()->getRouteUrl('hub/frontend', array('hub_id' => $topic['hub_id']));
            }
            $this->response = $url;

        }
    }
}

