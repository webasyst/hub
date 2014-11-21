<?php
class hubSitemapConfig extends waSitemapConfig
{
    public function execute()
    {
        $hm = new hubHubModel();
        $cm = new hubCategoryModel();
        $tm = new hubTagModel();

        // List of all hubs with their routes
        $time_now = date('Y-m-d H:i:s');
        $hubs = wa('hub')->getConfig()->getAvailableHubs();
        foreach ($this->getRoutes() as $route) {
            $hub_id = (int)ifempty($route['hub_id']);
            if (!$hub_id || !isset($hubs[$hub_id])) {
                continue;
            }
            if (empty($hubs[$hub_id]['routes'])) {
                $hubs[$hub_id]['routes'] = array();
            }

            $this->routing->setRoute($route);
            $this->addUrl($this->routing->getUrl("hub/frontend", array(), true), $time_now, self::CHANGE_HOURLY, 0.5);
            $route['_url_template_topic'] = $this->routing->getUrl('hub/frontend/topic', array('id' => '%ID%', 'topic_url' => '%URL%'), true);
            $route['_url_template_category'] = $this->routing->getUrl('hub/frontend/category', array('category_url' => '%URL%'), true);
            $route['_url_template_tag'] = $this->routing->getUrl('hub/frontend/tag', array('tag' => '%TAG%'), true);
            $hubs[$hub_id]['routes'][] = $route;
        }

        // For each hub, build a list of pages in that hub
        foreach ($hubs as $hub_id => $hub) {
            if (empty($hub['routes'])) {
                continue;
            }
            foreach ($hub['routes'] as $route) {

                // Topics
                $sql = "SELECT t.id, t.url, t.votes_count, t.votes_sum, t.comments_count, t.update_datetime
                        FROM hub_topic AS t
                        WHERE t.hub_id=?
                        GROUP BY t.id";
                foreach ($hm->query($sql, $hub_id) as $t) {
                    $url = str_replace(array('%ID%', '%URL%'), array($t['id'], $t['url']), $route['_url_template_topic']);
                    $priority = 0.2 + min(80, max(1, $t['votes_sum']) + $t['comments_count'] * 4 + $t['votes_count']) / 100.0;
                    $this->addUrl($url, $t['update_datetime'], self::CHANGE_MONTHLY, $priority);
                }

                // Categories
                foreach ($cm->getFullTree($hub_id) as $c) {
                    $url = str_replace('%URL%', $c['url'], $route['_url_template_category']);
                    $this->addUrl($url, $time_now, self::CHANGE_HOURLY, 0.1);
                }

                // Tags
                foreach ($tm->getAll() as $t) {
                    $url = str_replace('%TAG%', $t['name'], $route['_url_template_tag']);
                    $this->addUrl($url, $time_now, self::CHANGE_HOURLY, 0.1);
                }

            }
        }
    }
}

