(function ($) {

    $('#topic-access-toggle').iButton({
        labelOn: '',
        labelOff: '',
        className: 'mini'
    });

    $.storage = new $.store();
    // js controller
    $.hub = {
        options: {
            accountName: ''
        },
        // init js controller
        init: function (options) {

            this.options = options || {};
            $.sidebar.init();

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
            var hash = window.location.hash;
            if (hash === '#/' || !hash) {
                this.dispatch();
            }
        },

        // Change location hash without triggering dispatch
        forceHash: function (hash) {
            hash = $.hub.helper.cleanHash(hash);
            if ($.hub.currentHash !== hash) {
                $.hub.currentHash = hash;
                window.location.hash = hash;
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

            var old_hash = $.hub.currentHash;
            $.hub.currentHash = hash;

            // Fire an event allowing to prevent navigation away from current hash
            var e = new $.Event('wa_before_dispatched');
            $(window).trigger(e);
            if (e.isDefaultPrevented()) {
                $.hub.currentHash = old_hash;
                window.location.hash = old_hash;
                return false;
            }

            if (hash) {
                // clear hash
                hash = hash.replace(/^.*#\/?/, '').replace(/\/$/, '');
                hash = hash.split('/');

                if (hash[0]) {
                    var actionName = "";
                    var attrMarker = hash.length;
                    for (var i = 0; i < hash.length; i++) {
                        var h = hash[i];
                        if (i < 2) {
                            if (i === 0) {
                                actionName = h;
                            } else if ((h == 'add') || ((parseInt(h, 10) != h) && (h.indexOf('=') == -1) && (actionName != 'search') && (actionName != 'following'))) {
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
                    var attr = hash.slice(attrMarker);
                    // call action if it exists
                    if ($.hub[actionName + 'Action']) {
                        $('#wa-app > .sidebar li.selected').removeClass('selected');
                        var tmp_a = $('#wa-app > .sidebar li a[href="#/' + hash.join('/') + '/"]');
                        if (!tmp_a.length && hash.length > 2) {
                            tmp_a = $('#wa-app > .sidebar li a[href="#/' + hash.slice(0, -1).join('/') + '/"]');
                        }
                        if (tmp_a.length) {
                            tmp_a.parent().addClass('selected');
                        }
                        $.hub.currentAction = actionName;
                        $.hub.currentActionAttr = attr;
                        $.hub[actionName + 'Action'].apply($.hub, attr);
                        $('body').animate({scrollTop: 0}, 200);
                    } else {
                        if (console) {
                            console.log('Invalid action name:', actionName + 'Action');
                        }
                    }
                } else {
                    // call default action
                    $.hub.defaultAction();
                }
            } else {
                // call default action
                $.hub.defaultAction();
            }
        },

        defaultAction: function (order) {
            $('#wa-app > .sidebar li.selected').removeClass('selected');
            $('#wa-app > .sidebar li a[href="#/"]').parent().addClass('selected');
            order = this.getOrder('default', order);
            this.load('?module=topics' + (order ? '&sort=' + order : ''), function () {
                selectActiveTab('', order);
            });
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
            var paging = $('.lazyloading-paging');
            if (!paging.length) {
                return;
            }

            var times = parseInt(paging.data('times'), 10);
            var link_text = paging.data('linkText') || 'Load more';

            // check need to initialize lazy-loading
            var current = paging.find('li.selected');
            if (current.children('a').text() != '1') {
                return;
            }
            paging.hide();
            var win = $(window);

            // prevent previous launched lazy-loading
            win.lazyLoad('stop');

            // check need to initialize lazy-loading
            var next = current.next();
            if (next.length) {
                win.lazyLoad({
                    container: '#h-content .h-topics',
                    load: function () {
                        win.lazyLoad('sleep');

                        var paging = $('.lazyloading-paging').hide();
                        var footer = $('.h-footer');

                        // determine actual current and next item for getting actual url
                        var current = paging.find('li.selected');
                        var next = current.next();
                        var url = next.find('a').attr('href');
                        if (!url) {
                            win.lazyLoad('stop');
                            return;
                        }

                        var list = $('#h-content .h-topics');
                        $.get(url, function (html) {
                            var tmp = $('<div></div>').html(html);
                            list.append(tmp.find('#h-content .h-topics').children());
                            var tmp_paging = tmp.find('.lazyloading-paging').hide();
                            paging.replaceWith(tmp_paging);
                            paging = tmp_paging;

                            footer.replaceWith(tmp.find('.h-footer'));

                            times -= 1;

                            // check need to stop lazy-loading
                            var current = paging.find('li.selected');
                            var next = current.next();
                            if (next.length) {
                                if (!isNaN(times) && times <= 0) {
                                    win.lazyLoad('sleep');
                                    if (!$('.lazyloading-load-more').length) {
                                        $('<a href="#" class="lazyloading-load-more">' + link_text + '</a>').insertAfter(paging)
                                            .click(function () {
                                                times = 1;      // one more time
                                                win.lazyLoad('wake');
                                                win.lazyLoad('force');
                                                return false;
                                            });
                                    }
                                } else {
                                    win.lazyLoad('wake');
                                }
                            } else {
                                win.lazyLoad('stop');
                            }
                            tmp.remove();
                        });
                    }
                });
            }
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
                selectActiveTab('');
                $('.h-header .h-sort').remove();
            });
        },

        load: function (url, callback) {
            var options = this.options;
            $('#content').load(url, function (result) {
                var h1 = $('#content').find('h1:first');
                var title;
                if (h1.length) {
                    if (h1.children().length) {
                        if (h1.find('.title').length) {
                            title = h1.find('.title').text();
                        } else {
                            title = h1.contents()[0].textContent ? h1.contents()[0].textContent : h1.contents()[0].innerText;
                        }
                    } else {
                        title = h1.text();
                    }
                    document.title = title + ' — ' + options.accountName;
                }
                if (callback) {
                    try {
                        callback.call(this);
                    } catch (e) {
                    }
                }
            })
        },

        typeAction: function (id, order) {
            this.load('?module=topics&hash=type/' + id + (order ? '&sort=' + order : ''), function () {

            });
        },

        hubAction: function (id, order) {
            order = this.getOrder('hub' + id, order);
            this.load('?module=topics&hash=hub/' + id + (order ? '&sort=' + order : ''));
        },

        followingAction: function (order) {
            order = this.getOrder('following', order);
            this.load('?module=topics&hash=following' + (order ? '&sort=' + order : ''), function () {

            });
        },

        categoryAddAction: function (hub_id) {
            var self = this;
            hub_id = parseInt(hub_id);
            this.load('?module=settings&action=category&hub_id=' + hub_id, function () {
                selectActiveTab('category/add/' + hub_id);
                self.streamSettingsHandler(null, null, 'category');
            });
        },

        /**
         * Show category stream
         * @param id
         * @param order
         */
        categoryAction: function (id, order, callback) {
            var self = this;
            this.load('?module=topics&hash=category/' + id + (order ? '&sort=' + order : ''), function () {


                selectActiveTab('category/' + id, order);
                if (callback && (typeof callback == 'function')) {
                    callback();
                }
                $('a.stream-edit').click(function () {
                    var $link = $(this);
                    $link.find('.icon16').removeClass('settings').addClass('loading');
                    $('#h-stream-settings').load('?module=settings&action=category&id=' + id, function () {
                        self.streamSettingsHandler(id, $link, 'category');
                    });
                    return false;
                });

                var featured_topics = $("ul.h-topics.featured");
                featured_topics.sortable({
                    update: function (event, ui) {
                        var li = $(ui.item);
                        var next = li.next();
                        $.post('?module=topics&action=move', {
                            id: li.data('id'),
                            before_id: next.length ? next.data('id') : null,
                            category_id: id
                        });
                    }
                });

                $('ul.h-topics').on('click', 'a.topic-featured', function () {
                    var that = $(this);
                    var featured = $(this).find('i').hasClass('star');
                    var topic_li = $(this).closest('li');
                    var topic_id = topic_li.data('id');
                    $.post('?module=topics&action=setFeatured', {
                        id: topic_id,
                        category_id: id,
                        featured: featured ? 0 : 1
                    }, function (response) {
                        if (featured) {
                            that.find('i').removeClass('star').addClass('star-empty');
                            featured_topics.find('li[data-id=' + topic_id + ']').remove();
                        } else {
                            that.find('i').addClass('star').removeClass('star-empty');
                            featured_topics.append('<li data-id=' + topic_id + '>' +
                                '<i class="icon16 star" title="Featured topic"></i>' +
                                '<a href="#/topic/' + topic_id + '/" class="bold">' + topic_li.find('a:first').text() + '</a>' +
                                '<span class="status"></span></li>'
                            );
                        }
                    }, 'json');
                    return false;
                });

            });
        },

        filterAddAction: function () {
            var self = this;
            this.load('?module=settings&action=filter', function () {
                selectActiveTab('filter/add');
                self.streamSettingsHandler(null, null, 'filter');
            });
        },

        filterAction: function (id, order, callback) {
            var self = this;
            order = this.getOrder('filter' + id, order);
            this.load('?module=topics&hash=filter/' + id + (order ? '&sort=' + order : ''), function () {
                selectActiveTab('filter/' + id, order);

                if (callback && (typeof callback == 'function')) {
                    callback();
                }

                $('a.stream-edit').click(function () {
                    var $link = $(this);
                    $link.find('.icon16').removeClass('funnel').addClass('loading');
                    $('#h-stream-settings').load('?module=settings&action=filter&id=' + id, function () {
                        self.streamSettingsHandler(id, $link, 'filter');
                    });
                    return false;
                });
            });
        },


        tagAction: function (id, order) {
            this.load('?module=topics&hash=tag/' + id + (order ? '&sort=' + order : ''), function () {
                selectActiveTab('tag/' + id, order);
            });
        },

        searchAction: function (q) {
            this.load('?module=topics&hash=search/' + encodeURIComponent(q), function () {
                selectActiveTab('');
            });
        },

        topicAddAction: function () {
            this.load('?module=topics&action=edit');
        },

        topicEditAction: function (id) {
            this.load('?module=topics&action=edit&id=' + id);
        },

        topicAction: function (id) {
            this.load('?module=topics&action=info&id=' + id);
        },

        settingsAction: function () {
            this.load('?module=settings');
        },

        authorsAction: function () {
            this.load('?module=authors');
        },


        settingsTypeAction: function (id) {
            this.load('?module=settings&action=type&id=' + id);
        },

        settingsHubAction: function (id) {
            this.load('?module=settings&action=hub&id=' + id);
        },

        settingsFilterAction: function (id) {
            this.load('?module=settings&action=filter&id=' + id);
        },

        commentsAction: function () {
            this.load('?module=comments');
        },

        pagesAction: function (id) {
            if ($('#wa-page-container').length) {
                waLoadPage(id);
            } else {
                this.load('?module=pages');
            }
        },

        designAction: function (params) {
            if (params) {
                if ($('#wa-design-container').length) {
                    waDesignLoad();
                } else {
                    $("#content").load('?module=design', function () {
                        waDesignLoad(params);
                    });
                }
            } else {
                $("#content").load('?module=design', function () {
                    waDesignLoad('');
                });
            }
        },

        designThemesAction: function (params) {
            if ($('#wa-design-container').length) {
                waDesignLoad();
            } else {
                $("#content").load('?module=design', function () {
                    waDesignLoad();
                });
            }
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
            $form.find(':submit').attr('disabled', null);
            if (r.status == 'ok') {
                $settings.slideUp();
                if ($link) {
                    $link.find('.icon16').removeClass('loading').addClass(type == 'category' ? 'settings' : 'funnel');
                    $link.show();
                }
                if (r.data) {
                    var data = r.data || {};
                    //update sidebar text & icons
                    switch (type) {
                        case 'category':
                            $.hub.helper.updateCategory(id || data.id, data);
                            break;
                        case 'filter':
                            $.hub.helper.updateFilter(id || data.id, data);
                            break;
                    }


                    if (id) {
                        $('.h-saved').slideDown();
                        var callback = function () {
                            $('.h-saved').show();
                            setTimeout(function () {
                                $('.h-saved').slideUp();
                            }, 4000);
                        };
                        switch (type) {
                            case 'category':
                                if (data.type > 0) {
                                    //reload stream for dynamic category
                                    $.hub.categoryAction(id, null, callback);
                                }
                                break;
                            case 'filter':
                                //always reload stream
                                $.hub.filterAction(id, null, callback);
                                break;
                        }


                    } else {
                        // load new category stream
                        window.location.hash = '/' + type + '/' + data.id + '/';
                    }
                }
            } else {
                $.hub.helper.formError($form, r.errors || {}, 'category');
            }
        },

        streamDeleteHandler: function (id, type, data) {
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
                            var $c = $.sidebar.sidebar.find('#category-' + id);
                            $c.next().remove();
                            $c.remove();
                            break;
                        case 'filter':
                            var $f = $.sidebar.sidebar.find('a[href="\\#/filter/' + id + '/"]').parents('li');
                            $f.remove();
                            break;
                    }
                    location.hash = '#/';
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
            if ($link) {
                $link.hide();
            }
            var $settings = $('#h-stream-settings');
            $settings.slideDown();
            var self = this;

            var $form = $settings.find('form');
            this.helper.glyphHandler($form, type, (type == 'filter') ? 'icon' : 'glyph');
            $form.find(':input[name$="\\[name\\]"]:first').focus();
            $form.submit(function () {
                $.hub.helper.formError($form);
                $form.find(':submit').attr('disabled', true);
                // Update tags list
                $('.tagsinput input').trigger(jQuery.Event("keypress", {which: 13}));
                var formData = new FormData(this);
                var $progress = $form.find('#h-category-logo-progressbar');
                var progress = setTimeout(function () {
                    $form.find('.progressbar').show();
                }, 2000);
                $.ajax({
                    url: $form.attr('action'),  //Server script to process data
                    type: 'POST',
                    async: true,
                    xhr: function () {  // Custom XMLHttpRequest
                        var myXhr = $.ajaxSettings.xhr();

                        if (myXhr.upload && $form.find(':input[type="file"]').length) { // Check if upload property exists
                            myXhr.upload.addEventListener('progress', function (e) {
                                if (e.lengthComputable) {
                                    var done = e.loaded || e.position;
                                    var total = e.totalSize || e.total;
                                    $progress.show();
                                    $progress.css('width', Math.min(100, Math.floor(done / total * 100)) + '%');
                                }
                            }, false);
                        }
                        return myXhr;
                    },
                    //Ajax events

                    //beforeSend: beforeSendHandler,
                    success: function (r) {
                        try {
                            clearTimeout(progress);
                            $form.find('.progressbar').hide();
                            var json = $.parseJSON(r);
                            self.streamSettingsSaveHandler(json, $settings, $form, id, type, $link || false)
                        } catch (e) {
                            $.hub.helper.formError($form, {
                                submit: r.replace(/<\/?[^>]+>/gi, '').substr(0, 512)
                            }, 'category');
                        }
                    },
                    //error: errorHandler,
                    data: formData,
                    cache: false,
                    contentType: false,
                    processData: false
                });


                return false;
            });


            $('a.cancel', $settings).click(function () {
                $settings.slideUp();
                if ($link) {
                    $link.find('.icon16').removeClass('loading').addClass(type == 'category' ? 'settings' : 'funnel');
                    $link.show();
                } else {
                    window.location.hash = '/';
                }
                //or redirect into hub
                return false;
            });


            if (id) {
                $('a.js-delete', $settings).click(function () {
                    var $link = $(this);
                    if ((type == 'category') && $link.hasClass('js-dialog')) {
                        var $dialog = $('#h-category-delete');
                        $dialog.waDialog({
                            onSubmit: function () {
                                self.streamDeleteHandler(id, type, $dialog.find('form').serialize());
                                return false;
                            }
                        });
                    } else if (confirm($_('Are you sure?'))) {
                        self.streamDeleteHandler(id, type);
                    }
                    return false;
                });
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
            $topics_ul: null,
            $bulk_menu: null,
            $sort_menu: null,

            init: function (params) {
                if (params && (typeof params.topics_count != 'undefined')) {
                    var $counter = $('#wa-app > .sidebar li.selected:first .count:first');
                    if ($counter.length) {
                        $counter.text(params.topics_count);
                    }
                }

                this.$topics_ul = $('ul.h-topics');
                this.$bulk_menu = $('ul.js-bulk-menu:first');
                this.$sort_menu = $('ul.js-sort-menu:first');
                /**
                 *
                 * @var {$.hub.topics} self
                 */
                var self = this;

                this.$bulk_menu.find('a:first').click(function () {
                    if ($(this).is(':visible')) {
                        self.$topics_ul.addClass('h-bulk-mode');
                    }
                    self.$sort_menu.hide();
                    self.$bulk_menu.find('li').toggle();
                    self.bulkCount();

                    return false;
                });
                this.$bulk_menu.find('a:last').click(function () {
                    if ($(this).is(':visible')) {
                        self.$topics_ul.removeClass('h-bulk-mode');
                    }
                    self.$sort_menu.show();
                    self.$bulk_menu.find('li').toggle();
                    return false;
                });

                this.$bulk_menu.find('a.js-bulk-action').click(function () {
                    var $link = $(this);
                    if (self.bulkCount()) {
                        var confirm_text = $link.data('confirm');
                        if (!confirm_text || confirm(confirm_text)) {
                            var actionName = $link.data('action');
                            actionName = 'bulk' + actionName.substr(0, 1).toUpperCase() + actionName.substr(1);
                            self[actionName + 'Action'].apply(self, [$link]);
                        }
                    } else {
                        alert($_('Select at least one topic'));
                    }
                    return false;
                });

                // Follow/unfollow when user clicks a star
                this.$topics_ul.on('click', '.h-follow', function () {
                    var self = $(this);
                    var i = self.find('i');
                    var follow = i.hasClass('star-empty') ? 1 : 0;

                    $.post('?module=following', {
                        topic_id: self.data('topic'),
                        follow: follow
                    }, function (response) {
                        if (response.status == 'ok') {
                            var n = parseInt($('#following-count').html());
                            var topic_li = self.closest('li');
                            var comments_ul = topic_li.find('.h-comments');
                            if (follow) {
                                comments_ul.slideDown();
                                topic_li.addClass('h-followed').removeClass('h-not-followed');
                                self.addClass('highlighted');
                                i.removeClass('star-empty').addClass('star');
                                self.find('.followers-count').html($_('Following'));
                                n += 1;
                            } else {
                                comments_ul.slideUp();
                                topic_li.removeClass('h-followed').addClass('h-not-followed');
                                self.removeClass('highlighted');
                                i.addClass('star-empty').removeClass('star');
                                self.find('.followers-count').html('');
                                n -= 1;
                            }
                            $('#following-count').addClass('highlighted').html(n);
                        }
                    }, 'json');
                    return false;
                });

                // Show-hide new comments when user clicks comment counter
                this.$topics_ul.on('click', '.toggle-comments', function () {
                    $(this).closest('li').find('.h-comments').slideToggle();
                });

                this.$topics_ul.on('change', ':input.js-bulk-mode', function () {
                    self.bulkCount();
                });
            },
            bulkCount: function () {
                var count = this.$topics_ul.find(':input.js-bulk-mode:checked').length;
                this.$bulk_menu.find('.js-count').text(count);
                return count;
            },
            bulkDeleteAction: function ($link) {
                $link.find('i.icon16').removeClass('delete').addClass('loading');
                var ids = [];
                this.$topics_ul.find(':input.js-bulk-mode:checked').each(function () {
                    ids.push(this.value);
                });
                var self = this;
                $.ajax({
                    url: '?module=topics&action=delete',
                    dataType: 'json',
                    type: 'post',
                    data: {
                        ids: ids
                    },
                    /**
                     *
                     * @param {{data:{deleted:Array} }} r
                     */
                    success: function (r) {
                        $link.find('i.icon16').removeClass('loading').addClass('delete');
                        self.$bulk_menu.find('a:last').click();
                        if (r.status != 'ok') {
                            default_error_handler(r);
                            return;
                        }

                        if (r.data.deleted) {
                            for (var i in r.data.deleted) {
                                if (r.data.deleted.hasOwnProperty(i)) {
                                    self.$topics_ul.find('>li[data-id="' + r.data.deleted[i] + '"]').remove();
                                }
                            }
                        }
                    }
                });
            },
            bulkMoveAction: function ($link) {
                var params = '&hub_id=' + $link.data('hub') + '&category_id=' + $link.data('category');
                var $dialog = $('#h-bulk-topics-move');
                var self = this;

                if (!$dialog.hasClass('dialog')) {
                    $dialog.load('?module=dialog&action=topicsMove' + params, function () {
                        $dialog.addClass('dialog');
                        $dialog.find(':input[name="hub_id"]').change(function () {
                            if (this.checked) {
                                $dialog.find('select').attr('disabled', true);
                                var $related = $(this).parents('.value:first').find('select:first');
                                if ($related.length) {
                                    $related.attr('disabled', null);
                                }
                            }
                        }).change();
                        self.bulkMoveDialog($dialog);
                    });
                } else {
                    self.bulkMoveDialog($dialog);
                }
            },
            bulkMoveDialog: function ($dialog) {
                var self = this;
                $dialog.waDialog({
                    disableButtonsOnSubmit: true,
                    onLoad: function () {
                        var ids = [];
                        self.$topics_ul.find(':input.js-bulk-mode:checked').each(function () {
                            ids.push(this.value);
                        });
                        $dialog.find(':input[name="topic_ids"]').val(ids.join(','));
                    },
                    onSubmit: function () {
                        var $form = $dialog.find('form:first');
                        var $hub = $form.find(':input[name="hub_id"]:checked');
                        var hub_id = $hub.val();
                        var category_id = $hub.parents('.value:first').find('select:first').val();
                        $.ajax({
                            url: $form.attr('action'),
                            data: $form.serialize(),
                            dataType: 'json',
                            type: 'post',
                            success: function (r) {
                                $dialog.waDialog('close');
                                if (category_id) {
                                    window.location.hash = '/category/' + category_id + '/';
                                } else if (hub_id) {
                                    window.location.hash = '/hub/' + hub_id + '/';
                                } else {
                                    window.location.hash = '/';
                                }
                            }
                        });
                        return false;
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
                var $c = $.sidebar.sidebar.find('#category-' + id);
                if ($c.length) {
                    $c.find('.count').text(data.topics_count);
                    $c.find('.name').text(data.name);
                    $c.find('.js-glyph').replaceWith(data.glyph_html);
                    $('h1.list-title .title').text(data.name);
                } else {
                    var html = '<li class="drag-newposition"></li>' +
                        '<li class="dr" id="category-' + data.id + '">' +
                        '<span class="count">' + data.topics_count + '</span>' +
                        '<a href="#/category/' + data.id + '/">' +
                        data.glyph_html +
                        '<span class="name">name placeholder' +
                        '</span><span class="count"><i class="icon10 add h-new-category" data-category-id="' + data.id + '"></i></span>' +
                        '</a>' +
                        '</li>';
                    $c = $(html);
                    $('div.h-hub[data-hub-id="' + data.hub_id + '"] .category-list > ul:first').prepend($c);
                    $c.find('.name').text(data.name);
                }

            },

            /**
             *
             * @param {number} id
             * @param {{id:number, name:string, topics_count: number, icon_html: string}} data
             */
            updateFilter: function (id, data) {
                var $f = $.sidebar.sidebar.find('a[href="\\#/filter/' + id + '/"]').parents('li');
                if ($f.length) {
                    $f.find('.count').text(data.topics_count);
                    $f.find('.name').text(data.name);
                    $f.find('.js-icon').replaceWith(data.icon_html);
                    $('h1.list-title .title').text(data.name);
                } else {
                    var html = '<li>' +
                        '<a href="#/filter/' + (id || data.id) + '/">' + data.icon_html +
                        '<span class="name">name placeholder</span>' +
                            //'<span class="count">' + data.topics_count + '</span>' +
                            //'<strong class="small highlighted">0</strong>' +
                        '</a>' +
                        '</li>';
                    $f = $(html);
                    $f.insertBefore($.sidebar.sidebar.find('a[href="\\#/filter/add/"]').parents('li'));
                    $f.find('.name').text(data.name);
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
                $templates.on('click', 'li > a', function () {
                    $templates.find('.selected').removeClass('selected');
                    var $this = $(this);
                    $this.parents('li').addClass('selected');
                    $input.val($this.data(name));
                    return false;
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

    function selectActiveTab(hash, order) {
        var tabs = $('#content .tabs');
        tabs.find('.selected').removeClass('selected');
        tabs.find('a[href="#/' + (hash ? hash + '/' : '') + (order ? order + '/' : '') + '"]').parent().addClass('selected');
    }

})(jQuery);
