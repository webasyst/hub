(function ($) {

    $.storage = new $.store();
    // js controller
    $.hub = {
        lang: false,
        framework_version: false,
        options: {
            accountName: ''
        },
        deleteDialog: {},
        // init js controller
        init: function (options) {

            this.options = options || {};
            this.waLoading = $.waLoading();
            this.topics_count = parseInt(this.options.topics_count['all']);

            $.sidebar.init({
                reloadTime: 300000,
            });

            // DOM
            this.$window = $(window);
            this.$body = $('body');
            this.$content = $('#content');
            this.$addTopicButtonMobile = $('.js-add-topic-mobile');
            this.$reviewWidget = $('.i-product-review-widget-wrappper');

            // loaded event
            this.loadedEvent = $.Event('wa_loaded');

            this.$window.on('wa_loaded.pulsar', $.proxy(this.setPulsar, this));
            this.toggleAddTopicButton();

            // Init dispatcher based on location.hash
            if (typeof($.History) != "undefined") {
                $.History.bind($.hub.dispatch);

                $.History.unbind = function (state, handler) {
                    if (handler) {
                        if ($.History.handlers.specific[state]) {
                            $.each($.History.handlers.specific[state], function (i, h) {
                                if (h === handler) {
                                    $.History.handlers.specific[state].splice(i, 1);
                                    return false;
                                }
                            });
                        }
                    } else {
                        // We have a generic handler
                        handler = state;
                        $.each($.History.handlers.generic, function (i, h) {
                            if (h === handler) {
                                $.History.handlers.generic.splice(i, 1);
                                return false;
                            }
                        });
                    }
                };
            }
            const hash = window.location.hash;
            if (hash === '#/' || !hash) {
                this.dispatch();
            }
        },

        // Change location hash without triggering dispatch
        forceHash: function (hash) {
            hash = $.hub.helper.cleanHash(hash);
            if ($.hub.currentHash !== hash) {
                $.hub.currentHash = hash;
                $.wa.setHash(hash);
            }
        },

        // history.back() without triggering dispatch. Run callback when hash changes.
        // Used to go back after deletion so that we won't end up in non-existing page.
        backWithoutDispatch: function (callback) {
            var h;
            if (callback) {
                $.History.bind(h = function () {
                    $.History.unbind(h);
                    callback();
                });
            }

            this.skipDispatch = 1;
            history.back();
        },

        // Dispatch again based on current hash
        redispatch: function () {
            this.currentHash = null;
            this.dispatch();
        },

        // if this is > 0 then this.dispatch() decrements it and ignores a call
        skipDispatch: 0,

        // last hash processed by this.dispatch()
        currentHash: null,

        // dispatch call method by hash
        dispatch: function (hash) {
            const that = this;

            if (this !== $.hub) {
                return $.hub.dispatch(hash);
            }

            if ($.hub.skipDispatch > 0) {
                $.hub.skipDispatch--;
                return false;
            }

            hash = $.hub.helper.cleanHash(hash || undefined);
            if ($.hub.currentHash == hash) {
                return;
            }

            const old_hash = $.hub.currentHash;
            $.hub.currentHash = hash;

            // Fire an event allowing to prevent navigation away from current hash
            const e = new $.Event('wa_before_dispatched');
            that.$window.trigger(e);
            if (e.isDefaultPrevented()) {
                $.hub.currentHash = old_hash;
                $.wa.setHash(old_hash);
                return false;
            }

            this.removeSettingsClass();

            if (hash) {
                // clear hash
                hash = hash.replace(/^.*#\/?/, '').replace(/\/$/, '');
                hash = hash.split('/');

                if (hash[0]) {
                    let actionName = "";
                    let attrMarker = hash.length;
                    for (let i = 0; i < hash.length; i++) {
                        const h = hash[i];
                        if (i < 2) {
                            if (i === 0) {
                                actionName = h;
                            } else if ((h == 'add') || ((parseInt(h, 10) != h) && (h.indexOf('=') == -1) && (actionName !== 'search') && (actionName !== 'following') && (actionName !== 'plugins'))) {
                                actionName += h.substr(0, 1).toUpperCase() + h.substr(1);
                            } else {
                                attrMarker = i;
                                break;
                            }
                        } else {
                            attrMarker = i;
                            break;
                        }
                    }
                    const attr = hash.slice(attrMarker);
                    // call action if it exists
                    if ($.hub[actionName + 'Action']) {
                        $.sidebar.highlight();
                        $.hub.currentAction = actionName;
                        $.hub.currentActionAttr = attr;
                        $.hub[actionName + 'Action'].apply($.hub, attr);
                        that.$body.animate({scrollTop: 0}, 200);
                    } else {
                        if (console) {
                            console.log('Invalid action name:', actionName + 'Action');
                        }
                    }

                    this.toggleReviewWidget();
                } else {
                    // call default action
                    $.hub.defaultAction();
                }
            } else {
                // call default action
                $.hub.defaultAction();
                this.toggleReviewWidget();
            }
        },

        defaultAction: function (order) {
            if (!this.loaded) {
                $('.js-skeleton-default').show();
                this.loaded = true;
            }

            $.sidebar.sidebar.find('li.selected').removeClass('selected');
            $.sidebar.sidebar.find('a[href="#/"]').parent().addClass('selected');
            order = this.getOrder('default', order);
            this.load('?module=topics' + (order ? '&sort=' + order : ''));
            this.options.addTopicSettings.showAddTopicButton = true;
        },

        pluginsAction: function (params) {
            if (!this.loaded) {
                $('.js-skeleton-plugins').show();
                this.loaded = true;
            }

            if ($('#wa-plugins-container').length) {
                $.plugins.dispatch(params);
            } else {
                this.load('?module=plugins');
            }

            this.options.addTopicSettings.showAddTopicButton = false;
        },

        getOrder: function (key, order) {
            key = 'hub/' + key + '/order';
            if (order) {
                $.storage.set(key, order);
                return order;
            } else {
                return $.storage.get(key);
            }
        },

        initLazyLoad: function () {
            const that = this;
            const paging = $('.lazyloading-paging');
            if (!paging.length) {
                return;
            }

            let times = parseInt(paging.data('times') || '10', 10);
            const link_text = paging.data('linkText') || 'Load more';

            // check need to initialize lazy-loading
            const current = paging.find('li.selected');
            if (current.children('a').text() != '1') {
                return;
            }
            paging.hide();

            // prevent previous launched lazy-loading
            that.$window.lazyLoad('stop');

            // check need to initialize lazy-loading
            const next = current.next();
            if (!next.length) {
                return;
            }

            that.$window.lazyLoad({
                container: '#h-content .h-topics',
                load: function () {
                    that.$window.lazyLoad('sleep');

                    let paging = $('.lazyloading-paging').hide();
                    const footer = $('.h-footer');

                    // determine actual current and next item for getting actual url
                    const current = paging.find('li.selected');
                    const next = current.next();
                    const url = next.find('a').attr('href');
                    if (!url) {
                        that.$window.lazyLoad('stop');
                        return;
                    }

                    const list = $('#h-content .h-topics');
                    $('.loading-wrapper').show();

                    $.get(url, function (html) {
                        const tmp = $('<div></div>').html(html);
                        list.append(tmp.find('#h-content .h-topics').children());
                        const tmp_paging = tmp.find('.lazyloading-paging').hide();
                        paging.replaceWith(tmp_paging);
                        paging = tmp_paging;

                        footer.replaceWith(tmp.find('.h-footer'));

                        times -= 1;

                        // check need to stop lazy-loading
                        const current = paging.find('li.selected');
                        const next = current.next();
                        if (next.length) {
                            if (!isNaN(times) && times <= 0) {
                                that.$window.lazyLoad('sleep');
                                if (!$('.lazyloading-load-more').length) {
                                    $('<a href="#" class="semibold custom-ml-4 lazyloading-load-more">' + link_text + '</a>').insertAfter($('.loading-wrapper'))
                                        .on('click', function (event) {
                                            event.preventDefault();
                                            $(this).hide();
                                            times = 1;      // one more time
                                            that.$window.lazyLoad('wake');
                                            that.$window.lazyLoad('force');
                                        });
                                } else {
                                    $('.lazyloading-load-more').show();
                                }
                            } else {
                                that.$window.lazyLoad('wake');
                            }
                        } else {
                            that.$window.lazyLoad('stop');
                            $('.lazyloading-load-more').remove();
                        }
                        tmp.remove();

                        list.trigger('lazyload_append');
                        $('.loading-wrapper').hide();
                    });
                }
            });
        },

        popularAction: function () {
            this.defaultAction('popular');
        },

        archiveAction: function () {
            this.defaultAction('archive');
        },

        updatedAction: function () {
            this.defaultAction('updated');
        },

        recentAction: function () {
            this.defaultAction('recent');
        },

        unansweredAction: function () {
            this.defaultAction('unanswered');
        },

        contactAction: function (contact_id, hub_id, order) {
            order = order || '';
            if (order) {
                order = '&sort=' + order;
            }
            hub_id = hub_id || '';
            if (hub_id) {
                hub_id = '&hub_id=' + hub_id;
            }
            this.load('?module=topics' + order + hub_id + '&hash=contact/' + encodeURIComponent(contact_id), function () {
                $('.js-sort-menu').remove();
            });
        },

        load: function (url, callback) {
            const that = this;

            const load_protector = $.hub.load_protector = Math.random();
            that.waLoading.animate(2000, 96, false);
            that.$content.removeClass('flexbox');
            $.get(url, { }, function (result) {
                if (load_protector !== $.hub.load_protector) {
                    // too late!
                    return;
                }
                that.$window.scrollTop(0);
                that.$content.html(result);
                that.waLoading.done();

                const $pageTitle = that.$content.find('.js-page-title:first');

                if ($pageTitle.length) {
                    const title = $pageTitle.text();
                    document.title = title + ' — ' + that.options.accountName;
                }

                if (callback) {
                    try {
                        callback.call(this);
                    } catch (e) {
                    }
                }

                that.$window.trigger(that.loadedEvent);
                that.$body.find('.rx-popup').hide();
            });
        },

        typeAction: function (id, order) {
            this.load('?module=topics&hash=type/' + id + (order ? '&sort=' + order : ''));
        },

        hubAction: function (id, order) {
            if (!this.loaded) {
                $('.js-skeleton-default').show();
                this.loaded = true;
            }

            order = this.getOrder('hub' + id, order);
            this.load('?module=topics&hash=hub/' + id + (order ? '&sort=' + order : ''));
            this.options.addTopicSettings.showAddTopicButton = true;
        },

        followingAction: function (order) {
            if (!this.loaded) {
                $('.js-skeleton-default').show();
                this.loaded = true;
            }

            order = this.getOrder('following', order);
            this.load('?module=topics&hash=following' + (order ? '&sort=' + order : ''));
            this.options.addTopicSettings.showAddTopicButton = true;
        },

        categoryAddAction: function (hub_id) {
            const that = this;

            if (!this.loaded) {
                $('.js-skeleton-category-add').show();
                this.loaded = true;
            }

            hub_id = parseInt(hub_id);
            this.load('?module=settings&action=category&hub_id=' + hub_id, function () {
                that.streamSettingsHandler(null, null, 'category');
            });

            this.options.addTopicSettings.showAddTopicButton = false;
        },

        /**
         * Show category stream
         * @param id
         * @param order
         */
        categoryAction: function (id, order, callback) {
            const that = this;

            if (!this.loaded) {
                $('.js-skeleton-default').show();
                this.loaded = true;
            }

            this.load('?module=topics&hash=category/' + id + (order ? '&sort=' + order : ''), function () {
                if (callback && (typeof callback == 'function')) {
                    callback();
                }

                that.$content.on('click', 'a.stream-edit',function(event) {
                    event.preventDefault();

                    const $link = $(this);
                    const $icon = $link.find('.fa-pen');
                    $icon.removeClass('fa-pen').addClass('fa-spinner fa-spin');

                    that.$content.find('#h-stream-settings').load('?module=settings&action=category&id=' + id, function () {
                        that.streamSettingsHandler(id, $link, 'category');
                    });
                });
            });
            this.options.addTopicSettings.showAddTopicButton = true;
        },

        filterAddAction: function () {
            const that = this;

            if (!this.loaded) {
                $('.js-skeleton-filter-add').show();
                this.loaded = true;
            }

            this.load('?module=settings&action=filter', function () {
                that.streamSettingsHandler(null, null, 'filter');
            });

            this.options.addTopicSettings.showAddTopicButton = false;
        },

        filterAction: function (id, order, callback) {
            const that = this;

            if (!this.loaded) {
                $('.js-skeleton-default').show();
                this.loaded = true;
            }

            order = this.getOrder('filter' + id, order);
            this.load('?module=topics&hash=filter/' + id + (order ? '&sort=' + order : ''), function () {

                if (callback && (typeof callback == 'function')) {
                    callback();
                }

                that.$content.on('click', 'a.stream-edit',function(event) {
                    event.preventDefault();

                    const id = $(this).data('filter-id');

                    const $link = $(this);
                    $link.find('.fa-pen').removeClass('fa-pen').addClass('fa-spinner fa-spin');
                    that.$content.find('#h-stream-settings').load('?module=settings&action=filter&id=' + id, function () {
                        that.streamSettingsHandler(id, $link, 'filter');
                    });
                });
            });

            this.options.addTopicSettings.showAddTopicButton = true;
        },

        tagAction: function (id, order) {
            if (!this.loaded) {
                $('.js-skeleton-default').show();
                this.loaded = true;
            }

            this.load('?module=topics&hash=tag/' + id + (order ? '&sort=' + order : ''));
            this.options.addTopicSettings.showAddTopicButton = true;
        },

        searchAction: function (q) {
            if (!this.loaded) {
                $('.js-skeleton-default').show();
                this.loaded = true;
            }

            this.load('?module=topics&hash=search/' + encodeURIComponent(q));
            this.options.addTopicSettings.showAddTopicButton = true;
        },

        topicAddAction: function () {
            if (!this.loaded) {
                $('.js-skeleton-topic-edit').show();
                this.loaded = true;
            }

            this.load('?module=topics&action=edit');
            this.options.addTopicSettings.showAddTopicButton = false;
        },

        topicEditAction: function (id) {
            if (!this.loaded) {
                $('.js-skeleton-topic-edit').show();
                this.loaded = true;
            }

            this.load('?module=topics&action=edit&id=' + id);
            this.options.addTopicSettings.showAddTopicButton = true;
        },

        topicAction: function (id) {
            if (!this.loaded) {
                $('.js-skeleton-topic-edit').show();
                this.loaded = true;
            }

            this.load('?module=topics&action=info&id=' + id);
            this.options.addTopicSettings.showAddTopicButton = true;
        },

        settingsAction: function () {
            if (!this.loaded) {
                $('.js-skeleton-settings').show();
                this.loaded = true;
            }

            this.load('?module=settings');
            this.options.addTopicSettings.showAddTopicButton = false;
        },

        authorsAction: function () {
            if (!this.loaded) {
                $('.js-skeleton-authors').show();
                this.loaded = true;
            }

            this.load('?module=authors');
            this.options.addTopicSettings.showAddTopicButton = true;
        },

        settingsTypeAction: function (id) {
            if (!this.loaded) {
                $('.js-skeleton-settings-type').show();
                this.loaded = true;
            }

            this.load('?module=settings&action=type&id=' + id);
            this.options.addTopicSettings.showAddTopicButton = false;
        },

        settingsHubAction: function (id) {
            if (!this.loaded) {
                $('.js-skeleton-settings-hub').show();
                this.loaded = true;
            }

            this.load('?module=settings&action=hub&id=' + id);
            this.options.addTopicSettings.showAddTopicButton = false;
        },

        settingsFilterAction: function (id) {
            this.load('?module=settings&action=filter&id=' + id);

            this.options.addTopicSettings.showAddTopicButton = false;
        },

        commentsAction: function (params) {
            if (!this.loaded) {
                $('.js-skeleton-comments').show();
                this.loaded = true;
            }

            this.load('?module=comments'+(params ? '&'+params : ''));
            this.options.addTopicSettings.showAddTopicButton = true;
        },

        pagesAction: function (id) {
            if (!this.loaded) {
                $('.js-skeleton-pages').show();
                this.loaded = true;
            }

            if ($('#wa-page-container').length) {
                waLoadPage(id);
                this.$content.addClass('flexbox');
            } else {
                this.load('?module=pages', () => {
                    this.$content.addClass('flexbox');
                });
            }

            this.options.addTopicSettings.showAddTopicButton = false;
        },

        designAction: function (params) {
            if (!this.loaded) {
                $('.js-skeleton-pages').show();
                this.loaded = true;
            }

            if (params) {
                if ($('#wa-design-container').length) {
                    waDesignLoad();
                } else {
                    this.load('?module=design', function() {
                        waDesignLoad(params);
                    });
                }
            } else {
                this.load('?module=design', function() {
                    waDesignLoad('');
                });
            }

            this.$window.trigger(this.loadedEvent);
            this.options.addTopicSettings.showAddTopicButton = false;
        },

        designThemesAction: function (params) {
            if (!this.loaded) {
                $('.js-skeleton-pages').show();
                this.loaded = true;
            }

            if ($('#wa-design-container').length) {
                waDesignLoad();
            } else {
                this.load('?module=design', function () {
                    waDesignLoad();
                });
            }

            this.$window.trigger(this.loadedEvent);
            this.options.addTopicSettings.showAddTopicButton = false;
        },

        setPulsar: function() {
            const canCreateTopics = $.sidebar.$addTopicButton.length;

            if (!canCreateTopics || this.topics_count || localStorage.getItem('wa_hub_pulsar')) {
                this.unsetPulsar();

                return;
            }

            const isMobile = !!$.sidebar.sidebarData;

            if (!this.$pulsar && !isMobile) {
                this.$pulsar = $.sidebar.$addTopicButton.clone().appendTo('body');
            }

            if (this.$pulsar && isMobile) {
                this.$pulsar.remove();
            }

            const setOffset = () => {
                if (!this.$pulsar) {
                    return;
                }

                const { top, left } = $.sidebar.$addTopicButton.offset();
                this.$pulsar.css({
                    top,
                    left
                });
            };

            if (isMobile) {
                $.sidebar.$addTopicButton.addClass('pulsar');
                this.$addTopicButtonMobile.addClass('pulsar');
            } else {
                this.$pulsar.addClass('pulsar').css({
                    position: 'fixed',
                    'pointer-events': 'none'
                });

                setOffset();
            }

            $.sidebar.$addTopicButton.on('click.pulsar', $.proxy(this.unsetPulsar, this));
            this.$addTopicButtonMobile.on('click.pulsar', $.proxy(this.unsetPulsar, this));

            this.$window.on('resize.pulsar', $.proxy(this.setPulsar, this));
            this.$window.on('wa_sidebar_loaded.pulsar', setOffset);
        },

        unsetPulsar: function() {
            if (this.$pulsar) {
                this.$pulsar.remove();
            }

            $.sidebar.$addTopicButton.removeClass('pulsar');
            this.$addTopicButtonMobile.removeClass('pulsar');

            $.sidebar.$addTopicButton.off('.pulsar');
            this.$addTopicButtonMobile.off('.pulsar');
            this.$window.off('.pulsar');

            localStorage.setItem('wa_hub_pulsar', '1');
        },

        toggleAddTopicButton: function() {
            const that = this;

            this.options.addTopicSettings = new Proxy({}, {
                set(target, key, value) {
                    if (key === 'showAddTopicButton') {
                        if (value) {
                            that.$addTopicButtonMobile.removeClass('hidden');
                            return true;
                        } else {
                            that.$addTopicButtonMobile.addClass('hidden');
                            return false;
                        }
                    }
                }
            });
        },

        /**
         *
         * @param r
         * @param {jQuery} $settings
         * @param {jQuery} $form
         * @param {String} id
         * @param {String} type
         * @param {jQuery=} $link
         */
        streamSettingsSaveHandler: function (r, $settings, $form, id, type, $link) {
            this.options.addTopicSettings.showAddTopicButton = true;

            const $submitButton = $form.find(':submit');
            $submitButton.prop('disabled', false);
            $submitButton.siblings('.spinner').remove();
            this.removeSettingsClass();
            window.scrollTo(0, 0);

            if (r.status !== 'ok') {
                $.hub.helper.formError($form, r.errors || {}, 'category');
                return;
            }

            $settings.hide();
            if ($link) {
                $link.find('.fa-spinner').removeClass('loading fa-spinner fa-spin').addClass('fa-pen');
                $link.show();
            }

            if (!r.data) {
                return;
            }

            const data = r.data || {};

            //update sidebar text & icons
            switch (type) {
                case 'category':
                    $.hub.helper.updateCategory(id || data.id, data);
                    break;
                case 'filter':
                    $.hub.helper.updateFilter(id || data.id, data);
                    break;
            }

            // load new category stream
            if (!id) {
                window.location.hash = '/' + type + '/' + data.id + '/';
                return;
            }

            const callback = () => {
                $('.h-saved').show();
                setTimeout(function () {
                    $('.h-saved').hide();
                }, 4000);
            };

            switch (type) {
                case 'category':
                    if (data.type > 0) {
                        //reload stream for dynamic category
                        $.hub.categoryAction(id, null, callback);
                    } else {
                        document.title = r.data.name + ' — ' + $.hub.options.accountName;
                        callback();
                    }
                    break;
                case 'filter':
                    //always reload stream
                    $.hub.filterAction(id, null, callback);
                    break;
            }
        },

        streamDeleteHandler: function (id, type, data) {
            const self = this;

            if (data) {
                if (typeof(data) == 'string') {
                    data += '&id=' + id;
                }
            } else {
                data = {
                    id: id
                };
            }
            $.post('?module=settings&action=' + type + 'Delete', data, function (r) {
                if (r.status == 'ok') {
                    switch (type) {
                        case 'category':
                            $.sidebar.sidebar.find('#category-' + id).remove();
                            if (self.deleteDialog[id]) {
                                self.deleteDialog[id].close();
                                delete self.deleteDialog[id];
                            }
                            break;
                        case 'filter':
                            $.sidebar.sidebar.find('a[href="\\#/filter/' + id + '/"]').closest('li').remove();
                            break;
                    }

                    window.location.hash = '#/';
                    $.sidebar.resetHub();
                }
            }, 'json');
        },


        /**
         * @param {int=} id Category id
         * @param {jQuery=} $link Settings link DOM Element
         * @param {String} type
         * @returns {boolean}
         */
        streamSettingsHandler: function (id, $link, type) {
            const that = this;

            this.options.addTopicSettings.showAddTopicButton = false;

            if ($link) {
                $link.hide();
            }

            const $settings = $('#h-stream-settings');
            $settings.fadeIn(200);
            that.$content.addClass('settings-active');

            const self = this;

            const $form = $settings.find('form');
            this.helper.glyphHandler($form, type, (type == 'filter') ? 'icon' : 'glyph');
            $form.find(':input[name$="\\[name\\]"]:first').focus();

            const $categoryDescription = $form.find('textarea[name="category[description]"]');
            if ($categoryDescription.length) {
                $.hub.callRedactor($form.find('textarea[name="category[description]"]'), {
                    uploadPath: '?module=pages&action=uploadimage&r=x&absolute=1',
                    csrf: $form.find('input[name="_csrf"]').val()
                });
            }

            $form.on('submit', function(event) {
                event.preventDefault();

                $.hub.helper.formError($form);

                const formData = new FormData(this);
                const $spinner = $('<i class="fas fa-spinner fa-spin custom-ml-4"></i>');

                const $progress = $form.find('#h-category-logo-progressbar');
                const $progressLine = $progress.find('.progressbar-inner');
                const $progressText = $progress.find('.progressbar-text');
                $progress.show();

                $form.find(':submit').attr('disabled', true).append($spinner);

                $.ajax({
                    url: $form.attr('action'),  //Server script to process data
                    type: 'POST',
                    async: true,
                    xhr: function () {  // Custom XMLHttpRequest
                        const myXhr = $.ajaxSettings.xhr();

                        if (myXhr.upload && $form.find(':input[type="file"]').length) { // Check if upload property exists
                            myXhr.upload.addEventListener('progress', function (e) {
                                if (e.lengthComputable) {
                                    const done = e.loaded || e.position;
                                    const total = e.totalSize || e.total;
                                    const progressValue = Math.min(100, Math.floor(done / total * 100));
                                    $progressLine.css('width', progressValue + '%');
                                    $progressText.text(progressValue + '%');
                                }
                            }, false);
                        }
                        return myXhr;
                    },
                    success: function (r) {
                        try {
                            $form.find('.progressbar').hide();
                            const json = $.parseJSON(r);
                            self.streamSettingsSaveHandler(json, $settings, $form, id, type, $link || false);
                        } catch (e) {
                            $.hub.helper.formError($form, {
                                submit: r.replace(/<\/?[^>]+>/gi, '').substr(0, 512)
                            }, 'category');
                        }
                    },
                    //error: errorHandler,
                    data: formData,
                    cache: false,
                    dataType: 'html',
                    contentType: false,
                    processData: false
                });
            });


            $('.cancel', $settings).on('click', (event) => {
                event.preventDefault();

                $settings.hide();
                self.removeSettingsClass();
                window.scrollTo(0, 0);

                if ($link) {
                    $link.find('.fa-spinner').removeClass('fa-spinner fa-spin').addClass('fa-pen');
                    $link.show();
                } else {
                    window.location.hash = '/';
                }

                this.options.addTopicSettings.showAddTopicButton = true;
            });

            if (id) {
                $('.js-delete', $settings).on('click', function(event) {
                    event.preventDefault();

                    const $link = $(this);

                    if ((type == 'category') && $link.hasClass('js-dialog')) {
                        if (self.deleteDialog[id]) {
                            self.deleteDialog[id].show()
                        } else {
                            self.deleteDialog[id] = $.waDialog({
                                $wrapper: $('#h-category-delete'),
                                onClose(dialog) {
                                    dialog.hide();
                                    return false;
                                }
                            });
                        }
                    }
                });

                $('.js-hub-remove-category').on('click', function(event) {
                    event.preventDefault();

                    $(this).attr('disabled', true);

                    const $spinner = $('<i class="fas fa-spinner fa-spin custom-ml-4"></i>');
                    $(this).append($spinner);

                    self.streamDeleteHandler(id, type, self.deleteDialog[id].$body.find('form').serialize());
                });
            }
        },

        removeSettingsClass: function() {
            this.$content.removeClass('settings-active');
        },

        callRedactor: function($redactor, options) {
            return RedactorX($redactor, {
                editor: {
                    minHeight: '64px',
                    lang: $.hub.lang || 'en'
                },
                context: true,
                image: {
                    upload: options.uploadPath,
                    data: {
                        _csrf: options.csrf
                    }
                },
                toolbar: {
                    sticky: false
                },
                topbar: false,
                buttons: {
                    addbar: ['paragraph', 'image', 'embed', 'quote', 'pre'],
                    context: ['bold', 'italic', 'deleted', 'link', 'pre'],
                    topbar: ['undo', 'redo']
                },
                format: ['p', 'ul', 'ol'],
                quote: {
                    template: `<blockquote data-placeholder="${$.wa.locale["blockquote"] || 'Quote...'}"></blockquote>`
                },
                shortcutsRemove: ['ctrl+h, ctrl+l, ctrl+alt+1, ctrl+alt+2, ctrl+alt+3, ctrl+alt+4, ctrl+alt+5, ctrl+alt+6']
            });
        },

        toggleReviewWidget: function() {
            if ((
              $.hub.currentAction === 'hub' ||
              $.hub.currentAction === 'category'
            ) && this.$reviewWidget.length && $.hub.currentHash) {
                this.$reviewWidget.show();
            } else {
                this.$reviewWidget.hide();
            }
        },

        manageHandler: function (element, event) {
            $('#blog-stream-primary-menu').hide();
            $('#blog-stream-manage-menu').show();
            this.management = true;
            this.onContentUpdate();
            return false;
        },
        manageCompleteHandler: function (element, event) {
            this.management = false;
            $('#blog-stream-manage-menu').hide();
            $('#blog-stream-primary-menu').show();
            $('.b-post.js-managed').each(function () {
                $(this).removeClass('js-managed');
                $(this).find('h3:hidden').show();
                $(this).find('h3:first').hide();
                $(this).find('.b-post-body:hidden, .profile.image20px:hidden').fadeIn();
            });
            return false;
        },

        topics: {
            last_hash: '',
            $topics_ul: null,
            $bulk_menu: null,
            $sort_menu: null,

            init: function (params) {
                this.last_hash = window.location.hash;

                if (params && (typeof params.topics_count != 'undefined')) {
                    const $counter = $('#wa-app > .sidebar li.selected:first .count:first');
                    if ($counter.length) {
                        $counter.text(params.topics_count);
                    }
                }

                this.$topics_ul = $('.h-topics');

                this.initBulkActions();
                this.initFollowLinks();
                this.initRatingBox();
                this.initTopicTypesFilter();

                // Show-hide new comments when user clicks comment counter
                this.$topics_ul.on('click', '.toggle-comments', function () {
                    $(this).closest('.h-topic').find('.js-topic-comments').slideToggle(200);
                });

                this.$topics_ul.on('click', '.js-topic-comments-all', function () {
                    $(this).closest('.js-topic-comments').find('.h-comment').removeClass('hidden');
                    $(this).remove();
                });

                // Close 'saved' message when user clicks on a close button
                $('.h-saved .h-close-saved').on('click', function(event) {
                    event.preventDefault();
                    $(this).closest('.h-saved').fadeOut(200);
                });
            },

            initBulkActions: function() {
                /** @var {$.hub.topics} self */
                const self = this;
                self.$topics_header = $('.js-topics-header');
                self.$bulk_menu_toggle = self.$topics_header.find('.js-bulk-toggle');
                self.$bulk_menu = self.$topics_header.find('#bulk-menu');
                self.$sort_menu = self.$topics_header.find('.js-sort-menu:first');
                self.$bulk_menu_priority = self.$topics_header.find('#bulk-menu-priority');

                self.$bulk_menu_priority.waDropdown({
                    update_title: false,
                });

                self.$sort_menu.waDropdown();

                // Switch to Bulk mode when user clicks on "Select" link
                this.$bulk_menu_toggle.on('click', function(event) {
                    event.preventDefault();

                    self.$topics_ul.toggleClass('h-bulk-mode');
                    self.$topics_header.toggleClass('active');

                    if (self.$bulk_menu.is(':visible')) {
                        self.bulkCount();
                    }
                });

                // Actions with selected topics: select, delete, move
                this.$bulk_menu.find('.js-bulk-action').on('click', function(event) {
                    event.preventDefault();

                    const $link = $(this);

                    if (!self.bulkCount()) {
                        $.waDialog.alert({
                            title: $_('Select at least one topic'),
                            button_title: $_('Close'),
                            button_class: 'warning',
                        });
                        return;
                    }

                    let actionName = $link.data('action');
                    actionName = 'bulk' + actionName.substr(0, 1).toUpperCase() + actionName.substr(1);
                    self[actionName + 'Action'].apply(self, [$link]);
                });

                // Update number of selected topics when checkbox status changes
                this.$topics_ul.on('change', ':input.js-bulk-mode', function () {
                    self.bulkCount();
                });

                // Shift+click on a checkbox selects all between this one and previous one clicked
                let $last_li_checked = null;
                let $last_li_unchecked = null;
                this.$topics_ul.on('click', ':input.js-bulk-mode', function (e) {

                    var $checkbox = $(this);
                    var $li = $checkbox.closest('.h-topic');
                    if ($checkbox.prop('checked')) {
                        if (e.shiftKey && $last_li_checked) {
                            setCheckedBetween($last_li_checked, $li, true);
                        }
                        $last_li_checked = $li;
                        $last_li_unchecked = null;
                    } else {
                        if (e.shiftKey && $last_li_unchecked) {
                            setCheckedBetween($last_li_unchecked, $li, false);
                        }
                        $last_li_checked = null;
                        $last_li_unchecked = $li;
                    }

                    self.bulkCount();
                });

                // Button to hide a note why topics sorting is not available in dynamic lists
                if ($.storage.get('sort-handler-unavailable-notice-hidden')) {
                    $('.sort-handler-unavailable-notice').remove();
                } else {
                    $('.sort-handler-unavailable-notice').removeClass('hidden');
                    $('.sort-handler-unavailable-notice .h-close').on('click', function(event) {
                        event.preventDefault();
                        $(this).closest('.sort-handler-unavailable-notice').remove();
                        $.storage.set('sort-handler-unavailable-notice-hidden', true);
                    });
                }

                function setCheckedBetween($from, $to, status) {
                    if (!$from || !$to || !$from[0] || !$to[0] || $from.is($to[0])) {
                        return;
                    }

                    var is_between = false;
                    $to.closest('.h-topics').find('> li').each(function(i, el) {
                        if (!is_between) {
                            if ($from.is(el) || $to.is(el)) {
                                is_between = true;
                            }
                        } else {
                            if ($from.is(el) || $to.is(el)) {
                                return false;
                            }
                            $(el).find('input:checkbox.js-bulk-mode').prop('checked', status).change();
                        }
                    });
                }
            },

            initFollowLinks: function() {
                // Follow/unfollow when user clicks a star
                const $followLink = $('.js-follow-topic');
                const $followWrapper = $followLink.closest('.h-topic-meta');
                const $followCount = $('#following-count');
                const $followersCount = $followWrapper.find('.followers-count');
                const $followerStatus = $followWrapper.find('.following-status');
                const $own_follower_icon = $followWrapper.find('.own-follower-icon');

                $(document).on('click', '.js-follow-topic', function (event) {
                    event.preventDefault();

                    const $a = $(this);
                    const $i = $a.find('.fa-star');

                    const followed = $i.data('prefix') === 'far' ? 1 : 0;

                    let n = parseInt($('#following-count').html());
                    if (!followed) {
                        $i.attr('data-prefix', 'far');
                        $i.removeClass('text-yellow').addClass('gray');
                        $followerStatus.text($.wa.locale['not_following']).addClass('text-gray');
                        $own_follower_icon.hide();
                        n -= 1;
                    } else {
                        $i.attr('data-prefix', 'fas');
                        $i.removeClass('gray').addClass('text-yellow');
                        $followerStatus.text($.wa.locale['following']).removeClass('text-gray');
                        $own_follower_icon.show();
                        n += 1;
                    }

                    $followCount.addClass('highlighted').html(n);

                    $.post('?module=following', {
                        topic_id: $a.data('topic'),
                        follow: followed
                    }, function(response) {
                        if (response.status !== 'ok') {
                            console.warn(response);
                            return;
                        }

                        $followersCount.text(response.data.followers);
                    }, 'json');
                });
            },

            initRatingBox: function() {
                const $wrapper = $('.js-rating-box');
                const $total_score = $wrapper.find('.total-score');
                const $ratingCountUp = $('.js-rating-count-up');
                const $ratingCountDown = $('.js-rating-count-down');

                $wrapper.on('click', 'a', function(event) {
                    event.preventDefault();

                    const $this = $(this);

                    const vote = $(this).hasClass('h-vote-down') ? -1 : 1;
                    const topicId = $(this).data('topic-id');

                    $.post('?module=frontend&action=vote', { id: topicId, type: 'topic', vote }, undefined, 'json').always(function (r, text_status) {
                        if (text_status !== 'success' && !r && !r.data && !r.data.hasOwnProperty('votes_sum')) {
                            return;
                        }

                        const $i_up = $this.parent().find('.up,.up-bw');
                        const $i_down = $this.parent().find('.down,.down-bw');
                        if (vote > 0) {
                            $i_up.removeClass('up-bw').addClass('up');
                            $i_down.removeClass('down').addClass('down-bw');
                        } else {
                            $i_up.addClass('up-bw').removeClass('up');
                            $i_down.addClass('down').removeClass('down-bw');
                        }

                        $total_score.removeClass('text-green text-red text-gray');
                        $total_score.text(r.data.votes_sum);
                        $ratingCountUp.text(r.data.votes_up);
                        $ratingCountDown.text(r.data.votes_down * -1);

                        if (r.data.votes_sum - 0 > 0) {
                            $total_score.addClass('text-green');
                            $total_score.text('+'+r.data.votes_sum);
                        } else if (r.data.votes_sum - 0 < 0) {
                            $total_score.addClass('text-red');
                        } else {
                            $total_score.addClass('text-gray');
                        }
                    });
                });
            },

            initTopicTypesFilter: function() {
                const that = this;

                const $ul = $('#h-content .h-topics');
                const $sort_menu = $('.h-sort.js-sort-menu');
                const $checkboxes = $sort_menu.find('.h-filter-by-type :checkbox');
                const $window = $(window);

                // Trigger when user changes checkbox status in filter settings
                $sort_menu.find('.h-filter-by-type').on('change', ':checkbox', function() {
                    updateTopicVisibility();
                    updateMenuHeader();
                });

                // Trigger when lazyloading updates the list
                $ul.on('lazyload_append', function() {
                    updateTopicVisibility();
                });

                // Update visibility of topics in list
                function updateTopicVisibility() {
                    let types_disabled = {};
                    $checkboxes.map(function() {
                        if (this.checked) {
                            return 1;
                        } else {
                            types_disabled[this.value] = 1;
                        }
                    }).length || (types_disabled = {});

                    const lis_to_hide = [];
                    const lis_to_show = [];
                    $ul.children().each(function() {
                        const type_id = $(this).data('type-id');

                        if (types_disabled[type_id]) {
                            lis_to_hide.push(this);
                        } else {
                            lis_to_show.push(this);
                        }
                    });

                    if (lis_to_hide.length) {
                        $(lis_to_hide).css('opacity', 0.7).slideUp(function() {
                            $(lis_to_hide).css('opacity', '');
                            $window.scroll();
                        });
                        $('.h-footer .place-for-hidden-label').show().children('span').text(lis_to_hide.length);
                        $ul.closest('.h-stream').addClass('h-js-filtered').removeClass('h-not-js-filtered');
                    } else {
                        $('.h-footer .place-for-hidden-label').hide().children('span').text('0');
                        $ul.closest('.h-stream').addClass('h-not-js-filtered').removeClass('h-js-filtered');
                    }
                    lis_to_show.length && $(lis_to_show).slideDown(function() {
                        $window.scroll(); // triggers lazy loading if needed
                    });
                };

                function updateMenuHeader() {
                    let label = $checkboxes.map(function() {
                        return (this.checked || null) && $.trim($(this).closest('label').text());
                    }).get().join(', ');
                    label = $.trim($sort_menu.find('ul li.selected a').text()) + (label ? (': ' + label) : '');
                    $sort_menu.find('.js-filter-name').text(label);
                }
            },

            // not called from init(), called directly from Topics.html
            initManualDragAndDrop: function(category_id) {
                this.$topics_ul.sortable({
                    handle: '.sort,h3 a,i.h-glyph32',
                    onUpdate(event) {
                        const topic_id = $(event.item).data('id');
                        const before_id = $(event.item).next('li').data('id');
                        $.post('?module=topics&action=move', {
                            id: topic_id,
                            before_id,
                            category_id
                        }, null, 'json').always(function(r, status) {
                            if (status !== 'success' || r.status !== 'ok') {
                                console && console.log(status, r.errors || r);
                            }
                        });
                    }
                });
            },
            bulkCount: function () {
                const count = this.$topics_ul.find(':input.js-bulk-mode:checked').length;
                this.$topics_header.find('.js-count').text(count);
                return count;
            },
            bulkDeleteAction: function ($link) {
                const self = this;

                const confirm_text = $link.data('confirm');

                $.waDialog.confirm({
                    title: confirm_text,
                    success_button_title: $_('Delete'),
                    success_button_class: 'danger',
                    cancel_button_title: $_('Close'),
                    cancel_button_class: 'light-gray',
                    onSuccess() {
                        const ids = [];

                        self.$topics_ul.find(':input.js-bulk-mode:checked').each(function () {
                            ids.push(this.value);
                        });

                        $.ajax({
                            url: '?module=topics&action=delete',
                            dataType: 'json',
                            type: 'post',
                            data: {
                                ids: ids
                            },
                            success: function (r) {
                                self.$bulk_menu.find('.js-close-bulk-menu').click();

                                if (r.status !== 'ok') {
                                    console.warn(r);
                                    return;
                                }

                                if (r.data.deleted) {
                                    for (let i in r.data.deleted) {
                                        if (r.data.deleted.hasOwnProperty(i)) {
                                            self.$topics_ul.find('[data-id="' + r.data.deleted[i] + '"]').remove();
                                        }
                                    }

                                    $.hub.topics.bulkCount();
                                    $.sidebar.reload();
                                }
                            }
                        });
                    }
                });
            },
            bulkPriorityAction: function ($link) {
                var priority = $link.data('priority');
                var ids = this.$topics_ul.find(':input.js-bulk-mode:checked').map(function () {
                    return this.value;
                }).get();
                $.post('?module=topics&action=bulkPriority', { priority: priority, topic_ids: ids }, function() {
                    $.hub.redispatch();
                });
            },
            bulkMoveAction: function ($link) {
                const params = '&hub_id=' + $link.data('hub') + '&category_id=' + $link.data('category');
                const $dialog = $('#h-bulk-topics-move');
                const self = this;

                $dialog.load('?module=dialog&action=topicsMove' + params, function () {
                    $dialog.find(':input[name="hub_id"]').on('change', function () {
                        if (!this.checked) {
                            return;
                        }

                        $dialog.find('select').attr('disabled', true).closest('.js-move-dialog-select').addClass('hidden');
                        const $related = $(this).closest('.js-move-dialog-section').find('select:first');
                        if ($related.length) {
                            $related.attr('disabled', null);
                            $related.closest('.js-move-dialog-select').removeClass('hidden');
                        }
                    });

                    self.bulkMoveDialog($dialog);
                });
            },
            bulkMoveDialog: function ($dialog) {
                const self = this;

                $.waDialog({
                    html: $dialog,
                    onOpen($dialog, dialog) {
                        const ids = [];
                        self.$topics_ul.find(':input.js-bulk-mode:checked').each(function () {
                            ids.push(this.value);
                        });
                        $dialog.find(':input[name="topic_ids"]').val(ids.join(','));


                        const $saveButton = $dialog.find('.js-move-dialog-save');

                        $saveButton.on('click', function(event) {
                            event.preventDefault();

                            const $form = $dialog.find('form:first');
                            const $hub = $form.find(':input[name="hub_id"]:checked');
                            const hub_id = $hub.val();
                            const category_id = $hub.closest('.js-move-dialog-section').find('select:first').val();

                            const $saveButton = $(this);
                            const $spinner = $('<i class="fas fa-spinner fa-spin custom-ml-4"></i>');
                            $saveButton.attr('disabled', true);
                            $saveButton.append($spinner);

                            $.ajax({
                                url: $form.attr('action'),
                                data: $form.serialize(),
                                dataType: 'json',
                                type: 'post',
                                success: function (r) {
                                    $saveButton.attr('disabled', false);
                                    $spinner.remove();
                                    dialog.close();
                                    if (category_id) {
                                        window.location.hash = '/category/' + category_id + '/';
                                    } else if (hub_id) {
                                        window.location.hash = '/hub/' + hub_id + '/';
                                    } else {
                                        window.location.hash = '/';
                                    }
                                }
                            });
                        });
                    }
                });
            }
        },

        helper: {

            /**
             * Highlight invalid input form fields
             * @param {jQuery} $form HTMLFormElement or any other HTMLElement, contains inputs and jQuery wrapped
             * @param {Object=}errors key-value list of errors (key - input name, value - error text). If empty all errors will be removed
             * @param {String=} namespace
             */
            formError: function ($form, errors, namespace) {
                $form.find(':submit').attr('disabled', null);
                if (errors) {
                    var selector;
                    for (var field in errors || {}) {
                        if (errors.hasOwnProperty(field)) {
                            var $field = $form.find(this.getSelector(field, namespace));
                            if (!$field.length) {
                                $field = $form.find(this.getSelector(field));
                            }
                            if (!$field.length) {
                                $field = $form.find(':submit:first');
                            }
                            $field.addClass('error');
                            var $message = $field.next('.errormsg');
                            if ($message.length) {
                                $message.text(errors[field]);
                            } else {
                                $message = $field.after($('<em class="errormsg"></em>').text(errors[field]));
                            }
                        }

                    }
                    $form.find('input.error:first').focus();
                } else {
                    $form.find(':input.error').removeClass('error');
                    $form.find('.errormsg').text('');
                }
            },

            /**
             * update sidebar text & icons
             * @param {Number} id Category id
             * @param {{id:number, hub_id: number, name:string, topics_count:number, glyph_html: string}} data
             */
            updateCategory: function (id, data) {
                //update sidebar text & icons
                const $c = $.sidebar.sidebar.find('#category-' + id);
                if ($c.length) {
                    $c.find('.name').text(data.name);
                    $c.find('.count').text(data.topics_count);
                    $c.find('.js-glyph').replaceWith(data.glyph_html);
                    $('h1.list-title .title').text(data.name);
                } else {
                    $.sidebar.reload(function() {
                        $.sidebar.setHub(data.hub_id);
                    });
                }
            },

            /**
             *
             * @param {number} id
             * @param {{id:number, name:string, topics_count: number, icon_html: string}} data
             */
            updateFilter: function (id, data) {
                let $f = $.sidebar.sidebar.find('a[href="\\#/filter/' + id + '/"]').parents('li');

                if ($f.length) {
                    $f.find('.name').text(data.name);
                    $f.find('.icon').replaceWith(`<span class="icon"><i class="fas fa-${data.icon} text-light-gray"></i></span>`);
                } else {
                    // fallback for old ui icons
                    switch(data.icon) {
                        case 'funnel':
                            data.icon = 'filter';
                            break;
                        case 'lightning':
                            data.icon = 'bolt';
                            break;
                        case 'light-bulb':
                            data.icon = 'lightbulb';
                            break;
                        case 'lock-unlocked':
                            data.icon = 'lock-open';
                            break;
                        case 'contact':
                            data.icon = 'user-friends';
                            break;
                        case 'reports':
                            data.icon = 'chart-line';
                            break;
                        case 'books':
                            data.icon = 'book';
                            break;
                        case 'marker':
                            data.icon = 'map-marker-alt';
                            break;
                        case 'lens':
                            data.icon = 'camera';
                            break;
                        case 'alarm-clock':
                            data.icon = 'clock';
                            break;
                        case 'notebook':
                            data.icon = 'sticky-note';
                            break;
                        case 'blog':
                            data.icon = 'file-alt';
                            break;
                        case 'disk':
                            data.icon = 'save';
                            break;
                        case 'burn':
                            data.icon = 'radiation-alt';
                            break;
                        case 'clapperboard':
                            data.icon = 'video';
                            break;
                        case 'cup':
                            data.icon = 'mug-hot';
                            break;
                        case 'smiley':
                            data.icon = 'smile';
                            break;
                        case 'target':
                            data.icon = 'bullseye';
                            break;
                    }
                    const html = `<li id="filter-${id || data.id}" data-filter-id="${id || data.id}">
                        <a href="#/filter/${id || data.id}/">
                            <span class="icon"><i class="fas fa-${data.icon}"></i></span>
                            <span class="name">${data.name}</span>
                        </a>
                        </li>`;

                    $f = $(html);
                    $('.js-sidebar-filters').append($f);
                }
            },

            /**
             *
             * @param {jQuery} $context
             * @param {String} namespace
             * @param {String=} name
             */
            glyphHandler: function ($context, namespace, name) {
                if (!name) {
                    name = 'glyph';
                }

                var $templates = $context.find('.js-' + name + '-templates');
                var $input = $context.find(this.getSelector(name, namespace));
                $templates.on('click', 'li > a', function(event) {
                    event.preventDefault();

                    $templates.find('.selected').removeClass('selected');
                    const $this = $(this);
                    $this.parents('li').addClass('selected');
                    $input.val($this.data(name));
                });
            },

            /** Shows a confirmation dialog when user tries to navigate away from current page or current hash. */
            confirmLeave: function (is_relevant, warning_message, confirm_question) {
                var h, h2, $window = $(window);

                $window.on('beforeunload', h = function (e) {
                    if (is_relevant()) {
                        return warning_message;
                    }
                });

                $window.on('wa_before_dispatched', h2 = function (e) {
                    if (!is_relevant()) {
                        $window.off('unload', h).off('wa_before_dispatched', h2);
                        return;
                    }
                    if (!confirm(warning_message + " " + confirm_question)) {
                        e.preventDefault();
                    }
                });
            },

            /** Make sure hash has a # in the begining and exactly one / at the end.
             * For empty hashes (including #, #/, #// etc.) return an empty string.
             * Otherwise, return the cleaned hash.
             * When hash is not specified, current hash is used. */
            cleanHash: function (hash) {
                if (typeof hash == 'undefined') {
                    hash = window.location.hash.toString();
                }

                if (!hash.length) {
                    hash = '' + hash;
                }
                while (hash.length > 0 && hash[hash.length - 1] === '/') {
                    hash = hash.substr(0, hash.length - 1);
                }
                hash += '/';

                if (hash[0] != '#') {
                    if (hash[0] != '/') {
                        hash = '/' + hash;
                    }
                    hash = '#' + hash;
                } else if (hash[1] && hash[1] != '/') {
                    hash = '#/' + hash.substr(1);
                }

                if (hash == '#/') {
                    return '';
                }

                try {
                    // Fixes behaviour of Safari and possibly other browsers
                    hash = decodeURIComponent(hash);
                } catch (e) {
                }

                return hash;
            },

            isCtrlS: function (event) {
                if (event.which == 19) { // Mac users
                    return true;
                }
                if (event.which == 115 && event.ctrlKey) {
                    return true;
                }
                if (String.fromCharCode(event.which).toLowerCase() == 's' && event.ctrlKey) { // for chrome
                    return true;
                }
                return false;
            },

            getSelector: function (field, namespace) {
                var selector = ':input[name="';
                if (namespace) {
                    selector = selector + namespace + '\\[' + field + '\\]"]';
                } else {
                    selector = selector + field + '"]';
                }
                return selector;
            }
        }

    };

})(jQuery);
