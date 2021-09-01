(function($) {
    $.topics_edit = {

        options: {},

        /**
         * number
         */
        hub_id: 0,

        /**
         * jquery object of form
         */
        form: null,

        /**
         * Jquery object of submit button
         */
        button: null,

        /**
         * titleInput object
         */
        title_input: null,

        /**
         * tagsInput object
         */
        tags_input: null,

        init: function(options) {
            this.options = options || {};
            this.hub_id = options.hub_id;
            this.initView();
        },

        initView: function() {

            // Glory to the self-documenting code!
            this.initForm();
            this.initEditor();
            this.initTagsInput();
            this.initMetaDataDialog();
            this.initTopicUrlEditor();
            this.initCategoriesSelector();
            this.initSubscribersNotification();
            this.initTypeSelector();
            this.initHubSelector();
            this.initDateEditor();

            // Make button bar sticky
            var $window = $(window);
            $('#h-topic-button-bar').sticky({
                fixed_class: 'h-fixed-button-bar',
                isStaticVisible: function (e, o) {
                    return  $window.scrollTop() + $window.height() >= e.element.offset().top + e.element.outerHeight();
                }
            });

            // Make sure the form is in non-changed state
            this.button.removeClass('yellow').addClass('green');
        },

        initHubSelector: function() {
            var that = this;
            var hubs_selector = $("#hubs-selector");
            hubs_selector.on('click', 'a', function () {
                var self = $(this);
                hubs_selector.find("li.selected").removeClass('selected');
                self.parent('li').addClass('selected');
                self.closest('.h-stream').attr('class', 'h-stream h-'+self.data('color'));
                $("#topic-hub input").val(self.data('id'));
                $("#topic-hub b i").text(self.text());

                that.hub_id = self.data('id');

                hubs_selector.trigger('change');

                return false;
            });
            hubs_selector.find('a[data-id="'+that.hub_id+'"]').click();
            $("#topic-hub").click(function () {
                return false;
            });
        },

        initTypeSelector: function() {

            var $hubs_selector = $("#hubs-selector");
            var $wrapper = $.topics_edit.form.find('.h-topic-type:first');
            var $input = $('#topic-type');

            // Change topic type when user clicks on its element
            $wrapper.on('click', 'a', function () {
                var $a = $(this);
                $('.h-topic-type .selected').removeClass('selected');
                $a.closest('li').addClass('selected').find('i.h-glyph16').addClass('selected');
                $input.val($a.data('type')).change();
                return false;
            });

            // List of topic types depends on selected hub
            var updateTypesList;
            $hubs_selector.change(updateTypesList = function() {
                var hub_id = $hubs_selector.find('li.selected a').data('id');

                // Types available in current hub
                var type_ids = {};
                $.each($.topics_edit.options.hub_type_ids[hub_id] || [], function(i, type_id) {
                    type_ids[type_id] = true;
                });

                // Update type selector: show types available in new hub,
                // and hide everything else
                var selected_exists = false;
                var selected_is_hidden = false;
                $wrapper.find('li').each(function() {
                    var $li = $(this);
                    var type_id = $li.find('a').data('type');
                    if (type_ids[type_id]) {
                        $li.show();
                        if ($li.hasClass('selected')) {
                            selected_exists = true;
                        }
                    } else {
                        var $li = $li.hide();
                        if ($li.hasClass('selected')) {
                            selected_is_hidden = true;
                            selected_exists = true;
                        }
                    }
                });

                // Select first available type if previously selected type
                // is not available in new hub
                if (selected_is_hidden || !selected_exists) {
                    var $li = $wrapper.find('li:visible:first');
                    if ($li.length) {
                        $li.find('a').click();
                    } else {
                        selected_exists && $wrapper.find('.selected').removeClass('selected');
                        $input.val('').change();
                    }
                }
            });
            updateTypesList();

            $wrapper.show();
        },

        initForm: function() {

            var that = $.topics_edit;
            that.form = $("#topic-form");
            that.button = that.form.find('.button-form-submit');


            $('#ul-priority a').click(function () {
                $('#ul-priority li.selected').removeClass('selected');
                $(this).parent().addClass('selected');
                $('#input-priority').val($(this).data('value')).change();
                return false;
            });

            // Save as a draft button
            that.form.find('.draft').click(function() {
                $.topics_edit.form.append('<input type="hidden" name="draft" value="1">');
            });

            // Link to un-publish the topic
            $('#h-unpublish-link').click(function() {
                if (confirm($(this).attr('title'))) {
                    $.topics_edit.form.append('<input type="hidden" name="draft" value="1">');
                    $.topics_edit.form.submit();
                }
            });

            that.form.submit(function () {
                if (that.button.first().prop('disabled')) {
                    return false;
                }
                $('#topic-editor').waEditor2('sync');
                that.tags_input.save();
                var notification_sent = !!that.form.find('input[name="users_to_notify"]').val();
                that.button.prop('disabled', true).parent().append('<i class="icon16 loading" style="margin-top: 12px;"></i>');
                $.post($(this).attr('action'), $(this).serialize(), function (response) {
                    that.button.prop('disabled', false).siblings('.loading').remove();
                    if (response.status == 'ok') {
                        that.button.removeClass('yellow').addClass('green');
                        //that.tags_input.hide(); // tags input is never hidden anymore

                        // When the topic is just saved, remember its ID
                        if (response.data.add) {
                            that.form.attr('action', that.form.attr('action') + '&id=' + response.data.topic.id);
                        }

                        $.hub.forceHash('#/topic/edit/' + response.data.topic.id + '/');

                        if (response.data.topic && response.data.topic.status == '0') {
                            if (that.form.is('.published')) {
                                that.form.removeClass('published').addClass('draft');
                                $.sidebar.reload();
                            }

                            var t = response.data.topic;
                            var li = $('#draft-' + t.id);
                            if (!li.length) {
                                li = $('<li id="draft-' + t.id +'" class="selected" data-contact-id="' + t.contact_id + '">' +
                                    '<a href="#/topic/edit/' + t.id + '/">' +
                                    '<i class="icon16 userpic20" style="background-image: url(\' '  + response.data.contact.photo + ' \')"></i><span class="title">'  + t.title + '</span>' +
                                    '<br><span class="hint">' + t.datetime + '</span></a></li>');
                                $('#hub-drafts').append(li).closest('.block').slideDown();
                            } else {
                                li.find('.title').html(t.title);
                            }

                        } else {
                            if (that.form.is('.draft')) {
                                that.form.removeClass('draft').addClass('published');
                                $.sidebar.reload();

                                // Redirect to topic page. Load by hand to pass extra params.
                                $.hub.forceHash('#/topic/' + response.data.topic.id + '/');
                                $.hub.load('?module=topics&action=info&id=' + response.data.topic.id + (notification_sent ? '&notifications_sent=1' : ''));

                            }
                            $('#draft-' + response.data.topic.id).remove();
                        }

                        // Remove the hidden field that could have been added by unpublish link
                        // or draft button
                        that.form.find('input:hidden[name="draft"]').remove();

                        var e = new $.Event('wa_response');
                        e.response_data = response.data;
                        that.form.trigger(e);
                    }
                }, "json");

                return false;
            });

            // Turn the button yellow when something's changed by keyboard event
            that.form.on('input keypress', function(e) {
                if ((e.type == 'change' || e.charCode) && that.button.hasClass('green') && !$(e.target).hasClass('ignore-dirty')) {
                    that.button.removeClass('green').addClass('yellow');
                }
            });

            // Do not submit the form after Enter in input field
            that.form.on('keypress', 'input:text', function(e) {
                return (e.which || 0) !== 13;
            });

        },

        initTagsInput: function() {
            var that = this;
            this.tags_input = tagsInput({
                target: 'h-topic-tags',
                autocomplete_url: '',
                autocomplete: {
                    source: function(request, response) {
                        $.getJSON(
                            '?module=topics&action=tagsAutocomplete&term=' +
                                request.term +
                                '&hub_id=' + that.hub_id,
                        function(data) {
                            response(data);
                        });
                    }
                },
                width: '', // from .css
                height: '', // from .css
                defaultText: '',
                onAddTag: function() {
                    that.button.removeClass('green').addClass('yellow');
                },
                onRemoveTag: function() {
                    that.button.removeClass('green').addClass('yellow');
                }
            });
        },

        initMetaDataDialog: function() { "use strict";
            var wrapper = $('#meta-editor'),
                input_topic = wrapper.find('input[name="topic[meta_title]"]'),
                input_keywords = wrapper.find('input[name="topic[meta_keywords]"]'),
                input_description = wrapper.find('input[name="topic[meta_description]"]'),
                input_og_title = wrapper.find('input[name="og[title]"]'),
                input_og_image = wrapper.find('input[name="og[image]"]'),
                input_og_video = wrapper.find('input[name="og[video]"]'),
                input_og_description = wrapper.find('input[name="og[description]"]'),
                input_params = wrapper.find('input[name="params_string"]'),
                edit_link = $('#edit-meta-link');

            var dialog = $('#meta-dialog'),
                dialog_topic = dialog.find('input[name="title"]'),
                dialog_keywords = dialog.find('textarea[name="keywords"]'),
                dialog_description = dialog.find('textarea[name="description"]'),
                dialog_og_title = dialog.find('input[name="og_title"]'),
                dialog_og_image = dialog.find('input[name="og_image"]'),
                dialog_og_video = dialog.find('input[name="og_video"]'),
                dialog_og_description = dialog.find('textarea[name="og_description"]'),
                dialog_params = dialog.find('textarea[name="params"]'),
                switcher = dialog.find('.js-settings-custom-switcher');

            var dialog_is_initialized = false;

            inputs2sidebar();

            dialog_topic.on('change', function () {
                if (switcher.prop('checked')) {
                    dialog_og_title.val($(this).val());
                }
            });

            dialog_description.on('change', function () {
                if (switcher.prop('checked')) {
                    dialog_og_description.val($(this).val());
                }
            });

            switcher.on('change', function () {
                if ($(this).prop('checked')) {
                    dialog_og_title.attr('disabled', true).val(dialog_topic.val());
                    dialog_og_description.attr('disabled', true).val(dialog_description.val());
                } else {
                    dialog_og_title.attr('disabled', false).val('');
                    dialog_og_description.attr('disabled', false).val('');
                }
            });

            if (switcher.prop('checked')) {
                switcher.change();
            }

            edit_link.on("click", function(event) {
                event.preventDefault();
                showDialog();
            });

            // Update sidebar according to data in hidden inputs
            function inputs2sidebar() {
                var html = [];
                $.each([[input_topic, dialog_topic],
                        [input_keywords, dialog_keywords],
                        [input_description, dialog_description],
                        [input_og_title, dialog_og_title],
                        [input_og_image, dialog_og_image],
                        [input_og_video, dialog_og_video],
                        [input_og_description, dialog_og_description],
                        [input_params, dialog_params]],
                    function(i, inputs) {
                        var input = inputs[0];
                        if (input.val()) {
                            // Human-readable field name is taken from dialog HTML
                            var dialog_input = inputs[1];
                            var clone = dialog_input.closest('.field').children('.name').clone();
                            clone.find('.hint').remove();
                            var fld_name = $.trim(clone.text());
                            var value_escaped = clone.text(input.val()).html().replace(new RegExp("\n", 'g'), "<br>\n");
                            html.push('<span class="pair"><span class="gray">'+fld_name+':</span> <span class="val">'+value_escaped+'</span></span><br>');
                        }
                    }
                );
                if (html.length) {
                    wrapper.find('.parameters').html(html.join("\n")).show();
                    wrapper.find('.no-parameters').hide();
                } else {
                    wrapper.find('.parameters').hide();
                    wrapper.find('.no-parameters').show();
                }
            }

            // Update dialog form according to data in hidden inputs
            function dialog2inputs() {
                input_topic.val(dialog_topic.val()).change();
                input_keywords.val(dialog_keywords.val()).change();
                input_description.val(dialog_description.val()).change();
                input_og_image.val(dialog_og_image.val()).change();
                input_og_video.val(dialog_og_video.val()).change();
                if (switcher.prop('checked')) {
                    input_og_title.val('').change().prop('disabled', true);
                    input_og_description.val('').change().prop('disabled', true);
                } else {
                    input_og_title.val(dialog_og_title.val()).change().prop('disabled', false);
                    input_og_description.val(dialog_og_description.val()).change().prop('disabled', false);
                }
                input_params.val(dialog_params.val()).change();
            }

            // Update hidden inputs according to data in dialog form
            function inputs2dialog() {
                dialog_topic.val(input_topic.val());
                dialog_keywords.val(input_keywords.val());
                dialog_description.val(input_description.val());
                dialog_og_title.val(input_og_title.val());
                dialog_og_image.val(input_og_image.val());
                dialog_og_video.val(input_og_video.val());
                dialog_og_description.val(input_og_description.val());
                dialog_params.val(input_params.val());

                if (switcher.prop('checked')) {
                    switcher.change();
                }
            }

            function showDialog() {
                if (dialog_is_initialized) {
                    inputs2dialog();
                    dialog.show();
                } else {
                    dialog = dialog.waDialog({
                        onLoad: function() {
                            inputs2dialog();
                        },
                        onSubmit: function() {
                            dialog2inputs();
                            inputs2sidebar();
                            hideDialog();
                            return false;
                        }
                    });
                    dialog_is_initialized = true;
                }
            }

            function hideDialog() {
                dialog.trigger('close');
            }
        },

        initTopicUrlEditor: function() { "use strict";
            var hub_url_templates = $.topics_edit.options.hub_url_templates;

            var wrapper = $('#public-url-editor');
            var wr_editable = wrapper.find('.editable-url');
            var wr_not_editable = wrapper.find('.not-editable-url');
            var wr_no_frontend = wrapper.find('.no-frontend');
            var hubs_selector = $("#hubs-selector");

            var topic_url_input = $('#h-topic-url-input');

            wrapper.find('.edit-link').click(function() {
                enableEditMode();
                topic_url_input.focus();
                return false;
            });

            hubs_selector.change(function() {
                enableEditMode(); // since URLs will not work until saved anyway, no reason to keep them clickable
                updateURLs();
            });

            $.topics_edit.form.on('wa_response', function(e) {
                // If the topic is saved as not a draft, put the editor in clickable mode
                if (e.response_data.topic && e.response_data.topic.status == '1') {
                    wr_not_editable.show();
                    wr_editable.hide();
                    wr_no_frontend.hide();
                    updateURLs();
                }
            });

            updateURLs();

            if ($.topics_edit.options.topic_id) {
                if ($.topics_edit.form.find('input.draft:submit').length > 0) {
                    setTimeout(enableEditMode, 0);
                }
                wrapper.removeClass('hidden');
            } else {
                // Auto-fill url field when user modifies draft title
                $.topics_edit.title_input = titleInput({
                    src: 'h-topic-title',
                    dst: 'h-topic-url-input'
                });

                // For new topics the editor is not visible until user starts to enter the topic title
                topic_url_input.one('change', function() {
                    wrapper.slideDown().removeClass('.hidden');
                    enableEditMode();
                });
                topic_url_input.one('keyup', function() {
                    $.topics_edit.title_input.stop();
                });
            }

            // Update human-visible URLS in links etc. using data from the form (hub_id and topic_url).
            function updateURLs() {
                var hub_id = hubs_selector.find('li.selected a').data('id');
                if (!hub_id || !hub_url_templates[hub_id]) {
                    wr_not_editable.hide();
                    wr_no_frontend.show();
                    wr_editable.hide();
                    return;
                } else if (wr_no_frontend.is(':visible')) {
                    wr_no_frontend.hide();
                    wr_editable.show();
                }

                var url_stub = hub_url_templates[hub_id].replace('%topic_id%', $.topics_edit.options.topic_id || '<id>');
                wr_editable.find('.before-topic-url').text(url_stub.split('%topic_url%')[0]);
                wr_editable.find('.after-topic-url').text(url_stub.split('%topic_url%')[1] || '');

                var url = url_stub.replace('%topic_url%', topic_url_input.val());
                wr_not_editable.find('a.preview').text(url).attr('href', url);
            }

            // Hide the clickable link and show text field to modify topic URL.
            function enableEditMode() {
                if (wr_not_editable.is(':visible')) {
                    wr_not_editable.hide();
                    wr_editable.show();
                }
            }
        },

        initCategoriesSelector: function() { "use strict";

            var $hubs_selector = $("#hubs-selector");

            updateCategoriesInput();
            $hubs_selector.change(function() {
                updateCategoriesInput();
            });

            function updateCategoriesInput() {

                var hub_id = $hubs_selector.find('li.selected a').data('id');

                var categories_wrapper = $('#categories-editor');
                var categories_input_wrapper = $('#categories-input');

                // When selector is already updated to match current hub,
                // there's nothing else to do.
                var selector_hub_id = categories_input_wrapper.data('hub_id');
                if (selector_hub_id && selector_hub_id == hub_id) {
                    return;
                }

                categories_input_wrapper.empty().data('hub_id', hub_id);

                // Are there any categories in this hub?
                if (!$.topics_edit.options.categories[hub_id]) {
                    categories_wrapper.slideUp();
                    return;
                }

                categories_wrapper.slideDown();

                // Pre-selected categories for this topic
                var selected_categories;
                if (hub_id == $.topics_edit.options.initial_hub_id) {
                    selected_categories = $.topics_edit.options.initial_topic_categories;
                } else {
                    selected_categories = [];
                }

                // For each category this topic is in, create a <select> element.
                $.each(selected_categories, function(i, category_id) {
                    i > 0 && categories_input_wrapper.append($.parseHTML('<br>'));
                    categories_input_wrapper.append(createSelectElement(hub_id, category_id));
                });

                // If no <select>s are created, make an empty one.
                if (!selected_categories.length) {
                    categories_input_wrapper.append(createSelectElement(hub_id));
                }
            }

            function createSelectElement(hub_id, category_id) {
                var options = [$($.parseHTML('<option value=""></option>'))];
                $.each($.topics_edit.options.categories[hub_id], function(i, c) {
                    options.push($($.parseHTML('<option value="'+c.id+'"'+(category_id == c.id ? ' selected' : '')+'></option>')).text(c.name));
                });
                return $($.parseHTML('<select name="topic[categories][]"></select>')).append(options);
            }

        },

        initSubscribersNotification: function() {

            var $wrapper = $('#h-topic-button-bar').children(':first');
            var $form_wrapper = $('#subscribers-form-wrapper');
            var $autocomplete = $form_wrapper.find('.subscriber-autocomplete:first');
            var $users_wrapper = $autocomplete.siblings('.users-to-notify');

            // Toggle edit / notifications mode when user clicks "Notify subscribers about this topic"
            $wrapper.on('click', '.notify-subscribers-toggle', function() {
                $wrapper.toggleClass('editing selecting-subscribers');
            });

            // Autocomplete to search users
            $autocomplete.autocomplete({
                source: '?action=autocomplete&type=usergroup&fullgroups=1',
                minLength: 2,
                delay: 300,
                select: function (event, ui) {

                    if (ui.item.id > 0) {
                        addUser(ui.item);
                    } else {
                        $.each(ui.item.users, function(i, v) {
                            addUser(v);
                        });
                    }

                    $autocomplete.val('').focus();
                    return false;
                },
                focus: function (event, ui) {
                    this.value = ui.item.name;
                    return false;
                }
            });

            // Delete user from notify list
            $users_wrapper.on('click', '.icon10.close', function() {
                $(this).closest('.h-notify-user').remove();
            });

            // When submitting through "Publish and notify", add form field to send notifications
            $wrapper.find('.notify-subscribers-submit').click(function() {
                var users_to_notify = [];
                $users_wrapper.children('.h-notify-user').each(function() {
                    users_to_notify.push($(this).data('user-id'));
                });
                if (users_to_notify.length) {
                    $wrapper.append($($.parseHTML('<input type="hidden" name="users_to_notify">')).val(users_to_notify.join(',')));
                }
            });

            // Helper to append user HTML into `To` field.
            function addUser(data) {
                var $div = $users_wrapper.siblings('.template').clone().removeClass('hidden template');
                $div.find('.username').text(data.name);
                $div.data('user-id', data.id);
                if (data.userpic20) {
                    $div.find('.icon-has-userpic').css('background-image', 'url('+data.userpic20+')');
                    $div.find('.icon-no-userpic').remove();
                } else {
                    $div.find('.icon-has-userpic').remove();
                }
                return $div.appendTo($users_wrapper);
            }

        },

        initDateEditor: function() {
            $('#topic-create-date').val($('#topic-create-date').val()*1000); //convert php timestamp to js timestamp

            $('#edit-datetime-link').click(function() {
                var $wrapper = $(this).closest('.block');
                $wrapper.find('.non-editable-view').hide();

                $inputs = $wrapper.find('.editable-view').show().find(':input').prop('disabled', false);
                $input_hidden = $inputs.filter('[type="hidden"]');
                $inputs.filter('.date').datepicker({
                    changeYear: true,
                    changeMonth: true,
                    gotoCurrent: true,
                    constrainInput: false,
                    altField: $input_hidden,
                    altFormat: '@'
                }).datepicker('hide').on('keyup change', function() {
                    if (this.value == '') {
                        $input_hidden.val('');
                    }
                });
            });
        },

        initEditor: function() {
            var that = this;

            if(!$.fileupload) {
                $.wa.loadFiles(wa_url+"wa-content/js/jquery-plugins/fileupload/jquery.fileupload.js?v"+$.hub.framework_version);
            }

            $('#topic-editor').waEditor2({
                allowedTags: 'iframe|img|a|b|i|u|pre|blockquote|p|strong|em|del|strike|span|ul|ol|li|div|span|br|table|thead|tbody|tfoot|tr|td|th|h1|h2|h3|h4|h5|h6'.split('|'),
                formatting: ['p', 'blockquote', 'pre', 'h1', 'h2', 'h3', 'h4', 'h5'],
                buttons: ['format', 'bold', 'italic', 'underline', 'deleted', 'lists',
                          'outdent', 'indent', 'image', 'video', 'table', 'link', 'alignment',
                          'horizontalrule', 'codeblock', 'blockquote'],
                plugins: ['fontcolor', 'fontsize', 'fontfamily', 'table', 'video', 'alignment', 'codeblock', 'blockquote'],
                upload_img_dialog: '#s-upload-dialog',
                lang: $.hub.lang,
                imageUpload: '?module=pages&action=uploadimage&r=2&absolute=1',
                imageUploadFields: {
                    _csrf: that.form.find('input[name="_csrf"]').val()
                },
                callbacks: {
                    keydown: function () {} // without this waEditor intercepts Ctrl+S event in Redactor
                },
                changeCallback: function() {
                    that.form.change();
                    $(window).scroll();
                }
            });

            // without this waEditor intercepts Ctrl+S event in Ace
            ace.edit($('#topic-editor').closest('.h-topic-editor-wrapper').find('> .ace > .ace_editor')[0]).commands.removeCommand('waSave');

            // Save on Ctrl+S, or Cmd+S
            (function() {
                var h;
                $(document).on('keydown', h = function(event) {
                    if (!that.form.closest('body').length) {
                        $(document).off('keydown', h);
                    }
                    return submitIfCtrlS(event);
                });
            })();

            // Make sure sticky bottom buttons behave correctly when user switches between editors
            $('#topic-editor').closest('.h-topic-editor').find('.html,.wysiwyg').click(function() {
                setTimeout(function() { $(window).scroll(); }, 0);
            });

            function submitIfCtrlS(event) {
                if ($.hub.helper.isCtrlS(event)) {
                    if (that.form.is('.draft')) {
                        $.topics_edit.form.append('<input type="hidden" name="draft" value="1">');
                    }
                    that.form.submit();
                    event.preventDefault();
                    return false;
                }
            }
        }
    };



    function titleInput(options) {
        var instance = {

            timer_id: null,

            src_input: null,

            init: function() {
                var src_input = $('#' + options.src);
                var dst_input = $('#' + options.dst);
                var that = this;
                src_input.bind('keydown.topic_edit', function() {
                    if (that.timer_id) {
                        clearTimeout(that.timer_id);
                    }
                    that.timer_id = setTimeout(function() {
                        $.get('?action=transliterate', {
                            str: src_input.val()
                        }, function(r) {
                            if (r.status == 'ok') {
                                dst_input.val(r.data).change();
                            }
                        }, 'json');
                    }, 500);
                });
                this.src_input = src_input;

                return this;
            },
            stop: function() {
                if (this.timer_id) {
                    clearTimeout(this.timer_id);
                }
                this.timer_id = null;
                this.src_input.unbind('keydown.topic_edit');
            }
        };
        return instance.init();
    }

    // constructor of wrapper on tagsinput widget
    function tagsInput(options) {
        var instance = {
            options: {},
            init: function() {

                this.options = options;
                var target_id = options.target;
                var tags = $('#' + target_id);
                var tags_input = $('#' + target_id + '-input');
                var tags_link = $('#' + target_id + '-edit');
                tags_input.tagsInput(options);

                var widget = $('#' + target_id + '-input_tagsinput').hide();

                this.tags = tags;
                this.link = tags_link;
                this.tags_input = tags_input;
                this.widget = widget;

                var that = this;
                tags_link.click(function() {
                    tags_input.val(
                        tags.children('.tag').map(
                            function() { return $(this).text(); }
                        ).toArray().join(',')
                    );
                    tags_input.importTags(tags_input.val());
                    that.show();
                    return false;
                });

                this.show();
                return this;
            },

            setOptions: function(name, value) {
                this.options.name = value;
            },

            save: function () {
                if (this.widget) {
                    var e = jQuery.Event("keypress", {which:13});
                    $('#h-topic-tags-input_tag').trigger(e);
                }
            },

            show: function() {
                this.widget.show();
                this.tags.hide();
                this.link.hide();
            },

            hide: function() {
                this.widget.hide();
                var val = this.tags_input.val();
                if (val) {
                    var unique = {};
                    var tags = [];
                    $(val.split(',')).each(function(i, item) {
                            item = item.toLocaleLowerCase();
                            if (unique[item] !== true) {
                                tags.push('<span class="tag">' + item + '</span>');
                            }
                            unique[item] = true;
                    });
                    this.tags.html(tags.join(' '));
                } else {
                    this.tags.html('<span class="gray">' + $_('No tags assigned')  + '</span>');
                }
                this.tags.show();
                this.link.show();
            },

            clear: function() {
                this.tags_input.importTags('');
                //this.hide(); // tags input is never hidden anymore
            }

        };
        return instance.init();
    }

})(jQuery);
