<?php

wa('hub');
$hub_model = new hubHubModel();
$hubs = $hub_model->getNames(true);

$url_type_script = <<<SCRIPT
<script>
    $(function() {
        var meta_title = $('input[name="params[title]"]'),
            meta_description = $('textarea[name="params[meta_description]"]'),
            og_title = $('input[name="params[og_title]"]'),
            og_description = $('textarea[name="params[og_description]"]'),
            switcher = $('input[name="params[use_default_settings]"]');

        meta_title.on('change', function () {
            if (switcher.prop('checked')) {
                og_title.val($(this).val());
            }
        });

        meta_description.on('change', function () {
            if (switcher.prop('checked')) {
                og_description.val($(this).val());
            }
        });

        switcher.on('change', function () {
            if ($(this).prop('checked')) {
                og_title.attr('disabled', true).val(meta_title.val());
                og_description.attr('disabled', true).val(meta_description.val());
            } else {
                og_title.attr('disabled', false).val('');
                og_description.attr('disabled', false).val('');
            }
        });

        if (og_title.val().length === 0 && og_description.val().length === 0) {
            switcher.prop('checked', true).change();
        }
    });
</script>
SCRIPT;

$og_section = '<h5 class="heading" style="margin-bottom: 0">' . _w('Social media tags (Open Graph)') . '</h5><span class="hint">' . _w('For detailed information on Open Graph parameters and examples please refer to <a href="http://ogp.me" target="_blank">ogp.me</a>.') . '</span>';

return array(
    'params' => array(
        'hub_id' => array(
            'name' => _w('Hub'),
            'type' => 'select',
            'items' => $hubs
        ),
        'home_sort' => array(
            'name' => _w('Homepage default topic sort order'),
            'type' => 'select',
            'items' => array(
                'popular' => _w('Popular'),
                'recent' => _w('Newest'),
                'updated' => _w('Updated'),
            )
        ),
        'title' => array(
            'name' => _w('Homepage title <title>'),
            'type' => 'input',
        ),
        'meta_keywords' => array(
            'name' => _w('Homepage META keywords'),
            'type' => 'input'
        ),
        'meta_description' => array(
            'name' => _w('Homepage META description'),
            'type' => 'textarea'
        ),
        $og_section,
        'use_default_settings' => array(
            'name' => _w('Use these meta tags for social sharing too'),
            'type' => 'checkbox',
        ),
        'og_site_name' => array(
            'name' => _w('Site name'),
            'type' => 'input',
            'description' => _w('Brief website name. May be used instead of a URL; e.g., by Telegram messenger.')
        ),
        'og_locale' => array(
            'name' => _w('Locale'),
            'type' => 'input',
            'description' => _w('Page’s main locale.')
        ),
        'og_title' => array(
            'name' => _w('Social sharing title (og:title)'),
            'type' => 'input',
        ),
        'og_image' => array(
            'name' => _w('Social sharing image URL (og:image)'),
            'type' => 'input'
        ),
        'og_video' => array(
            'name' => _w('Social sharing video URL (og:video)'),
            'type' => 'input'
        ),
        'og_description' => array(
            'name' => _w('Social sharing description (og:description)'),
            'type' => 'textarea',
            'description' => $url_type_script
        ),
    ),
    'vars'   => array(
        '$wa' => array(
            '$wa->hub->topic($id)' => _w(''),
            '$wa->hub->topics($hash[, $offset[, $limit[, $hub_id]]])' => _w(''),
            '$wa->hub->comments($limit[, $hub_id])' => _w(''),
            '$wa->hub->staff([$hub_id])' => _w('Stuff as set up in hub settings.'),
            '$wa->hub->categories([bool $priority_topics[, $hub_id]])' => _w('Categories of given hub. When <code>$priority_topics</code> is <em>true</em>, list of high-priority topics is returned for each category.'),
            '$wa->hub->tags([$limit[, $hub_id]])' => _w(''),
            '$wa->hub->authors([$limit[, $hub_id]])' => _w(''),
        ),
    ),
);

