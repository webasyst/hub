(function($) {
    $.comments = {

        /**
         * Hotkey combinations
         * {Object}
         */
        hotkeys: {
            'alt+enter': {
                ctrl:false, alt:true, shift:false, key:13
            },
            'ctrl+enter': {
                ctrl:true, alt:false, shift:false, key:13
            },
            'ctrl+s': {
                ctrl:true, alt:false, shift:false, key:17
            }
        },

        /**
         * {Object}
         */
        options: {},

        /**
         * {Object}
         */
        statuses: {},

        /**
         * {Jquery} object
         */
        container: null,

        /**
         * {Jquery} object
         */
        sidebar_counter: null,

        /**
         * {Jquery} object
         */
        form: null,

        /**
         * Number
         */
        topic_id: null,

        init: function(options) {
            this.options = options || {};

            this.statuses = options.statuses || {};

            if (this.options.lazy_loading) {
                this.initLazyLoad(this.options.lazy_loading);
            }

            this.container = $(options.container);
            this.sidebar_counter = $('#h-all-comments').find('.count');

            this.form = $('#h-comment-add-form');
            if (this.options.topic_id) {
                this.topic_id = this.options.topic_id;
            }
            if (this.topic_id) {
                $('#h-comment-add').show();
            }

            this.form.find('textarea').redactor({
                minHeight: 150,
                paragraphy: false,
                convertDivs: false,
                imageUpload: '?module=pages&action=uploadimage&filelink=1',
                buttons: ['bold', 'italic', 'underline', 'deleted', 'unorderedlist', 'orderedlist',
                    'image', 'table', 'link', '|'],
                keydownCallback: function (e) {
                    if (e.keyCode == 13 && e.ctrlKey) {
                        //return addComment(); // Ctrl+Enter disabled
                    }
                }
            });


            var that = this;
            this.container
                .on('click', '.h-comment-delete', function() {
                    that.deleteComment($(this).closest('li').data('id'));
                    return false;
                })
                .on('click', '.h-comment-restore', function() {
                    that.restoreComment($(this).closest('li').data('id'));
                    return false;
                })
                .on('click', '.h-comment-solution', function() {
                    var self = $(this);
                    that.changeSolution(self.closest('li').data('id'), self.data('solution') ? 0 : 1);
                    return false;
                })
                .on('click', '.h-comment-reply', function() {
                    var self = $(this);
                    self.after(that.form.show());
                    that.clear();
                    $('input[name=parent_id]', that.form).val(self.closest('li').data('id'));
                    return false;
                });

            // Voting for comments
            this.container.on('click', 'a.h-vote-up,a.h-vote-down', function() {
                var $a = $(this);
                var $li = $a.closest('li');
                var vote = $a.hasClass('h-vote-down') ? -1 : 1;
                var comment_id = $li.data('id');

                var $i_up = $li.find('i.up,i.up-bw');
                var $i_down = $li.find('i.down,i.down-bw');
                if (vote > 0) {
                    $i_up.removeClass('up-bw').addClass('up');
                    $i_down.removeClass('down').addClass('down-bw');
                } else {
                    $i_up.addClass('up-bw').removeClass('up');
                    $i_down.addClass('down').removeClass('down-bw');
                }

                $.post('?module=frontend&action=vote', { id: comment_id, type: 'comment', vote: vote }, undefined, 'json').always(function (r, text_status, xhr) {
                    if (text_status == 'success' && r && r.data && r.data.hasOwnProperty('votes_sum')) {
                        var $total_score = $a.siblings('.total-score');
                        $total_score.removeClass('h-positive h-negative gray').text(r.data.votes_sum);
                        if (r.data.votes_sum - 0 > 0) {
                            $total_score.addClass('h-positive').text('+'+r.data.votes_sum);
                        } else if (r.data.votes_sum - 0 < 0) {
                            $total_score.addClass('h-negative');
                        } else {
                            $total_score.addClass('gray');
                        }
                    }
                });
            });

            var addComment = function() {
                that.addComment();
            };
            //this.addHotkeyHandler('textarea', 'ctrl+enter', addComment);
            this.form.find('input.save').unbind('click').bind('click', addComment);

        },

        initLazyLoad: function(options) {
            var count = options.count;
            var offset = count;
            var total_count = options.total_count;
            var url = options.url || '?module=comments';
            var target = $(options.target || '.h-comments:first ul:first');

            $(window).lazyLoad('stop');  // stop previous lazy-load implementation

            if (offset < total_count) {
                $(window).lazyLoad({
                    container: target,
                    state: (typeof options.auto === 'undefined' ? true: options.auto) ? 'wake' : 'stop',
                    load: function() {
                        $(window).lazyLoad('sleep');
                        $('.lazyloading-link').hide();
                        $('.lazyloading-progress').show();
                        $.get(
                            url+'&lazy=1&offset='+offset+'&total_count='+total_count,
                            function (html) {
                                var div = document.createElement('div');
                                div.innerHTML = html;
                                var data = $(div);
                                var children = data.find('.h-comments').children();
                                offset += children.length;
                                target.append(children);
                                $('.lazyloading-progress-string').replaceWith(data.find('.lazyloading-progress-string'));
                                $('.lazyloading-progress').replaceWith(data.find('.lazyloading-progress'));
                                if (offset >= total_count) {
                                    $(window).lazyLoad('stop');
                                    $('.lazyloading-link').hide();
                                } else {
                                    $('.lazyloading-link').show();
                                    $(window).lazyLoad('wake');
                                }
                                data.remove();
                            },
                            "html"
                        );
                    }
                });
                $('.lazyloading-link').unbind('click').bind('click', function(){
                    $(window).lazyLoad('force');
                    return false;
                });
            }
        },

        deleteComment: function(comment_id)
        {
            var container = this.container;
            var sidebar_counter = this.sidebar_counter;
            $.post('?module=comments&action=changeStatus',
                { comment_id: comment_id, status: this.statuses.deleted },
                function(r) {
                    if (r.status == 'ok') {
                        var comment_li  = container.find('li[data-id='+comment_id+']');
                        var comment_div = comment_li.find('div:first');
                        comment_div.addClass('h-deleted');
                        comment_div.find('.h-comment-delete').hide();
                        comment_div.find('.h-comment-restore').show();
                        if (sidebar_counter.length) {
                            sidebar_counter.text(parseInt(sidebar_counter.text(), 10) - 1);
                        }
                    }
                },
            'json');
        },

        restoreComment: function(comment_id)
        {
            var container = this.container;
            var sidebar_counter = this.sidebar_counter;
            $.post('?module=comments&action=changeStatus',
                { comment_id: comment_id, status: this.statuses.published },
                function(r) {
                    if (r.status == 'ok') {
                        var comment_li  = container.find('li[data-id='+comment_id+']');
                        var comment_div = comment_li.find('div:first');
                        comment_div.removeClass('h-deleted');
                        comment_div.find('.h-comment-delete').show();
                        comment_div.find('.h-comment-restore').hide();
                        if (sidebar_counter.length) {
                            sidebar_counter.text(parseInt(sidebar_counter.text(), 10) + 1);
                        }
                    }
                },
            'json');
        },

        changeSolution: function(comment_id, solution) {
            var container = this.container;
            $.post('?module=comments&action=changeSolution', {
                comment_id: comment_id, solution: solution
            }, function(r) {
                if (r.status == 'ok') {
                    var item = container.find('li[data-id=' + comment_id + ']').find('.h-comment-solution:first');
                    item.text(!solution ? $_('valid answer') : $_('remove anwsered badge'));
                    item.data('solution', solution ? 1 : '');
                }
            }, 'json');
        },

        addComment: function() {
            var sidebar_counter = this.sidebar_counter;
            var form = this.form;
            var that = this;
            form.find('textarea').redactor('code.sync');
            $.post(
                '?module=comments&action=add',
                form.serialize(),
                function (r) {
                    if (r.status == 'fail') {
                        that.clear(false);
                        that.showErrors(r.errors);
                        return;
                    }
                    if (r.status != 'ok' || !r.data.html) {
                        if (console) {
                            console.log('Error occured.');
                        }
                        return;
                    }
                    var parent_id_input = $('input[name=parent_id]', form);
                    var parent_li = form.closest('li.h-comment');
                    var html = r.data.html;
                    var ul = parent_li.children('ul');
                    var comment_block = that.container;

                    if (!parent_li.length) {
                        comment_block.show();
                        ul = $('ul:first', comment_block).show();
                    }
                    if (!ul.length) {
                        ul = $('<ul></ul>');
                        parent_li.append(ul);
                    }
                    ul.append($('<li class="h-comment" data-id="'+r.data.id+'" data-parent-id="'+r.data.parent_id+'"></li>').append(html));

                    // back form to 'h-comment-add' place and clear
                    $('textarea', form).val('').redactor('code.set', '');
                    var acceptor = $('#h-comment-add');
                    if (!acceptor.find('form').length) {
                        acceptor.append(form);
                        parent_id_input.val(0);
                    }
                    if (sidebar_counter.length) {
                        sidebar_counter.text(parseInt(sidebar_counter.text(), 10) + 1);
                    }

                    if (r.data.comments_count_str) {
                        $('.comments-header', that.container).text(r.data.comments_count_str);
                    }

                    that.clear();
                },
            'json')
            .error(function(r) {
                if (console) {
                    console.log(r.responseText ? 'Error occured: ' + r.responseText : 'Error occured.');
                }
            });
        },

        clear: function(clear_inputs) {
            clear_inputs = clear_inputs === undefined ? true : false;
            $('.errormsg', this.form).remove();
            $('.error', this.form).removeClass('error');
            if (clear_inputs) {
                $('textarea', this.form).val('').redactor('code.set', '');
            }
        },

        showErrors: function(errors) {
            for (var name in errors) {
                var el = $('[name='+name+']', this.form);
                if (name == 'text') {
                    el = el.parent();
                }
                el.after($('<em class="errormsg"></em>').
                    text(errors[name])).
                    addClass('error');
            }
        },

        addHotkeyHandler: function(item_selector, hotkey_name, handler) {
            var hotkey = this.hotkeys[hotkey_name];
            this.form.off('keydown', item_selector).on('keydown', item_selector,
                function(e) {
                    if (e.keyCode == hotkey.key &&
                        e.altKey  == hotkey.alt &&
                        e.ctrlKey == hotkey.ctrl &&
                        e.shiftKey == hotkey.shift)
                    {
                        return handler();
                    }
                }
            );
        }

    };
})(jQuery);