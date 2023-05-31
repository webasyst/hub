$.hub = $.hub || {};

/** Adds handlers for topic and post votes on topic page */
$.hub.initTopicVotes = function (wrapper, vote_url, topic_id) { // {{{

    wrapper.on('click', 'div.vote a', function () {
        var i = $(this).find('i');
        if (i.hasClass('disabled')) {
            return false;
        }

        var div_vote = i.closest('div.vote');
        var id = topic_id, type = 'topic';
        var li = i.closest('li');
        if (li.data('id')) {
            id = li.data('id');
            type = 'comment';
        }

        var vote = (i.hasClass('up') || i.hasClass('up-bw')) ? 1 : -1;

        var csrf = $('input[name=_csrf]:first').val();
        $.post(vote_url, { id: id, type: type, vote: vote, _csrf: csrf }, undefined, 'json').always(function (r, text_status, xhr) {
            if (text_status == 'success' && r && r.data && r.data.hasOwnProperty('votes_sum')) {
                var strong = div_vote.find('strong').text(r.data.votes_sum);
                if (r.data.votes_sum - 0 > 0) {
                    strong.attr('class', 'positive');
                } else if (r.data.votes_sum - 0 < 0) {
                    strong.attr('class', 'negative');
                } else {
                    strong.removeAttr('class');
                }
            } else {
                // Unable to vote for some reason: e.g. session died.
                // !!!
            }
        });

        // Deal with pesky arrows -_-'
        var div_vote_big = div_vote.hasClass('a');
        class_up = 'up';
        class_down = 'down';
        class_up_bw = 'up-bw';
        class_down_bw = 'down-bw';
        i.removeClass(class_up_bw).removeClass(class_down_bw).addClass('disabled');
        i.addClass(vote > 0 ? class_up : class_down);
        var opposite_i = div_vote.find(vote > 0 ? 'i.down' : 'i.up');
        opposite_i.removeClass('disabled').removeClass(class_up).removeClass(class_down);
        opposite_i.addClass(vote < 0 ? class_up_bw : class_down_bw);

        return false;
    });

}; // }}}

/** Adds handlers for topic and post voting when user is not authorized to vote. */
$.hub.initTopicVotesGuest = function (wrapper, login_url) { // {{{
    wrapper.on('click', 'div.vote a.plus,div.vote a.minus', function () {
        window.location = login_url;
        return false;
    });
}; // }}}

$.hub.initEditor = function (el, options) { // {{{
    options = $.extend({
        minHeight: 150,
        buttonSource: false,
        paragraphy: false,
        convertDivs: false,
        pasteLinkTarget: '_blank',
        buttons: ['bold', 'italic', 'underline', 'deleted', 'lists', 'image', 'video', 'link', 'codeblock', 'blockquote'],
        plugins: ['video', 'codeblock', 'blockquote'],
        imageUpload: el.data('upload-url'),
        imageUploadFields: {
            _csrf: (el.data('csrf') || el.closest('form').find('input[name="_csrf"]').val()),
            _version: 2
        },
        imageUploadErrorCallback: function(json) {
            alert(json.error);
        }
    }, options || {});

    if (!options.lang) {
        for (var lang in $.Redactor.opts.langs) {
            if (lang != 'en') {
                options.lang = lang;
                break;
            }
        }
    }

    el.redactor(options);
}; // }}}

