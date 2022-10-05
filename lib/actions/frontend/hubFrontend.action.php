<?php

class hubFrontendAction extends waViewAction
{
    protected $types;
    protected $hub_id;
    protected $hub;

    public function __construct($params = null)
    {
        parent::__construct($params);
        if (!waRequest::isXMLHttpRequest()) {
            $this->setLayout(new hubFrontendLayout());
        }
        $this->hub_id = waRequest::param('hub_id');
        $this->types = hubHelper::getTypes();
        $this->view->assign('types', $this->types);
    }

    protected function getHubId()
    {
        $hub_id = waRequest::param('hub_id');
        if (!$hub_id) {
            throw new waException('No hub', 500);
        }
        return $hub_id;
    }

    protected function setCollection(hubTopicsCollection $collection)
    {
        $limit = $this->getConfig()->getOption('topics_per_page');
        if (!$limit) {
            $limit = 50;
        }
        $page = waRequest::get('page', 1, 'int');
        if ($page < 1) {
            $page = 1;
        }
        $offset = ($page - 1) * $limit;

        $topics = $collection->getTopics('*,url,tags,author,is_updated,follow,params', $offset, $limit);
        $count = $collection->count();

        if (!$count) {
            $pages_count = 1;
        } else {
            $pages_count = ceil((float)$count / $limit);
        }
        $this->view->assign('pages_count', $pages_count);

        $this->view->assign('topics', $topics);
        $this->view->assign('topics_count', $count);

        return $topics;
    }


    public function execute()
    {
        // Do not show home page unless URL is /
        if (strlen(wa()->getRouting()->getCurrentUrl())) {
            throw new waException('Page not found', 404);
        }

        $cookie_key = 'hub_home_sort';
        $sort = waRequest::request('sort', '', 'string_trim');
        if (!$sort) {
            $sort = waRequest::cookie($cookie_key, waRequest::param('home_sort', 'popular'), 'string_trim');
        } else {
            wa()->getResponse()->setCookie($cookie_key, $sort);
        }

        $c = new hubTopicsCollection('', array('sort' => $sort));
        $this->setCollection($c);

        $title = waRequest::param('title');
        if (!$title) {
            $app = wa()->getAppInfo();
            $title = $app['name'];
        }
        $this->getResponse()->setTitle($title);
        $this->getResponse()->setMeta('keywords', waRequest::param('meta_keywords'));
        $this->getResponse()->setMeta('description', waRequest::param('meta_description'));

        $route = wa()->getRouting()->getRoute();
        $og = array(
            'site_name'   => ifset($route, 'og_site_name', ''),
            'locale'      => ifset($route, 'og_locale', ''),
            'title'       => !empty($route['use_default_settings']) ? ifset($route, 'title', '') : ifset($route, 'og_title', ''),
            'type'        => 'website',
            'url'         => wa()->getConfig()->getHostUrl() . wa()->getConfig()->getRequestUrl(false, true),
            'description' => !empty($route['use_default_settings']) ? ifset($route, 'meta_description', '') : ifset($route, 'og_description', ''),
            'image'       => ifset($route, 'og_image', ''),
            'video'       => ifset($route, 'og_video', ''),
        );
        foreach ($og as $property => $content) {
            if (strlen($content)) {
                $this->getResponse()->setOGMeta('og:'.$property, $content);
            }
        }

        $this->view->assign('sort', $sort);

        /**
         * @event frontend_homepage
         * @return array[string]string $return[%plugin_id%] html output for search
         */
        $this->view->assign('frontend_homepage', wa()->event('frontend_homepage'));

        $this->setThemeTemplate('home.html');
    }

    // Show nice page in error.html template in case of exception during execute()
    public function display($clear_assign = true)
    {
        /**
         * @event frontend_nav
         * @return array[string]string $return[%plugin_id%] html output for navigation section
         */
        $this->view->assign('frontend_nav', wa()->event('frontend_nav'));

        $user = hubHelper::getAuthor($this->getUserId());
        $following_count = 0;
        if ($this->getUserId()) {
            $hub_following_model = new hubFollowingModel();
            $following_count = $hub_following_model->countTopics($this->getUserId(), $this->hub_id);
        }
        $user['following_count'] = $following_count;

        $this->view->assign('following_count', $following_count);
        $this->view->assign('user', $user);

        try {
            $hub_model = new hubHubModel();
            $hub = $hub_model->getById($this->hub_id);
            if (!$hub['status']) {
                throw new waException('Not found', 404);
            }
            $hub_params_model = new hubHubParamsModel();
            $hub['params'] = $hub_params_model->getParams($this->hub_id);
            $this->view->assign('hub', $hub);
            return parent::display(false);
        } catch (waException $e) {
            $code = $e->getCode();
            if ($code == 404) {
                $url = $this->getConfig()->getRequestUrl(false, true);
                if (substr($url, -1) !== '/' && substr($url, -9) !== 'index.php') {
                    wa()->getResponse()->redirect($url.'/', 301);
                }
            }
            /**
             * @event frontend_error
             */
            wa()->event('frontend_error', $e);
            $this->view->assign('error_message', $e->getMessage());
            $this->view->assign('error_code', $code);
            $this->getResponse()->setStatus($code ? $code : 500);
            $this->setThemeTemplate('error.html');
            return $this->view->fetch($this->getTemplate());
        }
    }
}
