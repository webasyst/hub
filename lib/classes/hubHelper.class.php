<?php

class hubHelper
{
    protected static $hubs = array();

    public static function getSorting($short = false)
    {
        $result = array(
            'recent' => array(
                'short_name' => _w('Newest'),
                'name' => _w('Newest topics on top'),
            ),
            'popular'    => array(
                'short_name' => _w('Popular'), // top rated
                'name' => _w('Top rated topics on top'),
            ),
            'updated'    => array(
                'short_name' => _w('Last updated'),
                'name' => _w('Last updated topics on top'),
            ),
            'unanswered' => array(
                'short_name' => _w('Unanswered'),
                'name' => _w('Unanswered topics on top'),
            ),
            //'followers' => _w('Most followed'),
            //'comments' => _w('Most commented'),
        );

        foreach($result as $id => $s) {
            if ($short) {
                $result[$id] = $s['short_name'];
            } else {
                $result[$id]['id'] = $id;
            }
        }

        return $result;
    }

    public static function getBaseTypes()
    {
        return array(
            'page'     => array(
                'name'         => _w('Page'),
                'description'  => _w(
                    'Articles, guides, how-tos, tutorials and other materials that should be considered as static informational pages authored by a particular user.'
                ),
                'frontend_add' => false,
                // available sorting options
                'sorting'      => array(
                    'recent',
                    'popular',
                    'updated',
                ),
            ),
            'forum'    => array(
                'name'        => _w('Forum thread (discussion)'),
                'description' => _w('Classic forum thread with plain comment list sorted chronologically.'),
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
                'solution'    => true,
            ),
            'feedback' => array(
                'name'        => _w('Feedback (+/-)'),
                'description' => _w('Ideas, bug reports, thank-yous and other feedback.'),
                'sorting'     => array(
                    'recent',
                    'popular',
                    'updated',
                ),
                'solution'    => true,
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
                'archived'   => array(
                    'name' => _w('Archived'),
                ),
                'answered'   => array(
                    'name' => _w('Answered'),
                ),
                'pending'    => array(
                    'name' => _w('Pending'),
                ),
                'accepted'   => array(
                    'name' => _w('Accepted'),
                ),
                'confirmed'  => array(
                    'name' => _w('Confirmed'),
                ),
                'inprogress' => array(
                    'name' => _w('In progress'),
                ),
                'complete'   => array(
                    'name' => _w('Complete'),
                ),
                'fixed'      => array(
                    'name' => _w('Fixed'),
                ),
                'rejected'   => array(
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
        $oldUiIcons = array(
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

        $newUiIcons = array(
            'filter',
            'star',
            'bug',
            'bolt',
            'lightbulb',
            'comments',
            'lock',
            'lock-open',
            'broom',
            'user-friends',
            'chart-line',
            'book',
            'map-marker-alt',
            'camera',
            'clock',
            'sticky-note',
            'file-alt',
            'car',
            'save',
            'cookie',
            'radiation-alt',
            'video',
            'mug-hot',
            'home',
            'smile',
            'medal',
            'bullseye',
            'store'
        );

        return (wa()->whichUI() === '1.3') ? $oldUiIcons : $newUiIcons;
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
            'discussion2',
            'star',
            'solution',
            'question',
            'thumbup',
            'thumbdown',
            'camera',
            'page',
            'smile',
            'unsmile',
            'help',
            'beer',
            'cart',
            'crown',
            'trophy',
            'location',
            'list',
            'unknown'
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
            if ($size == 48) {
                $size = 50;
            }
            if (!empty($params['contact_id'])) {
                $contact_id = $params['contact_id'];
            } elseif (!empty($params['contact']['id'])) {
                $contact_id = $params['contact']['id'];
            }
            if (!empty($contact_id)) {
                $photo_id = !empty($params['contact']['photo']) ? $params['contact']['photo'] : null;
                if (!empty($params['contact']['email'])) {
                    $photo_url = hubHelper::getGravatarUrl($params['contact']['email'][0], $size, waContact::getPhotoUrl($contact_id, $photo_id, $size, $size, 'person', true));
                } else {
                    $photo_url = waContact::getPhotoUrl($contact_id, $photo_id, $size, $size, 'person', true);
                }
                $attributes['style'] = sprintf(' background-image: url(%s);', $photo_url);
            } else {
                $attributes['style'] = sprintf(' background-image: url(%s);', waContact::getPhotoUrl(null, null, $size, $size, 'person', true));
            }

            if ($size == 20) {
                $attributes['class'] .= " icon16 userpic userpic20"; //for proper appearance in within .menu-v and .menu-h
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
        $base_types = self::getBaseTypes();
        $type_model = new hubTypeModel();
        $types = $type_model->getTypes();
        foreach ($types as &$type) {
            $type['settings'] = json_decode(ifset($type['settings'], '{}'), true);
            if (isset($base_types[$type['type']])) {
                foreach ($base_types[$type['type']] as $k => $v) {
                    if (!isset($type[$k])) {
                        $type[$k] = $v;
                    }
                }
            }
            unset($type);
        }
        return $types;
    }

    protected static function sanitizeUrl($url)
    {
        if (empty($url)) {
            return '';
        }
        $url_alphanumeric = preg_replace('~&amp;[^;]+;~i', '', $url);
        $url_alphanumeric = preg_replace('~[^a-z0-9:]~i', '', $url_alphanumeric);
        if (preg_match('~^(javascript|vbscript):~i', $url_alphanumeric)) {
            return '';
        }

        static $url_validator = null;
        if (!$url_validator) {
            $url_validator = new waUrlValidator();
        }

        if (!$url_validator->isValid($url)) {
            $url = 'http://'.preg_replace('~^([^:]+:)?(//|\\\\\\\\)~', '', $url);
        }

        return $url;
    }

    // Helper for sanitizeHtml()
    public static function sanitizeHtmlAHref($m)
    {
        $url = self::sanitizeUrl(ifset($m[1]));
        return '<a href="'.self::$attr_start.$url.self::$attr_end.'" target="_blank" rel="nofollow">';
    }

    // Helper for sanitizeHtml()
    public static function sanitizeHtmlImg($m)
    {
        $url = self::sanitizeUrl(ifset($m[1]));
        if (!$url) {
            return '';
        }

        $attributes = array(
            'src' => $url,
        );

        $legal_attributes = array(
            'title',
            'alt'
        );

        foreach ($legal_attributes as $attribute) {
            preg_match(
                '~
                &lt;
                    img\s+
                    .*?
                    '.$attribute.'=&quot;([^"\'><]+?)&quot;
                    .*?
                    /?
                &gt;
            ~iuxs',
                $m[0],
                $match
            );

            if ($match) {
                $val = $match[1];
                $attributes[$attribute] = $val;
            }
        }

        foreach ($attributes as $attribute => $val) {
            $attributes[$attribute] = $attribute.'="'.self::$attr_start.$val.self::$attr_end.'"';
        }

        return '<img '.join(' ', $attributes).'>';
    }

    public static function sanitizeHtmlIframe($m)
    {
        $url = $m[1];
        if (preg_match('~^(https?:)?//player.vimeo.com/video/[0-9]+$|^//www.youtube.com/embed/[a-z0-9\-\_]+$~i', $url)) {
            return html_entity_decode($m[0], ENT_COMPAT, 'UTF-8');
        } else {
            return _w('[Incorrect video embed]');
        }
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

        // Replace all &entities; with UTF8 chars, except for &, <, >.
        $content = str_replace(array('&amp;','&lt;','&gt;'), array('&amp;amp;','&amp;lt;','&amp;gt;'), $content);
        $content = preg_replace('/(&#*\w+)[\x00-\x20]+;/u', '$1;', $content);
        $content = preg_replace('/(&#x*[0-9A-F]+);*/iu', '$1;', $content);
        $content = html_entity_decode($content, ENT_COMPAT, 'UTF-8');

        // Remove redactor data-attribute
        $content = preg_replace('/(<[^>]+)data-redactor[^\s>]+/uis', '$1', $content);

        // Encode everything that seems unsafe.
        $content = htmlentities($content, ENT_QUOTES, 'UTF-8');

        //
        // The plan is: to quote everything, then unquote parts that seem safe.
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
                    (.*?)
                &gt;
            ~iux',
            array('hubHelper', 'sanitizeHtmlAHref'),
            $content
        );

        // Allow width="" height="" as attributes in iframe, but replace them internally with style=""
        $youtube_pattern = '~
            &lt;
                iframe
                \s+
                width=
                    &quot;
                        (\d+)
                    &quot;
                \s+
                height=
                    &quot;
                        (\d+)
                    &quot;
            ~iux';

        $youtube_style = '&lt;iframe style=&quot;width: $1px; height: $2px;&quot;';
        $content = preg_replace($youtube_pattern, $youtube_style, $content);

        // iframes for youtube and vimeo
        // <iframe style="width: 500px; height: 281px;" src="//www.youtube.com/embed/TOTIBzyWjLM" frameborder="0" allowfullscreen=""></iframe>
        // <iframe style="width: 500px; height: 281px;" src="//player.vimeo.com/video/22439234" frameborder="0" allowfullscreen=""></iframe>
        // <iframe style="width: 500px; height: 281px;" src="//player.vimeo.com/video/110289386" allowfullscreen="" frameborder="0"></iframe>
        $content = preg_replace_callback(
            '~
                &lt;
                    iframe\s+
                    style=&quot;\s*
                        width:\s+[0-9]+px;\s+height:\s+[0-9]+px;
                    \s*&quot;
                    \s+
                    src=&quot;
                        ([^"><]+?)
                    &quot;
                    \s+
                    (
                        frameborder=&quot;0&quot;
                        \s+
                        allowfullscreen=&quot;&quot;
                    |
                        allowfullscreen=&quot;&quot;
                        \s+
                        frameborder=&quot;0&quot;
                    )
                    \s*
                &gt;
                \s*
                &lt;
                    /iframe
                &gt;
            ~iux',
            array('hubHelper', 'sanitizeHtmlIframe'),
            $content
        );

        // <img src="...">
        $content = preg_replace_callback(
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
            array('hubHelper', 'sanitizeHtmlImg'),
            $content
        );

        // Simple tags: <b>, <i>, <u>, <pre>, <blockquote> and closing counterparts.
        // All attributes are removed.
        $content = preg_replace(
            '~
                &lt;
                    (/?(?:a|b|i|u|pre|blockquote|p|strong|em|del|strike|span|ul|ol|li|div|span|br|table|thead|tbody|tfoot|tr|td|th|figure|figcaption))
                    ((?!&gt;)[^a-z\-\_]((?!&gt;).)+)?
                &gt;
            ~iux',
            '<\1>',
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

        // Remove \n around <blockquote> startting and ending tags
        $content = preg_replace('~(?U:\n\s*){0,2}<(/?blockquote)>(?U:\s*\n){0,2}~i', '<\1>', $content);

        return $content;
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
        $empty_contact['id'] = null;
        $empty_contact['email'] = '';
        foreach (array(20, 50, 96) as $size) {
            $empty_contact['photo_url_'.$size] = wa()->getRootUrl().'wa-content/img/userpic'.$size.'@2x.jpg';
        }
        $collection = new waContactsCollection('id/'.implode(',', $contact_ids), array('photo_url_2x' => true));
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

    public static function getGravatarUrl($contact_email, $size = 96, $userpic_url = null)
    {
        // When user has a non-default userpic, don't bother
        if ($userpic_url && strpos($userpic_url, '/wa-content/img/') === false) {
            return $userpic_url;
        }

        // Make sure email is a string
        if (!is_string($contact_email)) {
            if (!empty($contact_email['value'])) {
                $contact_email = $contact_email['value'];
            } else {
                $contact_email = null;
            }
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
                return wa()->getConfig()->getRootUrl().'wa-content/img/userpic'.$size.'@2x.jpg';
            }
        }

        // Default gravatar pic
        $default = $app_settings_model->get('hub', 'gravatar_default', 'retro');
        if ($default == 'custom') {
            $default = urlencode(wa()->getConfig()->getRootUrl(true).'wa-content/img/userpic'.$size.'@2x.jpg');
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
