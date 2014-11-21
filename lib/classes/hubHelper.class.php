<?php

class hubHelper
{
    protected static $hubs = array();

    public static function getSorting()
    {
        return array(
            'recent'     => _w('Newest'),
            'popular'    => _w('Popular'), // top rated
            'updated'    => _w('Last updated'),
            'unanswered' => _w('Unanswered'),
            //'followers' => _w('Most followed'),
            //'comments' => _w('Most commented'),
        );
    }

    public static function getBaseTypes()
    {
        return array(
            'page'     => array(
                'name'        => _w('Page'),
                'description' => _w(
                    'Articles, guides, how-tos, tutorials and other materials that should be considered as static informational pages authored by a particular user.'
                ),
                // available sorting options
                'sorting'     => array(
                    'recent',
                    'popular',
                    'updated',
                ),
            ),
            'forum'    => array(
                'name'        => _w('Forum thread (discussion)'),
                'description' => _w('A bug report is attached to a specific product version, and has a status'),
                'sorting'     => array(
                    'updated',
                ),
            ),
            'question' => array(
                'name'        => _w('Question & Answers'),
                'description' => _w('Questions & Answers. The right topic type for getting user questions answered.'),
                'sorting'     => array(
                    'recent',
                    'popular',
                    'updated',
                    'unanswered',
                ),
            ),
            'feedback' => array(
                'name'        => _w('Feedback (+/-)'),
                'description' => _w('Ideas, bug reports, thank-yous and other feedback.'),
                'sorting'     => array(
                    'recent',
                    'popular',
                    'updated',
                ),
            ),
            'custom'   => array(
                'name'        => _w('Custom'),
                'description' => _w('Custom')
            ),
        );
    }

    public static function getBadges()
    {
        static $result = null;
        if (empty($result)) {
            $result = array(
                'archived'  => array(
                    'name' => _w('Archived'),
                ),
                'answered'  => array(
                    'name' => _w('Answered'),
                ),
                'pending'   => array(
                    'name' => _w('Pending'),
                ),
                'accepted'  => array(
                    'name' => _w('Accepted'),
                ),
                'confirmed' => array(
                    'name' => _w('Confirmed'),
                ),
                'complete'  => array(
                    'name' => _w('Complete'),
                ),
                'fixed'     => array(
                    'name' => _w('Fixed'),
                ),
                'rejected'  => array(
                    'name' => _w('Rejected'),
                ),
            );

            foreach ($result as $id => &$b) {
                $b['id'] = $id;
            }
            unset($b);
        }

        return $result;
    }

    public static function getBadge($badge_id)
    {
        $badges = self::getBadges();
        if (!$badge_id || !isset($badges[$badge_id])) {
            return null;
        }
        return $badges[$badge_id];
    }

    /**
     * @return array of all available icons for custom topic filters
     */
    public static function getFilterIcons()
    {
        return array(
            'funnel',
            'star',
            'bug',
            'lightning',
            'light-bulb',
            'comments',
            'lock',
            'lock-unlocked',
            'broom',
            'contact',
            'reports',
            'books',
            'marker',
            'lens',
            'alarm-clock',
            'notebook',
            'blog',
            'car',
            'disk',
            'cookie',
            'burn',
            'clapperboard',
            'cup',
            'home',
            'smiley',
            'medal',
            'target',
            'store'
        );
    }

    /**
     * Same as ::getBadges() but only returns badges available for given topic type
     * @param int|string|array $type type_id, or type array as returned by ::getTypes(), or one of: page, forum, question, feedback.
     * @return array
     */
    public static function getBadgesByType($type)
    {
        static $badges_by_type = null;
        if (empty($badges_by_type)) {
            $badges = hubHelper::getBadges();
            $badges_by_type = array(
                'page'     => array(),
                'forum'    => array('archived'),
                'question' => array('answered', 'archived'),
                'feedback' => array_keys($badges), // some of them can be turned off in type settings
                // custom?..
            );

            foreach ($badges_by_type as &$v) {
                $v = array_intersect_key($badges, array_flip($v));
            }
            unset($v);
        }

        if (wa_is_int($type)) {
            $types = hubHelper::getTypes();
            if (empty($types[$type])) {
                return array();
            }
            $type = $types[$type];
        }
        if (!is_array($type)) {
            if (empty($badges_by_type[$type])) {
                return array();
            } else {
                return $badges_by_type[$type];
            }
        }

        $result = $badges_by_type[$type['type']];
        if (!empty($type['settings']['badges']) && is_array($type['settings']['badges'])) {
            $result = array_intersect_key($result, $type['settings']['badges']);
        }
        return $result;
    }

