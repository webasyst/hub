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

            this.contact_id = options.contact_id || {};

            if (this.options.lazy_loading) {
                this.initLazyLoad(this.options.lazy_loading);
            }

            this.initParentCommentView();

            this.container = $(options.container);
            this.sidebar_counter = $('.js-comments-total');

            this.form = $('#h-comment-add-form');
            if (this.options.topic_id) {
                this.topic_id = this.options.topic_id;
            }
            if (this.topic_id) {
                $('#h-comment-add').show();
            }

            const csrf = this.form.find('input[name="_csrf"]').val();
            const $commentForm = this.form.find('textarea');
            this.redactorx = $.hub.callRedactor($commentForm, {
                uploadPath: '?module=pages&action=uploadimage&r=x&absolute=1',
                csrf
            });

            const that = this;
            this.container
                .on('click', '.h-comment-edit', function(event) {
                    event.preventDefault();
                    that.editComment($(this).closest('.js-comment').data('id'));
                })
                .on('click', '.h-comment-delete', function(event) {
                    event.preventDefault();
                    that.deleteComment($(this).closest('.js-comment').data('id'));
                })
                .on('click', '.h-comment-restore', function(event) {
                    event.preventDefault();
                    that.restoreComment($(this).closest('.js-comment').data('id'));
                })
                .on('click', '.h-comment-solution', function(event) {
                    event.preventDefault();
                    const self = $(this);
                    that.changeSolution(self.closest('.js-comment').data('id'), self.data('solution') ? 0 : 1);
                })
                .on('click', '.h-comment-reply', function(event) {
                    event.preventDefault();
                    const self = $(this);
                    that.form.insertAfter(self.closest('.js-comment'));
                    that.form.addClass('h-comment-reply-form');
                    that.clear();
                    $('input[name=parent_id]', that.form).val(self.closest('.h-comment').data('id'));
                });

            // Voting for comments
            this.container.on('click', 'a.h-vote-up, a.h-vote-down', function(event) {
                event.preventDefault();

                console.log('here')

                const $a = $(this);
                const vote = $a.hasClass('h-vote-down') ? -1 : 1;
                const comment_id = $a.closest('.js-comment').data('id');

                if (!comment_id) {
                    return;
                }

                const $i_up = $a.parent().find('.up,.up-bw');
                const $i_down = $a.parent().find('.down,.down-bw');
                if (vote > 0) {
                    $i_up.removeClass('up-bw').addClass('up');
                    $i_down.removeClass('down').addClass('down-bw');
                } else {
                    $i_up.addClass('up-bw').removeClass('up');
                    $i_down.addClass('down').removeClass('down-bw');
                }

                $.post('?module=frontend&action=vote', { id: comment_id, type: 'comment', vote: vote }, undefined, 'json').always(function (r, text_status, xhr) {
                    if (text_status == 'success' && r && r.data && r.data.hasOwnProperty('votes_sum')) {
                        const $total_score = $a.closest('.js-comment').find('.total-score');
                        $total_score.removeClass('text-green text-red text-gray').text(r.data.votes_sum);
                        if (r.data.votes_sum - 0 > 0) {
                            $total_score.addClass('text-green').text('+'+r.data.votes_sum);
                        } else if (r.data.votes_sum - 0 < 0) {
                            $total_score.addClass('text-red');
                        } else {
                            $total_score.addClass('text-gray');
                        }
                    }
                });
            });

            const commentMode = that.form.closest('#h-comment-add').data('mode');

            const addComment = function() {
                that.addComment(commentMode);
            };
            //this.addHotkeyHandler('textarea', 'ctrl+enter', addComment);
            this.form.find('.save').unbind('click').bind('click', addComment);

        },

        initLazyLoad: function(options) {
            const count = options.count;
            let offset = count;
            const total_count = options.total_count;
            const url = options.url || '?module=comments';
            const target = $(options.target || '.h-comments:first .h-comments-wrap:first');

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
                            url+'&lazy=1&offset='+offset+'&total_count='+total_count+'&contact_id='+options.contact_id,
                            function (html) {
                                const div = document.createElement('div');
                                div.innerHTML = html;
                                const data = $(div);
                                const children = data.find('.h-comments .h-comments-wrap:first').children();
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

        initParentCommentView: function() {
            const commentReplies = $('.js-parent-comment-reference');

            $.each(commentReplies, function (i, element) {
                const id = $(element).data('id');

                $(element).waTooltip({
                    template: '.js-parent-comment-popper-wrapper-' + id,
                    interactive: true,
                    interactiveBorder: 15,
                    onShow(tooltip) {
                        const topCoord = tooltip.reference.getBoundingClientRect().top;
                        const bottomCoord = window.innerHeight - topCoord - tooltip.reference.offsetHeight;

                        const placement = topCoord > bottomCoord ? 'top' : 'bottom';
                        const maxHeight = topCoord > bottomCoord ? topCoord - 80 : bottomCoord - 80;

                        tooltip.popper.querySelector('.h-parent-content').style.setProperty('max-height', maxHeight + 'px')

                        tooltip.setProps({
                            placement
                        });
                    }
                });

                $(element).on('click', function(event) {
                    event.preventDefault();

                    const $parentComment = $('.js-comment[data-id="'+ id +'"]');

                    if (!$parentComment) {
                        return;
                    }

                    $('html, body').animate({ scrollTop: $parentComment.offset().top - 64 });
                });
            });
        },

        editComment: function(comment_id) {
            const that = this;
            const $container = that.container;
            const $comment_wrapper = $container.find('.h-comment-container[data-id="'+ comment_id +'"]');
            const $edit_link = $comment_wrapper.find('.h-comment-edit');
            const $text_wrapper = $comment_wrapper.find('.js-comment-text:first');
            const csrf = this.form.find('input[name="_csrf"]').val();

            $edit_link.hide();

            $text_wrapper.data('source-text', $text_wrapper.html());

            // Ignore the click if the comment is already in edit mode
            if ($text_wrapper.find('textarea').length) {
                return;
            }

            // Create textarea and submit button
            const $textarea = $(document.createElement('textarea')).val($text_wrapper.data('source-text'));
            const $submit = $($.parseHTML(`<button type="submit" class="button small js-save">${$edit_link.data('save-string')}</button>`));

            $text_wrapper.addClass('custom-mt-12 h-edit-comment');

            // Render textarea
            $text_wrapper.empty().append($textarea).append($($.parseHTML('<div class="comment-submit bordered-bottom"></div>')).append($submit));

            // Setup redactor
            $.hub.callRedactor($textarea, {
                uploadPath: '?module=pages&action=uploadimage&r=x&absolute=1',
                csrf
            });

            // Setup submit button
            $submit.on('click', function(event) {
                const new_comment_text = $.trim($textarea.val());
                const href = "?module=commentsEdit";
                const data = {
                    id: comment_id,
                    text: new_comment_text,
                    _csrf: csrf
                };

                const $spinner = $('<i class="fas fa-spinner fa-spin custom-ml-4"></i>');
                $submit.prop('disabled', true);
                $submit.append($spinner);

                $.post(href, data, null, 'json').always(function (res, textStatus) {
                    $submit.prop('disabled', false)
                    $submit.siblings('.state-error').remove();
                    $spinner.remove();

                    if (textStatus == 'success' && res.status === 'ok') {
                        $text_wrapper.html(new_comment_text);
                        $text_wrapper.data('source-text', $text_wrapper.html());
                        $edit_link.show();
                    } else if (textStatus == 'success' && res.errors) {
                        // Validation error
                        $.each(res.errors, function(key, value) {
                            $submit.parent().prepend($($.parseHTML('<em class="state-error custom-ml-8"></em>')).text(value));
                        });
                    } else {
                        // Something bad happened, probably 403.
                        // Revert field back to original state.
                        $submit.parent().remove();
                        $edit_link.remove();
                    }

                }, 'json');
            });
        },

        deleteComment: function(comment_id) {
            const container = this.container;
            const sidebar_counter = this.sidebar_counter;
            $.post('?module=comments&action=changeStatus',
                { comment_id: comment_id, status: this.statuses.deleted },
                function(r) {
                    if (r.status !== 'ok') {
                        console.warn(r);
                    }

                    const comment_li  = container.find('.h-comment[data-id='+comment_id+']');
                    const comment_div = comment_li.find('div:first');

                    comment_div.find('.h-comment-body').addClass('h-deleted');
                    comment_div.find('.js-comment-buttons').hide();
                    comment_div.find('.h-comment-edit').hide();
                    comment_div.find('.h-comment-delete').hide();
                    comment_div.find('.h-comment-restore').show();
                    comment_div.find('.js-comment-deleted').show();

                    if (sidebar_counter.length) {
                        sidebar_counter.text(parseInt(sidebar_counter.text(), 10) - 1);
                    }

                    // Deleted children comments
                    const children_comments = comment_li.find('.h-comment .h-comment-container');
                    children_comments.each(function (i) {
                        $(this).find('.h-comment-body').addClass('h-deleted');
                        $(this).find('.js-comment-buttons').hide();
                        $(this).find('.h-comment-edit').hide();
                        $(this).find('.h-comment-delete').hide();
                        $(this).find('.h-comment-restore').show();
                        $(this).find('.js-comment-deleted').show();
                        if (sidebar_counter.length) {
                            sidebar_counter.text(parseInt(sidebar_counter.text(), 10) - 1);
                        }
                    });
                },
                'json');
        },

        restoreComment: function(comment_id) {
            const container = this.container;
            const sidebar_counter = this.sidebar_counter;
            $.post('?module=comments&action=changeStatus',
                { comment_id: comment_id, status: this.statuses.published },
                function(r) {
                    if (r.status !== 'ok') {
                        console.warn(r);
                        return;
                    }

                    const comment_li  = container.find('.h-comment[data-id='+comment_id+']');
                    const comment_div = comment_li.find('div:first');
                    comment_div.find('.h-comment-body').removeClass('h-deleted');
                    comment_div.find('.js-comment-buttons').show();
                    comment_div.find('.h-comment-edit').show();
                    comment_div.find('.h-comment-delete').show();
                    comment_div.find('.h-comment-restore').hide();
                    comment_div.find('.js-comment-deleted').hide();
                    comment_div.find('.js-comment-text').show();
                    if (sidebar_counter.length) {
                        sidebar_counter.text(parseInt(sidebar_counter.text(), 10) + 1);
                    }

                    // Restore children comments
                    const children_comments = comment_li.find('.h-comment .h-comment-container');
                    children_comments.each(function (i) {
                        $(this).find('.h-comment-body').removeClass('h-deleted');
                        $(this).find('.js-comment-buttons').show();
                        $(this).find('.h-comment-edit').show();
                        $(this).find('.h-comment-delete').show();
                        $(this).find('.h-comment-restore').hide();
                        $(this).find('.js-comment-deleted').hide();
                        $(this).find('.js-comment-text').show();
                        if (sidebar_counter.length) {
                            sidebar_counter.text(parseInt(sidebar_counter.text(), 10) - 1);
                        }
                    });
                },
            'json');
        },

        changeSolution: function(comment_id, solution) {
            const container = this.container;
            const $comment = container.find('li[data-id=' + comment_id + ']');
            const $commentBadge = $comment.find('.js-comment-solution:first');
            const $item = $comment.find('.h-comment-solution:first');
            const $itemMark = $item.find('.js-solution-mark');
            const $itemUnmark = $item.find('.js-solution-unmark');

            $item.prepend('<i class="fas fa-spinner fa-spin js-solution-spinner"></i>');
            if ($.browser.mobile) {
                $itemMark.addClass('hidden');
                $itemUnmark.addClass('hidden');
            }

            $.post('?module=comments&action=changeSolution', {
                comment_id: comment_id, solution: solution
            }, function(r) {
                $item.find('.js-solution-spinner').remove();

                if (r.status !== 'ok') {
                    console.warn(r);
                    return;
                }

                if (!solution) {
                    $itemMark.removeClass('hidden');
                    $itemUnmark.addClass('hidden');
                } else {
                    $itemMark.addClass('hidden');
                    $itemUnmark.removeClass('hidden');
                }

                $item.data('solution', solution ? 1 : '');
                $commentBadge.toggle();
            }, 'json');
        },

        addComment: function(commentMode) {
            const sidebar_counter = this.sidebar_counter;
            const form = this.form;
            const that = this;
            const $submit = form.find(':submit,:button');
            $submit.append('<i class="fas fa-spinner fa-spin custom-ml-4 js-loading"></i>');

            that.redactorx.container.$main.nodes[0].style.pointerEvents = 'none';

            $.post(
                '?module=comments&action=add',
                form.serialize(),
                function (r) {
                    $submit.prop('disabled', false);
                    $submit.find('.js-loading').remove();
                    that.redactorx.container.$main.nodes[0].style.pointerEvents = 'auto';

                    if (r.status === 'fail') {
                        that.clear(false);
                        that.showErrors(r.errors);
                        return;
                    }

                    if (r.status !== 'ok' || !r.data.html) {
                        if (console) {
                            console.log('Error occured.', r);
                        }
                        return;
                    }

                    that.redactorx.app.editor.setEmpty();

                    const parent_id_input = $('input[name=parent_id]', form);
                    const parent_li = form.closest('.h-comment');
                    const html = r.data.html;

                    if (commentMode === 'plain') {
                        const $comment = $('<div class="h-comment custom-ml-40 fade-in" data-id="'+r.data.id+'" data-parent-id="'+r.data.parent_id+'"></div>');
                        $comment.append(html);
                        parent_li.append($comment);
                    } else {
                        let ul = parent_li.children('ul:first-of-type');
                        let comment_block = that.container;

                        if (!parent_li.length) {
                            comment_block.show();
                            ul = $('ul:first', comment_block).show();
                        }
                        if (!ul.length) {
                            ul = $('<ul></ul>');
                            parent_li.append(ul);
                        }
                        const $comment = $('<li class="h-comment fade-in" data-id="'+r.data.id+'" data-parent-id="'+r.data.parent_id+'"></li>');
                        $comment.append(html);
                        ul.append($comment);
                    }

                    // back form to 'h-comment-add' place and clear
                    const acceptor = $('#h-comment-add');
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
            .fail(function(r) {
                $submit.prop('disabled', false);
                $submit.find('.js-loading').remove();

                if (console) {
                    console.warn(r.responseText ? 'Error occured: ' + r.responseText : 'Error occured.');
                }
            });
        },

        clear: function(clear_inputs) {
            clear_inputs = clear_inputs === undefined ? true : false;
            $('.state-error', this.form).remove();
            $('.error', this.form).removeClass('error');
            this.redactorx.app.editor.setEmpty();
        },

        showErrors: function(errors) {
            for (const name in errors) {
                let el = $('[name='+name+']', this.form);
                if (name == 'text') {
                    el = el.parent().parent().next();
                }
                if (name == 'text') {
                    el.prepend($('<div class="state-caution-hint"></div>').text(errors[name]));
                } else {
                    el.after($('<div class="state-caution-hint"></div>').text(errors[name]));
                }
            }
        },

        addHotkeyHandler: function(item_selector, hotkey_name, handler) {
            const hotkey = this.hotkeys[hotkey_name];
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