/** Controller for topic creation form. */
$.hub.initAddForm = function (form, autocomplete_url) { // {{{

    form.find('input:submit[name="preview"]').click(function () {
        var $submit = $(this).after('<i class="icon16 loading"></i>').prop('disabled', true);
        var d = form.find('.topic-preview');
        $.post(d.data('url'), {content: form.find('.topic-content').val(), _csrf: form.find('input[name="_csrf"]').val()}, function (response) {
            $submit.prop('disabled', false).siblings('.loading').remove();
            d.html(response.data).show();
        }, 'json');
        return false;
    });

    this.initEditor(form.find('textarea.topic-content'));

    var select_category = $('.topic-category select:first');
    var select_category_v;
    $('.topic-type').on('click', 'a', function () {
        $('.topic-type li.selected').removeClass('selected');
        $(this).parent().addClass('selected');
        var type_id = $(this).data('type');
        $('input[name="data[type_id]"]').val(type_id);

        // if at least one dynamic category (by topic type) exists,
        // show only categories applicable for selected type_id.
        if (select_category.find('option[data-type]').length) {
            var prev_selected_category = (select_category_v || select_category).val();
            select_category_v && select_category_v.remove();
            select_category_v = select_category.clone();
            select_category_v.find('option[data-type]').each(function () {
                var $option = $(this);
                if ($option.data('type') != type_id) {
                    if ($option.attr('value') == prev_selected_category) {
                        prev_selected_category = null;
                    }
                    $option.remove();
                }
            });
            select_category.prop('disabled', true).hide();
            prev_selected_category && select_category_v.val(prev_selected_category);
            select_category_v.prop('disabled', false).insertAfter(select_category).show();
        }
        return false;
    });

    $('.topic-type .selected a').click();

    var ti_widget;
    var tags_input = form.find('[name="data[tags]"]');
    tags_input.tagsInput({
        autocomplete_url: '',
        autocomplete: {
            source: function (request, response) {
                $.getJSON(autocomplete_url + "?term=" + request.term, function (data) {
                    response(data.data);
                });
            }
        },
        defaultText: "",
        height: '30px',
        width: (tags_input.parent().width() - 6) + 'px',
        onChange: function () {
            if (!ti_widget) {
                return;
            }
            tags_input.val(
                ti_widget.removeClass('error').find('.tag > span').map(
                    function () {
                        return $.trim($(this).text());
                    }
                ).toArray().join(',')
            );
            tags_input.siblings('.errormsg').remove();
        }
    });

    ti_widget = tags_input.siblings('.tagsinput');
    var fake_input = ti_widget.find('input').css('min-width', '150px');
    fake_input.blur(function () {
        tags_input.addTag(fake_input.val(), {unique: true});
    });

    // Click on a popular tag adds it into the tag list
    form.find('.popular-tags').on('click', 'a', function () {
        var tag = $.trim($(this).text());
        if (!tags_input.tagExist(tag)) {
            tags_input.addTag(tag, {unique: true});
            fake_input.removeClass('not_valid');
        }
        return false;
    });

    $('#ask-examples-link').click(function () {
        $('#ask-examples').slideToggle(200);
        return false;
    });

}; // }}}

/** Controller for follow/unfollow buttons */
$.hub.initFollowingButton = function (wrapper, follow_url, topic_id) { // {{{

    var csrf = $('input[name=_csrf]:first').val();
    var $button_follow = $('#button-follow');
    var $button_unfollow = $('#button-unfollow');
    var $wrapper = $button_follow.closest('.follow');

    $button_follow.click(function() {
        $button_follow.parent().append('<i class="icon16 loading"></i>');
        $.post(follow_url, { topic_id: topic_id, follow: 1, _csrf: csrf }, function() {
            $wrapper.addClass('following').removeClass('not-following').find('.loading').remove();
        });
    });

    $button_unfollow.click(function() {
        $button_unfollow.parent().append('<i class="icon16 loading"></i>');
        $.post(follow_url, { topic_id: topic_id, follow: 0, _csrf: csrf }, function() {
            $wrapper.addClass('not-following').removeClass('following').find('.loading').remove();
        });
    });

}; // }}}

/** Localization helper */
$.hub.locale = $.hub.locale || {};
$.hub.$_ = window.$_ = function(p1) { // {{{
    return $.hub.locale[p1] || p1;
}; // }}}