    public static function getIcon($icon, $params = array())
    {
        $attributes = array(
            'class' => '',
            'style' => '',
        );
        foreach ($params as $field => $value) {
            if (in_array($field, array("class", "id", "title", "style"))) {
                $attributes[$field] = $value;
            }
        }
        $attributes['class'] = " js-icon icon16";
        if (strpos($icon, '.')) {
            $attributes['style'] .= sprintf("background-image: url('%s'); background-repeat: no-repeat;", htmlentities($icon, ENT_NOQUOTES, 'utf-8'));

        } else {
            $attributes['class'] .= ' '.$icon;
        }
        $html = '<i';
        foreach ($attributes as $field => $value) {
            if (!empty($value)) {
                $html .= sprintf(' %s="%s"', $field, trim(htmlentities($value, ENT_QUOTES, 'utf-8')));
            }
        }
        $html .= '></i>';
        return $html;
    }


    public static function getGlyphs()
    {
        return array(
            'info',
            'bug',
            'idea',
            'exclamation',
            'discussion',
            'star',
            'solution',
            'question',
            'thumbup',
            'thumbdown',
            'unknown',
        );
    }

    /**
     * @param string $glyph
     * @param int $size indicates glyph size in pixels. Supported options: 16, 24, 32, 48
     * @param bool $selected indicates if glyph activity status: either gray wireframe or solid
     * @param string[] $params extra attributes of glyph HTMLElement e.g. title, class, style, id
     * @return string
     */
    public static function getGlyph($glyph, $size = 16, $selected = false, $params = array())
    {
        if (!in_array($size, array(16, 24, 32, 48), true)) {
            $size = 16;
        }
        $attributes = array(
            'class' => '',
            'style' => '',
        );
        foreach ($params as $field => $value) {
            if (in_array($field, array("class", "id", "title", "style"))) {
                $attributes[$field] = $value;
            }
        }
        $attributes['class'] = " js-glyph";

        if ($glyph == 'userpic') {

            if ($size == 16) {
                $size = 20;
            }

            $contact_id = ifset($params['contact_id'], ifempty($params['contact']['id'], wa()->getUser()->getId()));
            $photo_id = ifempty($params['contact']['photo'], wa()->getUser()->get('photo'));
            $attributes['style'] = sprintf(' background-image: url(%s);', waContact::getPhotoUrl($contact_id, $photo_id, $size));

            if ($size == 20) {
                $attributes['class'] .= " icon16 userpic20"; //for proper appearance in within .menu-v and .menu-h
            } else {
                $attributes['class'] .= ' userpic h-userpic'.$size;
            }
        } else {

            $attributes['class'] .= " h-glyph".$size;
            $pattern = ' %s';
            if ($selected) {
                $pattern .= ' selected';
            }
            if ($size == 16) {
                $attributes['class'] .= " icon16"; //for proper appearance in within .menu-v and .menu-h
            }
            if (strpos($glyph, '.')) {
                //custom glyph
                $attributes['class'] .= " custom";
                if ($selected) {
                    $attributes['class'] .= ' selected';
                }
                $attributes['style'] = sprintf(' background-image: url(%s);', htmlentities($glyph, ENT_QUOTES, 'utf-8'));
            } else {
                $attributes['class'] .= sprintf($pattern, ifempty($glyph, 'info'));
            }
        }
        $html = '<i';
        foreach ($attributes as $field => $value) {
            if (!empty($value)) {
                $html .= sprintf(' %s="%s"', $field, trim(htmlentities($value, ENT_QUOTES, 'utf-8')));
            }
        }
        $html .= '></i>';
        return $html;
    }


