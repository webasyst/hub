(function ($) {
    $.sidebar = {

        /**
         * @var {jQuery} Jquery object of sidebar
         */
        sidebar: null,

        options: {},

        init: function (options) {
            this.options = options || {};
            this.sidebar = $('#wa').find('.js-app-sidebar:first');

            // DOM
            this.$window = $(window);
            this.$skeleton = this.sidebar.find('.js-hub-sidebar-skeleton');
            this.$sidebarBody = this.sidebar.find('.js-hub-sidebar-body');
            this.$sidebarBg = this.sidebar.find('#hub-sidebar-bg');
            this.$addTopicButton = this.sidebar.find('.js-add-topic');
            this.$hubList = this.sidebar.find('.h-hub');
            this.$hubsAll = this.sidebar.find('#hub-all');
            this.$hubSelector = this.sidebar.find('.js-hub-selector');
            this.$hubSelectorBack = this.sidebar.find('.js-hub-selector-back');
            this.$filters = this.sidebar.find('.js-sidebar-filters');
            this.$categories = this.sidebar.find('.js-categories-sort');

            // init events
            this.loadedEvent = $.Event('wa_sidebar_loaded');
            this.hubLoadedEvent = $.Event('wa_sidebar_hub_loaded');
            this.hubUnLoadedEvent = $.Event('wa_sidebar_hub_unloaded');

            // Reload sidebar every once in a while
            if (!this.reloader) {
                this.setIntervalReloader();
            }

            this.bindEvents();

            // trigger loaded event
            this.$window.trigger(this.loadedEvent);

            this.initDragAndDrop();

            // highlight active item
            this.highlight();

            // collapse blocks based on localStorage items
            this.iterateCollapsibleSidebar();
        },

        bindEvents: function() {
            this.$window.on('wa_sidebar_loaded.Hub', $.proxy(this.initMobileSidebar, this));
            this.$window.on('resize.Hub', $.proxy(this.initMobileSidebar, this));

            this.$hubSelector.on('click.Hub', $.proxy(this.showHub, this));
            this.$hubSelectorBack.on('click.Hub', $.proxy(this.resetHub, this));

            this.sidebar.on('click.Hub', '.js-collapse-block', $.proxy(this.collapseBlock, this));

            this.$window.on('resize.Hub', $.proxy(this.initDragAndDrop, this));
            this.$window.on('wa_loaded.Hub', $.proxy(this.removeSkeleton, this));

            this.sidebar.on('click.Hub', '.menu li a, .js-mobile-collapse-sidebar', $.proxy(this.slideUpSidebar, this));
        },

        initDragAndDrop: function() {
            if (this.$window.width() > 768) {
                this.initCategoriesDragAndDrop();
                this.initFiltersDragAndDrop();
            }
        },

        initMobileSidebar: function() {
            if (this.$window.width() > 768) {
                if (this.sidebarData) {
                    this.sidebar.removeData('sidebar');
                    this.sidebarData.unbindEvents();
                    delete this.sidebarData;
                }

                return;
            }

            if (!this.sidebarData) {
                this.sidebar.waShowSidebar();
                this.sidebarData = this.sidebar.data('sidebar');
            }
        },

        slideUpSidebar: function() {
            if (!this.sidebarData) {
                return;
            }

            this.sidebarData.$sidebar_content.slideUp(200);
            this.sidebarData.is_open = false;
        },

        setIntervalReloader: function() {
            this.reloader = setInterval(() => {
                this.reload({}, true);
            }, this.options.reloadTime || 300000);
        },

        clearIntervalReloader: function() {
            clearInterval(this.reloader);
        },

        removeSkeleton: function() {
            this.$sidebarBody.removeClass('hidden');
            this.$skeleton.addClass('hidden');
        },

        setHub: function(hub_id) {
            const $activeHub = this.$sidebarBody.find(`.js-hub-selector[data-hub-id="${hub_id}"]`);
            $activeHub.addClass('prevent-hash-change');
            $activeHub.click();
        },

        showHub: function(event) {
            event.preventDefault();

            if (this.sidebarData) {
                this.slideUpSidebar();
            }

            const $this = $(event.target).closest('.js-hub-selector');
            const selectedHubId = $this.data('hub-id');
            const selectedHubColor = $this.data('hub-color');
            const selectedHubHref = $this.data('href');

            const $selectedHub = this.sidebar.find('#hub-' + selectedHubId);

            this.$hubList.removeClass('active');
            $selectedHub.addClass('active');
            this.$sidebarBg.addClass('h-' + selectedHubColor);
            this.$sidebarBody.addClass('active');
            this.$sidebarBody[0].scrollTo(0, 0);

            const currentColor = selectedHubColor;
            if (currentColor !== this.cacheColor && this.$addTopicButton.length) {
                this.$addTopicButton.removeClass('h-hub-color-' + this.cacheColor);
            }

            this.cacheColor = currentColor;
            if (currentColor !== 'white' && this.$addTopicButton.length) {
                this.$addTopicButton.addClass('h-hub-color-' + currentColor);
            }
            this.$hubsAll.removeClass('active');

            if (!$this.hasClass('prevent-hash-change')) {
                window.location.hash = selectedHubHref;
                this.$hubSelector.removeClass('prevent-hash-change');
            }

            this.$window.trigger(this.hubLoadedEvent);
        },

        resetHub: function(event) {
            if (event) {
                event.preventDefault();
            }

            this.$sidebarBody.removeClass('active');
            this.$sidebarBg.removeClass('h-' + this.cacheColor);
            this.$hubList.removeClass('active');
            if (this.$addTopicButton.length) {
                this.$addTopicButton.removeClass('h-hub-color-' + this.cacheColor);
            }

            this.$window.trigger(this.hubUnLoadedEvent);
        },

        initCategoriesDragAndDrop: function() {
            this.$categories.sortable({
                animation: 150,
                direction: 'vertical',
                handle: '> li',
                onUpdate(event) {
                    if (event.oldIndex === event.newIndex) {
                        return;
                    }

                    const params = {
                        id: $(event.item).data('category-id'),
                        before_id: $(event.item).prev().data('category-id') || 0
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
            const that = this;

            if (that.$window.width() < 760) {
                that.$filters.sortable('disable');
            } else {
                that.$filters.sortable('enable');
            }

            const lis = {};
            that.$filters.children().each(function(i, el) {
                const $el = $(el);
                lis[$el.data('filter-id')] = $el;
            });

            const initial_order = $.storage.get('hub/filters_order');
            if (initial_order && initial_order.reverse) {
                $.each(initial_order.reverse(), function(i, filter_id) {
                    if (lis[filter_id]) {
                        lis[filter_id].prependTo(that.$filters);
                    }
                });
            }

            that.$filters.sortable({
                direction: 'vertical',
                handle: '> li',
                onUpdate(event) {
                    if (event.oldIndex === event.newIndex) {
                        return;
                    }

                    const filter_ids = [];
                    that.$filters.children().each(function(i, el) {
                        filter_ids.push($(el).data('filterId'));
                    });
                    $.storage.set('hub/filters_order', filter_ids);
                }
            });
        },

        collapseBlock: function(event, element) {
            if (event) {
                event.preventDefault();
            }

            let $el;
            if (!element) {
                $el = $(event.target).closest('.js-collapse-block');
            } else {
                $el = $(element);
            }

            const key = 'hub/' + $el.attr('id') + '/collapse';
            const $icon = $el.find('.fa-caret-down');
            const $hidingBlock = $el.closest('.js-collapse-block-wrapper').next();

            if (!$icon.hasClass('fa-rotate-270')) {
                $icon.addClass('fa-rotate-270');
                $hidingBlock.hide();
                $.storage.set(key, 1);
            } else {
                $icon.removeClass('fa-rotate-270');
                $hidingBlock.show();
                $.storage.del(key, 1);
            }
        },

        iterateCollapsibleSidebar: function() {
            const that = this;

            this.sidebar.find('.js-collapse-block').each(function() {
                const key = 'hub/' + $(this).attr('id') + '/collapse';
                if ($.storage.get(key)) {
                    that.collapseBlock(null, $(this));
                }
            });
        },

        highlight: function(hash) {
            let hashHighlight = $.hub.helper.cleanHash(hash);
            hashHighlight = hashHighlight.replace('\"', '\\"');

            this.sidebar.find('.selected').removeClass('selected');

            if (!hashHighlight.length) {
                this.sidebar.find('a[href="#/"]').parent().addClass('selected');
            }

            let $link = this.sidebar.find('a[href="'+hashHighlight+'"]');

            if (!$link.length && hashHighlight.length > 2) {
                $link = this.sidebar.find('a[href="' + $.hub.helper.cleanHash(hashHighlight.replace(/\/[^\/]+\/$/, '')) + '"]');
            }

            if ($link.length) {
                $link.closest('li').addClass('selected');
            }
        },

        reload: function(callback, background) {
            let requestUrl = '?sidebar=1';
            if (background) {
                requestUrl += '&background_process=1';
            }

            $.post(requestUrl, (r) => {
                const current_hub_id = this.$sidebarBody.find('.h-hub.active').data('hub-id');

                this.unbindEvents();
                this.sidebar.html(r);
                this.init();
                this.$window.trigger('wa_loaded.Hub');

                current_hub_id && $.sidebar.setHub(current_hub_id);
                typeof callback === 'function' && callback();
            });
        },

        unbindEvents: function() {
            this.sidebar.off('.Hub');
            this.$hubSelector.off('.Hub');
            this.$hubSelectorBack.off('.Hub');
            this.$window.off('.Hub');

            this.sidebar.removeData('sidebar');
            delete this.sidebarData;

            this.clearIntervalReloader();
        }

    };
})(jQuery);