$(function () {
    /** Handler for topic deletion link */
    $('#topic-and-comments article .edit-links .delete').click(function() { // {{{
        var $a = $(this);
        if (confirm($a.data('confirm'))) {
            var csrf = $('input[name=_csrf]:first').val();
            $.post($a.attr('href'), { _csrf: csrf }, null, 'json').always(function(r, status) {
                if (status == 'success' && r.status == 'ok') {
                    window.location = r.data;
                }
            });
        }
        return false;
    }); // }}}

    /** Comments ordering: 'popular' or 'newest' */
    if ($('#comments').length) { (function() { // {{{

        var $comments = $('#comments');

        // Links to order comments by rating or datetime
        $comments.find('.sorting li').on('click', function () {
            var self = $(this);
            var parent = self.parent();
            var order = $(this).data('order');
            if (parent.find('.selected').data('order') != order) {
                parent.find('.selected').removeClass('selected');
                self.addClass('selected');
                orderComments($comments.data('topic'), order);
            }
            return false;
        });

        function orderComments(topic_id, order) {
            $.post(
                location.href.replace(/\/#\/[^#]*|\/#|\/$/g, '') + '/comments/order/',
                {topic_id: topic_id, order: order},
                function (r) {
                    if (r.status == 'fail') {
                        console && console.log(r);
                        return;
                    }
                    if (r.status != 'ok') {
                        console && console.log('Error occured');
                        return;
                    }
                    if (r.data.comment_ids && $.isArray(r.data.comment_ids) && r.data.comment_ids.length) {
                        var ul = $('ul:not(.sorting):first', $comments);
                        for (var i = 0, n = r.data.comment_ids.length; i < n; i += 1) {
                            ul.append($('li[data-id=' + r.data.comment_ids[i] + ']', ul));
                        }
                    }
                },
                'json'
            );
        }

    })(); } // }}}

    /** Controller for new comment form */
    if ($('#comment-form').length) { (function() { // {{{


        var form_wrapper = $('#comment-form');
        var form_initial_placement = $('<div></div>').insertBefore(form_wrapper);
        var form = form_wrapper.find('form');

        var type_input = $('input[name=type]', form);
        var add_topic_header = form_wrapper.siblings('h3,h4');

        var content = $('#comments');
        var comment_type = type_input.val();
        var hotkeys = {
            'alt+enter': {
                ctrl: false, alt: true, shift: false, key: 13
            },
            'ctrl+enter': {
                ctrl: true, alt: false, shift: false, key: 13
            },
            'ctrl+s': {
                ctrl: true, alt: false, shift: false, key: 17
            }
        };

        $.hub.initEditor(form.find("textarea"), {minHeight: 150});

        // 'Reply' link near a comment moves the comment form there
        content.on('click', '.comment-reply', function () {
            var self = $(this);
            var item = self.closest('li');
            var parent_id = parseInt(item.attr('data-id'), 10) || 0;
            prepareAddingForm(parent_id, self);

            $('.comment').removeClass('in-reply-to');
            item.children('.comment').addClass('in-reply-to');

            return false;
        });

        // Mark comment as a solution
        content.on('click', '.button-solution', function () {
            var self = $(this);
            var item = self.closest('li');
            var solution = self.val() == self.data('solution') ? 1 : 0;
            var csrf = $('input[name=_csrf]:first').val();
            $.post('comments/solution/', {id: item.data('id'), solution: solution, _csrf: csrf}, function (response) {
                if (response.status == 'ok') {
                    if (solution) {
                        self.val(self.data('cancel'));
                        $('<span class="badge-solution badge badge-answered"><i></i>' + self.data('badge') + '</span>').insertBefore(self);
                    } else {
                        self.val(self.data('solution'));
                        self.prev('.badge').remove();
                    }
                    self.toggleClass('gray');
                    self.closest('.comment').toggleClass('solution');
                }
            }, 'json');
        });

        // Move form back to root comment level when user clicks on a special link
        add_topic_header.on('click', '.back-to-root', function() {
            prepareAddingForm('');
            return false;
        });

        // Submit comments
        addHotkeyHandler('textarea', 'ctrl+enter', addComment);
        form.submit(function () {
            addComment();
            return false;
        });

        var is_changed = false;

        form
            .on("submit", function() { is_changed = false; })
            .on("input change", function () { is_changed = true; });

        $(window).on('beforeunload', function(event) {
            if (is_changed) { return ""; }
        });

        function addComment() {
            var $submit = form.find(':submit').after('<i class="icon16 loading"></i>').prop('disabled', true);
            $.post(
                location.origin + location.pathname.replace(/\/$/, '') + '/comments/add/',
                form.serialize(),
                function (r) {
                    $submit.prop('disabled', false).siblings('.loading').remove();
                    if (r.status == 'fail') {
                        if (console) {
                            console.log(r);
                        }
                        for (var name in r.errors) {
                            var el = form.find('[name='+name+']');
                            if (name == 'text') {
                                el = el.parent();
                            } else {
                                el.addClass('error');
                            }
                            if (el.next('em.errormsg').length) {
                                el.next('em.errormsg').text(r.errors[name]);
                            } else {
                                $('<em class="errormsg"></em>').text(r.errors[name]).insertAfter(el);
                            }
                        }
                        return;
                    }
                    if (r.status != 'ok' || !r.data.html) {
                        if (console) {
                            console.log('Error occured.');
                        }
                        return;
                    }
                    var html = r.data.html;
                    var parent_id = parseInt(r.data.parent_id, 10) || 0;
                    var parent_item = parent_id ? form.closest('li') : content;
                    var ul = parent_item.children('ul:last');
                    if (!ul.length) {
                        ul = $('<ul></ul>');
                        parent_item.append(ul);
                    }
                    ul.show().append($.parseHTML(html));
                    parent_item.children('.comment').removeClass('in-reply-to');

                    var header = $('.comments-header').show();
                    if (r.data.comment_count_str) {
                        header.text(r.data.comment_count_str);
                    }
                    $('input[name=count]', form).val(r.data.count);
                    form.find('em.errormsg').remove();
                    var $parent_comment = $('li[data-id='+parent_id+']');
                    $parent_comment.find('div.comment').removeClass('in-reply-to');
                    form.find('#comment-text').redactor('toolbar.setUnfixed');
                    prepareAddingForm('');
                },
                'json')
                .error(function (r) {
                    $submit.prop('disabled', false).siblings('.loading').remove();
                    if (console) {
                        console.error(r.responseText ? 'Error occured: ' + r.responseText : 'Error occured.');
                    }
                });
        }

        function prepareAddingForm(comment_id, place_for_form) {
            add_topic_header.children('a.back-to-root').remove();
            if (comment_id) {
                place_for_form.after(form_wrapper);
                $('input[name=parent_id]', form).val(comment_id);
                type_input.val('comment');
                add_topic_header.append('<a class="back-to-root" href="#">#</a>');
            } else {
                form_initial_placement.after(form_wrapper);
                $('input[name=parent_id]', form).val(0);
                type_input.val(comment_type);
            }

            $('textarea', form).val('').redactor('code.set', '');
            form_wrapper.show();
        }

        function addHotkeyHandler(item_selector, hotkey_name, handler) {
            var hotkey = hotkeys[hotkey_name];
            form.off('keydown', item_selector).on('keydown', item_selector,
                function (e) {
                    if (e.keyCode == hotkey.key &&
                        e.altKey == hotkey.alt &&
                        e.ctrlKey == hotkey.ctrl &&
                        e.shiftKey == hotkey.shift) {
                        return handler();
                    }
                }
            );
        }


    })(); } // }}}

    // Controller for comments editor
    if ($('#comments').length) { (function() { // {{{

        // Edit link
        var $content = $('#comments');
        $content.on('click', '.comment-edit', function() {
            var $edit_link = $(this);
            var $comment_wrapper = $edit_link.closest('.comment');
            var $text_wrapper = $comment_wrapper.find('.h-text');
            var upload_url = $edit_link.data('upload-url');
            var csrf = $edit_link.parent('.actions').find('input[name="_csrf"]').val();


            // Ignore the click if the comment is already in edit mode
            if ($text_wrapper.find('textarea').length) {
                return;
            }

            var comment_text = $text_wrapper.html();
            var comment_id = $text_wrapper.closest('[data-id]').data('id');

            // Replace comment text with form to edit it
            var $textarea = $(document.createElement('textarea')).data('upload-url', upload_url).data('csrf', csrf).val(comment_text);
            var $submit = $($.parseHTML('<input type="submit" class="save">')).val($edit_link.data('save-string'));

            $comment_wrapper.find('.actions:first').hide();
            $text_wrapper.empty().append($textarea).append($($.parseHTML('<div class="comment-submit"></div>')).append($submit));
            $.hub.initEditor($textarea, { minHeight: 150 });

            // Save the comment when user clicks the button
            $submit.click(function() {
                var csrf = $('input[name=_csrf]:first').val();
                var new_comment_text = $.trim($textarea.val());
                new_comment_text == '<p><br></p>' && (new_comment_text = '');
                $submit.siblings('.errormsg').remove();
                $.post($edit_link.data('url'), { id: comment_id, text: new_comment_text, _csrf: csrf }, null, 'json').always(function(r, textStatus) {
                    if (textStatus == 'success' && r.status == 'ok') {
                        // Saved successfully
                        $edit_link.off('click', h);
                        $text_wrapper.html(new_comment_text);
                        $comment_wrapper.find('.actions:first').show();
                    } else if (textStatus == 'success' && r.errors) {
                        // Validation error
                        $.each(r.errors, function(key, value) {
                            $submit.parent().append($($.parseHTML('<em class="errormsg" style="margin:0"></em>')).text(value));
                        });
                    } else {
                        // Something bad happened, probably 403.
                        // Revert field back to original state.
                        $submit.parent().remove();
                        $edit_link.closest('.edit-links').remove();
                        $textarea.closest('.redactor-box').css('border', '2px solid red');
                        // !!! should say something to user here?..
                    }
                }, 'json');
            });

            // Replace edit form back with plain html when user clicks on edit link again
            var h;
            $edit_link.one('click', h = function() {
                $text_wrapper.html(comment_text);
                return false;
            });

            return false;
        });

        // Delete link
        $content.on('click', '.comment-delete', function() {
            var $edit_link = $(this);
            var comment_id = $edit_link.closest('[data-id]').data('id');
            if (comment_id && confirm($edit_link.data('confirm'))) {
                $edit_link.closest('.comment').remove();
                var csrf = $('input[name=_csrf]:first').val();
                $.post($edit_link.data('url'), { id: comment_id, _csrf: csrf });
            }
            return false;
        });

    })(); } // }}}

    //LAZYLOADING
    if ($.fn.lazyLoad) { (function() { // {{{
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
                container: '.lazyloading-list',
                load: function () {
                    win.lazyLoad('sleep');

                    var paging = $('.lazyloading-paging').hide();

                    // determine actual current and next item for getting actual url
                    var current = paging.find('li.selected');
                    var next = current.next();
                    var url = next.find('a').attr('href');
                    if (!url) {
                        win.lazyLoad('stop');
                        return;
                    }

                    var list = $('.lazyloading-list');
                    var loading = paging.parent().find('.loading').parent();
                    if (!loading.length) {
                        loading = $('<div><i class="icon16 loading"></i>Loading...</div>').insertBefore(paging);
                    }

                    loading.show();
                    $.get(url, function (html) {
                        var tmp = $('<div></div>').html(html);
                        if ($.Retina) {
                            tmp.find('.lazyloading-list img').retina();
                        }
                        list.append(tmp.find('.lazyloading-list').children());
                        var tmp_paging = tmp.find('.lazyloading-paging').hide();
                        paging.replaceWith(tmp_paging);
                        paging = tmp_paging;

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
                                            loading.show();
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
                            $('.lazyloading-load-more').remove();
                        }

                        loading.hide();
                        tmp.remove();
                    });
                }
            });
        }
    })(); } // }}}

});