    public static function getHub($hub_id = null)
    {
        if (!$hub_id) {
            $hub_id = waRequest::param('hub_id');
        }
        if (!$hub_id) {
            return array();
        }

        if (!isset(self::$hubs[$hub_id])) {
            $hub_model = new hubHubModel();
            self::$hubs[$hub_id] = $hub_model->getById($hub_id);
            if (self::$hubs[$hub_id]) {
                $hub_params_model = new hubHubParamsModel();
                self::$hubs[$hub_id]['params'] = $hub_params_model->getParams($hub_id);
            }
        }
        return self::$hubs[$hub_id];
    }

    public static function getTypes()
    {
        $type_model = new hubTypeModel();
        $types = $type_model->getAll('id');
        foreach ($types as &$type) {
            $type['settings'] = json_decode(ifset($type['settings'], '{}'), true);
            unset($type);
        }
        return $types;
    }

    // Helper for sanitizeHtml()
    public static function sanitizeHtmlAHref($m)
    {
        static $url_validator = null;
        if (!$url_validator) {
            $url_validator = new waUrlValidator();
        }
        $url = preg_replace('~^javascript:~i', '', trim($m[1]));

        if (!$url_validator->isValid($url)) {
            $url = 'http://'.$url;
        }

        return '<a href="'.self::$attr_start.$url.self::$attr_end.'" target="_blank" rel="nofollow">';
    }

    protected static $attr_end = null;
    protected static $attr_start = null;

