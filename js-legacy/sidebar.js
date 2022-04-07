(function ($) {
    $.sidebar = {

        /**
         * @var {jQuery} Jquery object of sidebar
         */
        sidebar: null,

        options: {},

        init: function (options) {
            this.options = options || {};
            this.sidebar = $('#wa-app').find('.sidebar:first');

            this.initCollapsibleSections();
            this.initCategoriesDragAndDrop();
            this.initFiltersDragAndDrop();
            this.highlight();
            $(window).trigger($.Event('wa_sidebar_loaded'));

            // Reload sidebar every once in a while
            if (!this.reloader) {
                this.reloader = setInterval(function() {
                    $.sidebar.reload({}, true);
                }, 300000);
            }
        },

        setHub: function(hub_id) {
            var our_hub_category_list = $('#hub-'+hub_id).closest('.h-hub').removeClass('folded').find('.category-list:first');
            $('#wa-app > .sidebar .category-list').not(our_hub_category_list).slideUp(200, function() {
                $(this).closest('.h-hub').addClass('folded');
            });
            our_hub_category_list.slideDown(200);
        },

        initCategoriesDragAndDrop: function() {

            var $ul = this.sidebar.find('.category-list ul');
            $ul.sortable({
                axis: 'y',
                items: '> li',
                distance: 5,
                //containment: 'parent',
                update: function (e, ui) {
                    var params = {
                        id: ui.item.data('categoryId'),
                        before_id: ui.item.prev().data('categoryId') || 0
                    };
                    $.post('?module=categories&action=move', params, null, 'json').always(function(r, status) {
                        if (status != 'success' || r.status !== 'ok') {
                            console && console.log(status, r.errors || r);
                        }
                    });
                }
            });
        },

        initFiltersDragAndDrop: function() {

            var $ul = $('#h-sidebar-filters-list');

            // Initial order
            var lis = {};
            $ul.children().each(function(i, el) {
                var $el = $(el);
                lis[$el.data('filterId')] = $el;
            });
            var initial_order = $.storage.get('hub/filters_order');
            if (initial_order && initial_order.reverse) {
                $.each(initial_order.reverse(), function(i, filter_id) {
                    if (lis[filter_id]) {
                        lis[filter_id].prependTo($ul);
                    }
                });
            }

            $ul.sortable({
                axis: 'y',
                items: '> li',
                distance: 5,
                //containment: 'parent',
                update: function () {
                    var filter_ids = [];
                    $ul.children().each(function(i, el) {
                        filter_ids.push($(el).data('filterId'));
                    });
                    $.storage.set('hub/filters_order', filter_ids);
                }
            });
        },

        initCollapsibleSections: function() {

            var collapseHandler = function (el, not_save) {
                var key = 'hub/' + $(el).attr('id') + '/collapse';
                var i = $(el).find('.collapse-handler');
                var d = $(el).next();
                if (i.hasClass('darr')) {
                    i.removeClass('darr').addClass('rarr');
                    d.hide();
                    if (!not_save) {
                        $.storage.set(key, 1);
                    }
                } else {
                    i.removeClass('rarr').addClass('darr');
                    d.show();
                    if (!not_save) {
                        $.storage.del(key, 1);
                    }
                }
                return false;
            };

            this.sidebar.on('click', '.collapse', function () {
                collapseHandler(this);
            });
            this.sidebar.find('.collapse').each(function () {
                var key = 'hub/' + $(this).attr('id') + '/collapse';
                if ($.storage.get(key)) {
                    collapseHandler(this, true);
                }
            });

        },

        // Highlight current active link in sidebar
        highlight: function(hash) {
            var hash = $.hub.helper.cleanHash(hash),
                hash = hash.replace('\"', '\\"');
            $('#wa-app > .sidebar li.selected').removeClass('selected');
            var tmp_a = $('#wa-app > .sidebar li a[href="'+hash+'"]');
            if (!tmp_a.length && hash.length > 2) {
                tmp_a = $('#wa-app > .sidebar li a[href="' + $.hub.helper.cleanHash(hash.replace(/\/[^\/]+\/$/, '')) + '"]');
            }
            if (tmp_a.length) {
                tmp_a.closest('li').addClass('selected');
            }
        },

        reload: function(callback, background) {
            var current_hub_id = $('.h-hub:not(.folded)').data('hub-id');
            let requestUrl = '?sidebar=1';
            if (background) {
                requestUrl += '&background_process=1';
            }

            $.post(requestUrl, function(r) {
                $.sidebar.sidebar.html(r);
                $.sidebar.init();
                current_hub_id && $.sidebar.setHub(current_hub_id);
                typeof callback === 'function' && callback();
            });
        }

    };
})(jQuery);