/* jQuery tags input */
(function(a){var b=[];var c=[];a.fn.doAutosize=function(b){var c=a(this).data("minwidth"),d=a(this).data("maxwidth"),e="",f=a(this),g=a("#"+a(this).data("tester_id"));if(e===(e=f.val())){return;}var h=e.replace(/&/g,"&").replace(/\s/g," ").replace(/</g,"<").replace(/>/g,">");g.html(h);var i=g.width(),j=i+b.comfortZone>=c?i+b.comfortZone:c,k=f.width(),l=j<k&&j>=c||j>c&&j<d;if(l){f.width(j);}};a.fn.resetAutosize=function(b){var c=a(this).data("minwidth")||b.minInputWidth||a(this).width(),d=a(this).data("maxwidth")||b.maxInputWidth||a(this).closest(".tagsinput").width()-b.inputPadding,f=a(this),g=a("<tester/>").css({position:"absolute",top:-9999,left:-9999,width:"auto",fontSize:f.css("fontSize"),fontFamily:f.css("fontFamily"),fontWeight:f.css("fontWeight"),letterSpacing:f.css("letterSpacing"),whiteSpace:"nowrap"}),h=a(this).attr("id")+"_autosize_tester";if(!a("#"+h).length>0){g.attr("id",h);g.appendTo("body");}f.data("minwidth",c);f.data("maxwidth",d);f.data("tester_id",h);f.css("width",c);};a.fn.addTag=function(d,e){e=jQuery.extend({focus:false,callback:true},e);this.each(function(){var f=a(this).attr("id");var g=a(this).val().split(b[f]);if(g[0]==""){g=[];}d=jQuery.trim(d);var h;if(e.unique){h=a(g).tagExist(d);if(h==true){a("#"+f+"_tag").addClass("not_valid");}}else{h=false;}if(d!=""&&h!=true){a("<span>").addClass("tag").append(a("<span>").text(d).append("  "),a("<a>",{href:"#",title:"Removing tag",text:"x"}).click(function(){return a("#"+f).removeTag(escape(d));})).insertBefore("#"+f+"_addTag");g.push(d);a("#"+f+"_tag").val("");if(e.focus){a("#"+f+"_tag").focus();}else{a("#"+f+"_tag").blur();}a.fn.tagsInput.updateTagsField(this,g);if(e.callback&&c[f]&&c[f]["onAddTag"]){var i=c[f]["onAddTag"];i.call(this,d);}if(c[f]&&c[f]["onChange"]){var j=g.length;var i=c[f]["onChange"];i.call(this,a(this),g[j-1]);}}});return false;};a.fn.removeTag=function(d){d=unescape(d);this.each(function(){var e=a(this).attr("id");var f=a(this).val().split(b[e]);a("#"+e+"_tagsinput .tag").remove();str="";for(var i=0;i<f.length;i++){if(f[i]!=d){str=str+b[e]+f[i];}}a.fn.tagsInput.importTags(this,str);if(c[e]&&c[e]["onRemoveTag"]){var g=c[e]["onRemoveTag"];g.call(this,d);}});return false;};a.fn.tagExist=function(b){return jQuery.inArray(b,a(this))>=0;};a.fn.importTags=function(b){id=a(this).attr("id");a("#"+id+"_tagsinput .tag").remove();a.fn.tagsInput.importTags(this,b);};a.fn.tagsInput=function(d){var e=jQuery.extend({interactive:true,defaultText:"add a tag",minChars:0,width:"300px",height:"100px",autocomplete:{selectFirst:false},hide:true,delimiter:",",unique:true,removeWithBackspace:true,placeholderColor:"#666666",autosize:true,comfortZone:20,inputPadding:6*2},d);this.each(function(){if(e.hide){a(this).hide();}var d=a(this).attr("id");var f=jQuery.extend({pid:d,real_input:"#"+d,holder:"#"+d+"_tagsinput",input_wrapper:"#"+d+"_addTag",fake_input:"#"+d+"_tag"},e);b[d]=f.delimiter;if(e.onAddTag||e.onRemoveTag||e.onChange){c[d]=[];c[d]["onAddTag"]=e.onAddTag;c[d]["onRemoveTag"]=e.onRemoveTag;c[d]["onChange"]=e.onChange;}var g='<div id="'+d+'_tagsinput" class="tagsinput"><div id="'+d+'_addTag">';if(e.interactive){g=g+'<input id="'+d+'_tag" value="" data-default="'+e.defaultText+'" />';}g=g+'</div><div class="tags_clear"></div></div>';a(g).insertAfter(this);a(f.holder).css("width",e.width);a(f.holder).css("height",e.height);if(a(f.real_input).val()!=""){a.fn.tagsInput.importTags(a(f.real_input),a(f.real_input).val());}if(e.interactive){a(f.fake_input).val(a(f.fake_input).attr("data-default"));a(f.fake_input).css("color",e.placeholderColor);a(f.fake_input).resetAutosize(e);a(f.holder).bind("click",f,function(b){a(b.data.fake_input).focus();});a(f.fake_input).bind("focus",f,function(b){if(a(b.data.fake_input).val()==a(b.data.fake_input).attr("data-default")){a(b.data.fake_input).val("");}a(b.data.fake_input).css("color","#000000");});if(e.autocomplete_url!=undefined){autocomplete_options={source:e.autocomplete_url};for(attrname in e.autocomplete){autocomplete_options[attrname]=e.autocomplete[attrname];}if(jQuery.Autocompleter!==undefined){a(f.fake_input).autocomplete(e.autocomplete_url,e.autocomplete);a(f.fake_input).bind("result",f,function(b,c,f){if(c){a("#"+d).addTag(c[0]+"",{focus:true,unique:e.unique});}});}else if(jQuery.ui.autocomplete!==undefined){a(f.fake_input).autocomplete(autocomplete_options);a(f.fake_input).bind("autocompleteselect",f,function(b,c){a(b.data.real_input).addTag(c.item.value,{focus:true,unique:e.unique});return false;});}}else{a(f.fake_input).bind("blur",f,function(b){var c=a(this).attr("data-default");if(a(b.data.fake_input).val()!=""&&a(b.data.fake_input).val()!=c){if(b.data.minChars<=a(b.data.fake_input).val().length&&(!b.data.maxChars||b.data.maxChars>=a(b.data.fake_input).val().length)){a(b.data.real_input).addTag(a(b.data.fake_input).val(),{focus:true,unique:e.unique});}}else{a(b.data.fake_input).val(a(b.data.fake_input).attr("data-default"));a(b.data.fake_input).css("color",e.placeholderColor);}return false;});}a(f.fake_input).bind("keypress",f,function(b){if(b.which==b.data.delimiter.charCodeAt(0)||b.which==13){b.preventDefault();if(b.data.minChars<=a(b.data.fake_input).val().length&&(!b.data.maxChars||b.data.maxChars>=a(b.data.fake_input).val().length)){a(b.data.real_input).addTag(a(b.data.fake_input).val(),{focus:true,unique:e.unique});}a(b.data.fake_input).resetAutosize(e);return false;}else if(b.data.autosize){a(b.data.fake_input).doAutosize(e);}});f.removeWithBackspace&&a(f.fake_input).bind("keydown",function(b){if(b.keyCode==8&&a(this).val()==""){b.preventDefault();var c=a(this).closest(".tagsinput").find(".tag:last").text();var d=a(this).attr("id").replace(/_tag$/,"");c=c.replace(/[\s]+x$/,"");a("#"+d).removeTag(escape(c));a(this).trigger("focus");}});a(f.fake_input).blur();if(f.unique){a(f.fake_input).keydown(function(b){if(b.keyCode==8||String.fromCharCode(b.which).match(/\w+|[áéíóúÁÉÍÓÚñÑ,\/]+/)){a(this).removeClass("not_valid");}});}}return false;});return this;};a.fn.tagsInput.updateTagsField=function(c,d){var e=a(c).attr("id");a(c).val(d.join(b[e]));};a.fn.tagsInput.importTags=function(d,e){a(d).val("");var f=a(d).attr("id");var g=e.split(b[f]);for(var i=0;i<g.length;i++){a(d).addTag(g[i],{focus:false,callback:false});}if(c[f]&&c[f]["onChange"]){var h=c[f]["onChange"];h.call(d,d,g[i]);}};})(jQuery);