    /**
     * Escapes everything in given user-supplied string except several HTML tags considered safe.
     * @param string $content Html string
     * @return string
     */
    public static function sanitizeHtml($content)
    {
        // Make sure it's a valid UTF-8 string
        $content = preg_replace('~\\xED[\\xA0-\\xBF][\\x80-\\xBF]~', '?', mb_convert_encoding($content, 'UTF-8', 'UTF-8'));
        $content = preg_replace('/(<[^>]+)data-redactor[^\s>]+/uis', '$1', $content);

        // Encode everything that seems unsafe.
        // Does not re-encode existing entities if they are present (4th parameter false).
        $content = htmlentities($content, ENT_QUOTES, 'UTF-8');

        //
        // Decode back tags that are allowed.
        //

        // A trick we use to make sure there are no tags inside attributes of other tags.
        do {
            self::$attr_start = $attr_start = uniqid('<ATTRSTART').'>';
            self::$attr_end = $attr_end = uniqid('<ATTREND').'>';
        } while (strpos($content, $attr_start) !== false || strpos($content, $attr_end) !== false);

        // <a href="...">
        $content = preg_replace_callback(
            '~
                &lt;
                    a
                    \s+
                    href=&quot;
                        ([^"><]+?)
                    &quot;
                    (\s.*?)?
                &gt;
            ~iux',
            array('hubHelper', 'sanitizeHtmlAHref'),
            $content
        );

        // Simple tags: <b>, <i>, <u>, <pre>, <blockquote> and closing counterparts
        $content = preg_replace(
            '~
                &lt;
                    (/?(?:a|b|i|u|pre|blockquote|p|strong|em|del|strike|span|ul|ol|li|div|span|br))
                    (\s.*?)?
                &gt;
            ~iux',
            '<\1>',
            $content
        );


        // <img src="...">
        $content = preg_replace(
            '~
                &lt;
                    img\s+
                    src=&quot;
                        ([^"><]+?)
                    &quot;
                    .*?
                    /?
                &gt;
            ~iux',
            '<img src="\1">',
            $content
        );

        // Remove $attr_start and $attr_end from legal attributes
        $content = preg_replace(
            '~
                '.preg_quote($attr_start).'
                ([^"><]*)
                '.preg_quote($attr_end).'
            ~ux',
            '\1',
            $content
        );

        // Remove illegal attributes, i.e. those where $attr_start and $attr_end are still present
        $content = preg_replace(
            '~
                '.preg_quote($attr_start).'
                .*
                '.preg_quote($attr_end).'
            ~uxU',
            '',
            $content
        );
        $content = str_replace('&amp;', '&', $content);

        // Being paranoid... remove $attr_start and $attr_end if still present anywhere.
        // Should not ever happen.
        $content = str_replace(array($attr_start, $attr_end), '', $content);

        return preg_replace('~(?U:\n\s*){0,2}<(/?blockquote)>(?U:\s*\n){0,2}~i', '<\1>', $content);
    }

    public static function transliterate($str, $strict = true)
    {
        $str = preg_replace('/\s+/', '-', $str);
        if ($str) {
            foreach (waLocale::getAll() as $lang) {
                $str = waLocale::transliterate($str, $lang);
            }
        }
        $str = preg_replace('/[^a-zA-Z0-9_-]+/', '', $str);
        if ($strict && !$str) {
            $str = date('Ymd');
        }
        return strtolower($str);
    }

    public static function getBreadcrumbs($category_id, $include_itsef = false)
    {
        $category_model = new hubCategoryModel();

        $breadcrumbs = array();
        $category = $category_model->getById($category_id);
        if ($category) {
            if ($include_itsef) {
                $breadcrumbs[] = array(
                    'url'  => wa()->getRouteUrl(
                        '/frontend/category',
                        array(
                            'category_url' => $category['url']
                        ),
                        true
                    ),
                    'name' => $category['name']
                );
            }
        }
        return $breadcrumbs;
    }

    public static function getUrls($hub_id, $category = array())
    {
        $frontend_urls = array();
        if ($hub_id = intval($hub_id)) {
            $category_url = null;
            $path = '/frontend';
            $params = array();
            if (!empty($category)) {
                $path = '/frontend/category';
                if (is_array($category)) {
                    if (isset($category['url'])) {
                        $params['category_url'] = $category['url'];

                    } elseif (isset($category['id'])) {
                        //TODO
                    }
                } else {
                    $params['category_url'] = $category;
                }
            }

            $routing = wa()->getRouting();
            $current_route = $routing->getRoute();
            $current_domain = $routing->getDomain();

            static $domain_routes = null;
            if (empty($domain_routes)) {
                $domain_routes = $routing->getByApp('hub');
            }
            foreach ($domain_routes as $domain => $routes) {
                foreach ($routes as $r) {
                    if (!empty($r['private'])) {
                        continue;
                    }
                    if ($hub_id == ifset($r['hub_id'])) {
                        $params['hub_id'] = $hub_id;
                        $frontend_url = $routing->getUrl($path, $params, true, $domain, $r['url']);
                        $frontend_urls[] = array(
                            'url' => $frontend_url
                        );
                    }
                }
            }
        }
        return $frontend_urls;
    }

    public static function getAuthor($contact_id)
    {
        $fields = '*,email,photo_url_96,photo_url_50,photo_url_20';
        $contact_ids = (array)$contact_id;
        $contact_model = new waContactModel();
        $empty_contact = $contact_model->getEmptyRow();
        unset($empty_contact['id']);
        $empty_contact['email'] = '';
        foreach (array(20, 50, 96) as $size) {
            $empty_contact['photo_url_'.$size] = wa()->getRootUrl().'wa-content/img/userpic'.$size.'.jpg';
        }
        $collection = new waContactsCollection('id/'.implode(',', $contact_ids));
        $contacts = $collection->getContacts($fields, 0, count($contact_ids));

        if ($contacts) {
            foreach ($contacts as &$c) {
                $c['name'] = waContactNameField::formatName($c);
                if ($c['email']) {
                    $c['photo_url_20'] = hubHelper::getGravatarUrl($c['email'][0], 20, $c['photo_url_20']);
                    $c['photo_url_50'] = hubHelper::getGravatarUrl($c['email'][0], 50, $c['photo_url_50']);
                    $c['photo_url_96'] = hubHelper::getGravatarUrl($c['email'][0], 96, $c['photo_url_96']);
                }
            }
            unset($c);
            if (wa()->getEnv() == 'frontend') {
                // Badges
                $staff_model = new hubStaffModel();
                $rows = $staff_model->getByField(
                    array(
                        'hub_id'     => waRequest::param('hub_id'),
                        'contact_id' => array_keys($contacts)
                    ),
                    true
                );
                foreach ($rows as $row) {
                    $contacts[$row['contact_id']]['name'] = $row['name'];
                    $contacts[$row['contact_id']]['badge'] = $row['badge'];
                    $contacts[$row['contact_id']]['badge_color'] = $row['badge_color'];
                }

                // Author stats
                $author_model = new hubAuthorModel();
                $rows = $author_model->getByField(
                    array(
                        'contact_id' => array_keys($contacts),
                        'hub_id'     => waRequest::param('hub_id'),
                    ),
                    'contact_id'
                );
                $empty_row = $author_model->getEmptyRow();
                unset($empty_row['contact_id'], $empty_row['hub_id']);
                $author_url = wa()->getRouteUrl('hub/frontend/author', array('id' => '%ID%'));
                foreach ($contacts as &$c) {
                    if ($c['id']) {
                        $c['url'] = str_replace('%ID%', $c['id'], $author_url);
                    } else {
                        $c['url'] = '';
                    }
                    if (isset($rows[$c['id']])) {
                        unset($rows[$c['id']]['contact_id'], $rows[$c['id']]['hub_id']);
                        $c += $rows[$c['id']];
                    } else {
                        $c += $empty_row;
                    }
                }
                unset($c);
            }
        }

        if (is_numeric($contact_id)) {
            if (isset($contacts[$contact_id])) {
                return $contacts[$contact_id];
            } else {
                return $empty_contact;
            }
        } else {
            foreach ($contact_ids as $c_id) {
                if (!isset($contacts[$c_id])) {
                    $contacts[$c_id] = $empty_contact;
                }
            }
            return $contacts;
        }
    }

    public static function getGroups()
    {
        $group_model = new waGroupModel();
        /**
         * @todo get groups only with access to hub
         */
        return $group_model->getNames();
    }

    public static function parseConditions($conditions)
    {
        $parsed = array();
        foreach (explode('&', $conditions) as $condition) {
            if (preg_match('@^(\w+)=(.+)$@', $condition, $matches)) {
                $parsed[$matches[1]] = strpos($matches[2], '||') ? explode('||', $matches[2]) : $matches[2];
            }
        }
        return $parsed;
    }

    public static function getGravatarUrl($contact_email, $size = 48, $userpic_url = null)
    {
        // When user has a non-default userpic, don't bother
        if ($userpic_url && strpos($userpic_url, '/wa-content/img/') === false) {
            return $userpic_url;
        }

        static $app_settings_model = null;
        if (!$app_settings_model) {
            $app_settings_model = new waAppSettingsModel();
        }

        // Are gravatars turned off?
        if (!$app_settings_model->get('hub', 'gravatar') || !$contact_email) {
            if ($userpic_url) {
                return $userpic_url;
            } else {
                return wa()->getConfig()->getRootUrl().'wa-content/img/userpic96.jpg';
            }
        }

        // Default gravatar pic
        $default = $app_settings_model->get('hub', 'gravatar_default', 'retro');
        if ($default == 'custom') {
            $default = urlencode(wa()->getConfig()->getRootUrl(true).'wa-content/img/userpic96.jpg');
        }

        return '//www.gravatar.com/avatar/'.md5($contact_email).'?s='.($size * 2).'&d='.$default;
    }

    /**
     * @param array|int $topic Topic entry row or array of topic ids or topic id
     * @param waUser $user
     * @param int $hub_id Optional second hub id
     * @return array|mixed
     */
    public static function checkTopicRights($topic, $user = null, $hub_id = null)
    {
        if (empty($user)) {
            $user = wa()->getUser();
        }
        if (is_array($topic) && isset($topic['hub_id'])) {
            $access_level = $user->getRights('hub', 'hub.'.$topic['hub_id']);
            if ($hub_id) {
                $access_level = min($access_level, $user->getRights('hub', 'hub.'.$hub_id));
            }
            return (
                //Full rights for hub
                ($access_level >= hubRightConfig::RIGHT_FULL)
                ||
                //Topic owner and has write rights
                (($access_level >= hubRightConfig::RIGHT_READ_WRITE) && ($topic['contact_id'] == $user->getId()))
            );
        } else {
            //get hub_id
            $model = new hubTopicModel();
            $topics = $model->select('id, hub_id, contact_id')->where('id IN(i:topic)', compact('topic'))->fetchAll('id');
            foreach ($topics as &$t) {
                $t = self::checkTopicRights($t, $user, $hub_id);
                unset($t);
            }
            return is_array($topic) ? $topics : reset($topics);
        }
    }
}
