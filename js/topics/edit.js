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
            this.initTitleEditor();
            this.initEditor();
            this.initTopicSettingsToggle();
            this.initTagsInput();
            this.initMetaDropdown();
            this.initMetaDataDialog();
            this.initTopicUrlDropdown();
            this.initTopicUrlEditor();
            this.initCategoriesSelector();
            this.initSubscribersNotification();
            this.initTypeSelector();
            this.initDateEditor();
            this.initBadgeSelector();
            this.initFormChangesObserver();

            // Make sure the form is in non-changed state
            this.button.removeClass('yellow').addClass('blue');
        },

        initTypeSelector: function() {

            const $hubs_selector_value = $.topics_edit.form.find('.js-meta-hub-selector-value:first');
            const $wrapper = $.topics_edit.form.find('.js-meta-topic-type-wrapper:first');
            const $topicTypeDropdown = $.topics_edit.form.find('.js-meta-topic-type:first');
            const $topicTypeValue = $.topics_edit.form.find('.js-meta-topic-type-value:first');

            // List of topic types depends on selected hub
            $hubs_selector_value.on('change', updateTypesList);

            updateTypesList();

            $wrapper.show();

            function updateTypesList() {
                const hub_id = $hubs_selector_value.val();

                // Types available in current hub
                const type_ids = {};
                $.each($.topics_edit.options.hub_type_ids[hub_id] || [], function(i, type_id) {
                    type_ids[type_id] = true;
                });

                // Update type selector: show types available in new hub,
                // and hide everything else
                let selected_exists = false;
                let selected_is_hidden = false;
                $topicTypeDropdown.find('li').each(function() {
                    const $li = $(this);
                    const type_id = $li.find('a').data('value');

                    if (type_ids[type_id]) {
                        $li.removeClass('hidden');
                        if ($li.hasClass('selected')) {
                            selected_exists = true;
                        }
                    } else {
                        $li.addClass('hidden');
                        if ($li.hasClass('selected')) {
                            selected_is_hidden = true;
                            selected_exists = true;
                        }
                    }
                });

                $topicTypeDropdown.trigger('change');

                if (selected_is_hidden || !selected_exists) {
                    const $li = $topicTypeDropdown.find('li').not('.hidden').first();
                    $wrapper.show();

                    if ($li.length) {
                        $li.find('a').click();
                    } else {
                        selected_exists && $topicTypeDropdown.find('.selected').removeClass('selected');
                        $wrapper.hide();
                        $topicTypeValue.val('').change();
                    }
                }
            }
        },

        initForm: function() {
            const that = $.topics_edit;
            that.form = $("#topic-form");
            that.button = that.form.find('.js-button-form-submit');

            const $topicInfo = that.form.find('.js-topic-info');
            const $topicTitle = that.form.find('.js-edit-topic-title');
            const $topicMeta = that.form.find('.js-topic-meta');
            const $newModeAlert = that.form.find('.js-new-mode-notification');
            const $newModeAlertClose = $newModeAlert.find('.js-close-new-mode-notification');
            const $notificationAlert = that.form.find('.js-saved-with-notification');
            const $hubSelector = that.form.find('.js-hub-select');
            const $unpublishLink = that.form.find('#h-unpublish-link');

            if (!that.options.topic_id) {
                $topicTitle.focus();
            }

            // show/close notification about new editor mode
            const isNotificationAlertHidden = $.storage.get('wa_hub_new_mode_notification');

            if (!isNotificationAlertHidden) {
                $newModeAlert.removeClass('hidden');
            }

            $newModeAlertClose.on('click', function(event) {
                event.preventDefault();

                $.storage.set('wa_hub_new_mode_notification', '1');
                $newModeAlert.remove();
            });

            $hubSelector.find(`option[value="${$.topics_edit.hub_id}"]`).prop('selected', true);

            // Save as a draft button
            that.form.find('.draft').on('click', function() {
                that.form.append('<input type="hidden" name="draft" class="js-topic-draft-input" value="1">');
            });

            // Link to un-publish the topic
            $unpublishLink.on('click', function(event) {
                event.preventDefault();

                const confirmTitle = $(this).attr('title');
                const confirmButtonText = $(this).text();

                $.waDialog.confirm({
                    title: confirmTitle,
                    success_button_title: confirmButtonText,
                    success_button_class: 'warning',
                    cancel_button_title: $.wa.locale['Cancel'],
                    cancel_button_class: 'light-gray',
                    onSuccess() {
                        $.topics_edit.form.append('<input type="hidden" name="draft" value="1">');
                        $.topics_edit.form.submit();
                    }
                });
            });

            that.form.on('submit', function(event) {
                event.preventDefault();

                $topicTitle.blur();

                const isExistSubmitter = (event.originalEvent && event.originalEvent.submitter);
                let $submitButton;
                if (isExistSubmitter) {
                    $submitButton = $(event.originalEvent.submitter);
                    if ($submitButton.hasClass('draft') && $.browser.mobile) {
                        $submitButton.html('<span class="js-loading"><i class="fas fa-spinner fa-spin"></i></span>');
                    } else {
                        $submitButton.append('<span class="custom-ml-4 js-loading"><i class="fas fa-spinner fa-spin"></i></span>');
                    }
                    $submitButton.prop('disabled', true);
                }

                $.post($(this).attr('action'), $(this).serialize(), function(response) {
                    if (response.status !== 'ok') {
                        console.warn(response);
                        return;
                    }

                    if (response.data.error) {
                        console.warn(response);
                    }

                    that.form.find('.js-topic-draft-input').remove();
                    that.button.removeClass('yellow').addClass('blue');

                    // When the topic is just saved, remember its ID
                    if (response.data.add) {
                        that.form.attr('action', that.form.attr('action') + '&id=' + response.data.topic.id);
                    }

                    $.sidebar.reload();

                    if (isExistSubmitter) {
                        $submitButton.prop('disabled', false);
                        const $spinner = $submitButton.find('.js-loading');
                        $spinner.html('<i class="fas fa-check-circle"></i>');

                        setTimeout(() => {
                            $spinner.remove();
                        }, 2000);
                    }

                    if (response.data.topic && response.data.topic.status === '0') {
                        // unpublish topic
                        if (that.form.is('.published') && that.options.topic_id) {
                            $.hub.topicEditAction(response.data.topic.id);

                            return;
                        }

                        // save existing draft
                        if (that.form.is('.draft') && that.options.topic_id) {
                            compareNewData(response.data.topic.id);

                            return;
                        }

                        // save new topic to draft
                        if (!that.options.topic_id) {
                            $.hub.forceHash('#/topic/edit/' + response.data.topic.id + '/');
                            $.hub.topicEditAction(response.data.topic.id);
                        }
                    }

                    if (response.data.topic && response.data.topic.status === '1') {
                        // publish draft
                        if (that.form.is('.draft')) {
                            $.hub.forceHash('#/topic/edit/' + response.data.topic.id + '/');

                            // publish with notification
                            if (that.notificationDialog) {
                                that.notificationDialog.hide();
                                $notificationAlert.show();

                                setTimeout(() => {
                                    $notificationAlert.hide();
                                    $.hub.topicEditAction(response.data.topic.id);
                                }, 2000);

                                return;
                            }

                            // direct publish
                            $.hub.topicEditAction(response.data.topic.id);
                        }

                        // save existing topic
                        if (that.form.is('.published')) {
                            compareNewData(response.data.topic.id);

                            setTimeout(() => {
                                that.form.removeClass('with-changes');
                            }, 2000)
                        }
                    }
                }, "json");

                function compareNewData(id) {
                    $.get('?module=topics&action=edit&id=' + id, function(data) {
                        const $data = $(data);
                        const $dataInfo = $data.find('.js-topic-info');
                        const $dataMeta = $data.find('.js-topic-meta');

                        $topicInfo.html($dataInfo.html());
                        $topicMeta.html($dataMeta.html());
                    });
                }
            });
        },

        initTagsInput: function() {
            const that = this;

            const $tags = that.form.find('.js-topic-tags');
            const $tagList = $tags.find('.js-tags-list');
            const $tagsInputPlace = $tags.find('.js-tags-input-place');
            const $tagsInput = $tags.find('#js-tags-input');
            const $tagsInputHidden = $tags.find('.js-tags-input-hidden');
            let tags = $tagsInputHidden.val() ? $tagsInputHidden.val().split(',') : [];
            const tagTemplate = $tags.find('.js-tag-template').html();

            $tagsInput.tagsInput({
                'defaultText': '',
                'width': '',
                'height': '',
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
                onAddTag(tag) {
                    tags.push(tag);

                    const $tag = $(tagTemplate);

                    $tag.find('.h-tag-name').text(tag);

                    $tag.insertBefore($tagsInputPlace);
                    $tagsInputHidden.val(tags.join(','));

                    that.form.trigger('wa_hub_topic_edit_change');
                }
            });

            const $tagsInputPlugin = that.form.find('#js-tags-input_tag');
            const $plusIcon = $('<i class="fas fa-plus custom-mr-8 gray"></i>');

            $plusIcon.insertBefore($tagsInputPlugin);
            $tagsInputPlugin.attr('placeholder', $.wa.locale['Add_tag'])

            $tagList.on('click', '.h-tag-remove', function(event) {
                event.preventDefault();

                const tagName = $(this).siblings('.h-tag-name').text();
                tags = tags.filter(tag => tag !== tagName);
                $tagsInputHidden.val(tags.join(','));
                $tagsInput.removeTag(tagName);
                $(this).closest('li').remove();

                that.form.trigger('wa_hub_topic_edit_change');
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
                switcher = dialog.find('.js-settings-custom-switcher'),
                dialog_save = dialog.find('.js-meta-save');

            let dialog_instance;

            inputs2sidebar();

            dialog_topic.on('input', function () {
                if (switcher.prop('checked')) {
                    dialog_og_title.val($(this).val());
                }
            });

            dialog_description.on('input', function () {
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

            dialog_save.on('click', function(event) {
                event.preventDefault();
                dialog2inputs();
                inputs2sidebar();
                dialog_instance.hide();
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
                if (dialog_instance) {
                    dialog_instance.show();
                    return;
                }

                dialog_instance = $.waDialog({
                    $wrapper: dialog,
                    onOpen() {
                        inputs2dialog();
                    },
                    onClose(dialog) {
                        dialog.hide();
                        return false;
                    }
                });
            }
        },

        initTitleEditor: function() {
            const that = this;

            const $title = that.form.find('.js-edit-topic-title');
            const $titleInput = that.form.find('#h-topic-title');

            $title.on('input', syncValue);
            $title.on('paste', clearHtml);
            $title.on('keydown', disableOnPressEnter);

            function syncValue(event) {
                $titleInput.val($title.text());
                that.form.trigger('wa_hub_topic_edit_change');
            }

            function clearHtml(event) {
                event.preventDefault();

                let text = event.originalEvent.clipboardData.getData('text/plain');
                text = text.replace(/<[^>]*>?/gm, '');

                if (document.queryCommandSupported('insertText')) {
                    document.execCommand('insertText', false, text);
                } else {
                    document.execCommand('paste', false, text);
                }
            }

            function disableOnPressEnter(event) {
                if (event.keyCode === 13) {
                    event.preventDefault();
                    $title.blur();
                }
            }
        },

        initTopicSettingsToggle: function() {
            const $topicSettingsLink = this.form.find('.js-topic-settings-link');
            const $topicSettings = this.form.find('.js-topic-settings');

            $topicSettingsLink.on('click', function(event) {
                event.preventDefault();

                $topicSettings.slideToggle(200);
            });

            $topicSettings.on('change', () => {
                this.form.trigger('wa_hub_topic_edit_change');
            });
        },

        initTopicUrlDropdown: function() {
            const $dropdown = $('.js-topic-link');

            $dropdown.waDropdown();
        },

        initTopicUrlEditor: function() {
            const that = this;

            const hub_url_templates = $.topics_edit.options.hub_url_templates;

            const $wrapper = $('#public-url-editor');
            const wr_editable = $wrapper.find('.editable-url');
            const wr_no_frontend = $wrapper.find('.no-frontend');
            const $hubs_selector_value = $.topics_edit.form.find('.js-meta-hub-selector-value:first');
            const topic_url_input = $('#h-topic-url-input');

            $hubs_selector_value.on('change', updateURLs);

            topic_url_input.on('input', () => {
                that.form.trigger('wa_hub_topic_edit_change');
            });

            updateURLs();

            if (!$.topics_edit.options.topic_id) {
                // Auto-fill url field when user modifies draft title
                $.topics_edit.title_input = titleInput({
                    src: 'h-topic-title',
                    dst: 'h-topic-url-input'
                });

                topic_url_input.one('keyup', function() {
                    $.topics_edit.title_input.stop();
                });
            }

            // Update human-visible URLS in links etc. using data from the form (hub_id and topic_url).
            function updateURLs() {
                const hub_id = $hubs_selector_value.val();

                if (!hub_id || !hub_url_templates[hub_id]) {
                    wr_no_frontend.removeClass('hidden');
                    wr_editable.addClass('hidden');
                    return;
                } else if (wr_no_frontend.not('.hidden')) {
                    wr_no_frontend.addClass('hidden');
                    wr_editable.removeClass('hidden');
                }

                const url_stub = hub_url_templates[hub_id].replace('%topic_id%', $.topics_edit.options.topic_id || '<id>');
                wr_editable.find('.before-topic-url').text(url_stub.split('%topic_url%')[0]);
                wr_editable.find('.after-topic-url').text(url_stub.split('%topic_url%')[1] || '');
            }
        },

        initCategoriesSelector: function() { "use strict";

            const $hubs_selector = $('.js-meta-hub-selector');
            const $hubs_selector_value = $('.js-meta-hub-selector-value');
            const $categoriesWrapper = $('.js-meta-category-editor');
            const $categoriesDropdown = $('.js-meta-category-dropdown');
            const $categoriesDropdownBody = $categoriesDropdown.find('.dropdown-body');
            const $topicCategoryInput = $('.js-topic-category');

            $hubs_selector.waDropdown({
                items: '.menu > li > a',
                hover: false,
                ready(dropdown) {
                    const currentHubItem = dropdown.$menu.find(`[data-id="${$.topics_edit.hub_id}"]`);
                    currentHubItem.click();
                },
                change(event, item) {
                    const $item = $(item);
                    const id = $item.data('id');

                    $item.closest('li').addClass('selected').siblings().removeClass('selected');
                    $hubs_selector_value.val(id).trigger('change');
                    updateCategoriesInput();
                }
            });

            updateCategoriesInput();

            function updateCategoriesInput() {
                const hub_id = $hubs_selector_value.val();

                // When selector is already updated to match current hub,
                // there's nothing else to do.
                const selector_hub_id = $categoriesDropdown.data('hub_id');
                if (selector_hub_id && selector_hub_id === hub_id) {
                    return;
                }

                $categoriesDropdownBody.empty().data('hub_id', hub_id);

                // Are there any categories in this hub?
                if (!$.topics_edit.options.categories[hub_id]) {
                    $categoriesWrapper.hide();
                    return;
                }

                $categoriesWrapper.show();

                // Pre-selected categories for this topic
                let selected_categories;
                if (hub_id == $.topics_edit.options.initial_hub_id) {
                    selected_categories = $.topics_edit.options.initial_topic_categories;
                } else {
                    selected_categories = [];
                }

                // For each category this topic is in, create a <select> element.
                $.each(selected_categories, function(i, category_id) {
                    $categoriesDropdownBody.append(createSelectElement(hub_id, category_id));
                });

                // If no menus are created, make an empty one.
                if (!selected_categories.length) {
                    const toggleText = $.wa.locale['choose_category'] || 'Choose category...';
                    $categoriesDropdownBody.append(createSelectElement(hub_id));
                    $categoriesDropdown.find('.dropdown-toggle').text(toggleText);
                    $topicCategoryInput.val('');
                }

                if (selected_categories.length) {
                    $categoriesDropdownBody.find('.selected a').click();
                }
            }

            function createSelectElement(hub_id, category_id) {
                const options = [];

                $.each($.topics_edit.options.categories[hub_id], function(i, c) {
                    let optionClass = category_id === Math.abs(c.id) ? ' selected' : '';
                    options.push($(`<li class="${optionClass}"><a href="#" data-value="${c.id}">${c.name}</a></li>`));
                });

                return $('<ul class="menu"></ul>').append(options);
            }

        },

        initMetaDropdown: function() {
            const $metaDropdown = $('.js-meta-dropdown');

            $metaDropdown.each((index, dropdown) => {
                const $dropdown = $(dropdown);
                const $dropdownInput = $dropdown.next('.js-meta-dropdown-value');

                $dropdown.waDropdown({
                    items: '.menu > li > a',
                    hover: false,
                    change(event, item) {
                        const $item = $(item);
                        const value = $item.data('value');

                        $item.closest('li').addClass('selected').siblings().removeClass('selected');
                        $dropdownInput.val(value);
                    }
                });
            });
        },

        initSubscribersNotification: function() {
            const that = this;

            const $notificationButton = $('.notify-subscribers-toggle');
            const notificationHtml = $('.js-notification-publish').html();
            let users_to_notify = [];

            $notificationButton.on('click', function(event) {
                event.preventDefault();

                if (that.notificationDialog) {
                    that.notificationDialog.show();
                    setAutocomplete(that.notificationDialog);
                    return;
                }

                that.notificationDialog = $.waDialog({
                    html: notificationHtml,
                    onOpen($dialog, dialog) {
                        setAutocomplete(dialog);
                    },
                    onClose(dialog) {
                        dialog.$content.find('.subscriber-autocomplete').autocomplete('destroy');
                        dialog.hide();
                        return false;
                    }
                });
            });

            function setAutocomplete(dialog) {
                const $autocomplete = dialog.$content.find('.subscriber-autocomplete');
                const $users_wrapper = $autocomplete.siblings('.users-to-notify');

                $autocomplete.autocomplete({
                    source: '?action=autocomplete&type=usergroup&fullgroups=1',
                    minLength: 2,
                    delay: 300,
                    select(event, ui) {
                        $autocomplete.val('').focus();

                        const userInList = users_to_notify.includes(ui.item.id);
                        if (userInList) {
                            return false;
                        }

                        if (ui.item.id > 0) {
                            addUser(ui.item);
                        } else {
                            $.each(ui.item.users, function(i, v) {
                                addUser(v);
                            });
                        }

                        return false;
                    },
                    focus(event, ui) {
                        this.value = ui.item.name;
                        return false;
                    }
                });

                // Delete user from notify list
                $users_wrapper.on('click', '.close', function() {
                    const id = $(this).closest('.h-notify-user').data('user-id');
                    users_to_notify = users_to_notify.filter(item => +item !== id);
                    $(this).closest('.h-notify-user').remove();
                    dialog.resize();
                });

                // When submitting through "Publish and notify", add form field to send notifications
                const $submitButton = dialog.$wrapper.find('.js-notify-subscribers-submit');
                $submitButton.on('click', function(event) {
                    event.preventDefault();

                    $(this).append('<i class="fas fa-spinner fa-spin custom-ml-4 js-loading"></i>');

                    if (users_to_notify.length) {
                        const notificationText = dialog.$content.find('.notification-message').val();
                        that.form.append($(`<input type="hidden" name="users_to_notify" value="${users_to_notify.join(',')}">`));
                        that.form.append(`<textarea class="hidden" name="notification_message">${notificationText}</textarea>`);
                    }

                    that.form.submit();
                });

                // Helper to append user HTML into `To` field.
                function addUser(data) {
                    const $div = $users_wrapper.siblings('.template').clone().removeClass('hidden template');
                    $div.find('.username').text(data.name);
                    $div.attr('data-user-id', data.id);
                    users_to_notify.push(data.id);
                    dialog.resize();

                    return $div.appendTo($users_wrapper);
                }
            }
        },

        initDateEditor: function() {
            $('#topic-create-date').val($('#topic-create-date').val()*1000); //convert php timestamp to js timestamp

            $('#edit-datetime-link').on('click', function(event) {
                event.preventDefault();

                const $wrapper = $(this).closest('.js-editable-switch');
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
            const that = this;

            RedactorX('#topic-editor', {
                control: true,
                context: true,
                codemirror: {
                    lineNumbers: true,
                    lineWrapping: true,
                    mode: {
                        name: 'text/x-smarty',
                        baseMode: 'text/html'
                    },
                    theme: 'monokai'
                },
                editor: {
                    lang: $.hub.lang || 'en',
                    minHeight: '300px'
                },
                replaceTags: false,
                clean: {
                    enter: false,
                    comments: true
                },
                buttons: {
                    context: ['bold', 'italic', 'deleted', 'code', 'link', 'alignment', 'sub', 'sup', 'kbd']
                },
                toolbar: {
                    hide: ['bold', 'italic', 'deleted', 'link']
                },
                topbar: !(!!that.options.topic_id),
                format: ['p', 'h1', 'h2', 'h3', 'h4', 'h5', 'ul', 'ol'],
                image: {
                    upload: '?module=pages&action=uploadimage&r=x&absolute=1',
                    data: {
                        _csrf: that.form.find('input[name="_csrf"]').val()
                    }
                },
                buttons: {
                    topbar: ['undo', 'redo', 'shortcut']
                },
                quote: {
                    template: `<blockquote data-placeholder="${$.wa.locale["blockquote"] || 'Quote...'}"></blockquote>`
                },
                plugins: ['alignment', 'blockcode', 'imageposition', 'inlineformat', 'removeformat', 'allowstyle'],
                subscribe: {
                    'editor.change': function(event) {
                        that.form.trigger('wa_hub_topic_edit_change');
                    },
                    'editor.before.copy': function(event) {
                        event.stop();

                        const clipboard = event.params.e.clipboardData;

                        const html = $.wa.decodeHTML(event.get('html'));
                        const text = this.app.content.getTextFromHtml(html, { nl: true });

                        clipboard.setData('text/html', html);
                        clipboard.setData('text/plain', text);
                    },
                    'editor.click': function(event) {
                        const e = event.params.e;
                        const link = this.dom(e.target).closest('a');

                        if (e.ctrlKey && !e.shiftKey && link.length) {
                            const a = document.createElement('a');
                            a.setAttribute('href', e.target.href);
                            a.setAttribute('target', '_blank');
                            a.click();
                            a.remove();
                        }

                        if (e.ctrlKey && e.shiftKey && link.length) {
                            window.location.href = e.target.href;
                        }
                    },
                    'popup.open': function(event) {
                        if (this.app.popup.name === 'link-items') {
                            const link = this.app.selection.getNodes({ tags: ['a'] });
                            if (link.length) {
                                const nodes = this.dom(this.app.popup.getItems().nodes[0]);
                                const newTabLocale = $.wa.locale["open_in_new_tab"];

                                nodes.prepend(`
                                    <div class="rx-popup-item rx-popup-stack-item">
                                        <a href="${link[0].href}" class="flexbox width-100" target="_blank">
                                            <span class="wide">${newTabLocale || 'Open in new tab'}</span>
                                            <span class="custom-ml-4"><i class="fas fa-external-link-alt"></i></span>
                                        </a>
                                    </div>
                                `);

                                if (!newTabLocale) {
                                    console.error('Missing locale: open_in_new_tab');
                                }
                            }
                            return;
                        }

                        const $inputUrl = this.app.popup.getElement().find('.rx-form-input[name="url"]');
                        const $btn = this.app.popup.getFooterPrimary();

                        if ($inputUrl.length) {
                            $inputUrl.on('keydown', (event) => {
                                if (event.which === 13) {
                                    event.stopPropagation();
                                    $btn.click();
                                }
                            })
                        }
                    }
                },
            });

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

            function submitIfCtrlS(event) {
                if ($.hub.helper.isCtrlS(event)) {
                    event.preventDefault();

                    if (that.form.is('.draft')) {
                        $.topics_edit.form.append('<input type="hidden" name="draft" value="1">');
                    }
                    that.form.submit();
                }
            }
        },

        initBadgeSelector: function() {
            const $wrapper = $('.js-badge-selector');

            if (!$wrapper.length) {
                return;
            }

            const that = this;

            $wrapper.waDropdown({
                items: ".menu > li > a",
                change(event, target, dropdown) {
                    const badge_id = $(target).data('id');

                    dropdown.$button.find('.h-badge').remove();
                    dropdown.$button.attr('data-name', badge_id);
                    dropdown.$button.prepend('<i class="fas fa-spinner fa-spin custom-mr-4"></i>');

                    $.post(`?module=topics&action=changeBadge&id=${that.options.topic_id}`, { badge: badge_id }).always(function() {
                        dropdown.$button.find('.fa-spinner').remove();
                    });
                }
            });
        },

        initFormChangesObserver: function() {
            const that = this;

            if (!that.options.topic_id) {
                return;
            }

            that.form.on('wa_hub_topic_edit_change', () => {
                if (that.button.hasClass('blue')) {
                    that.button.removeClass('blue').addClass('yellow');
                }

                that.form.addClass('with-changes');
            });
        }
    };

    function titleInput(options) {
        var instance = {

            timer_id: null,

            src_input: null,

            init: function() {
                const src_input = $('.' + options.src);
                const dst_input = $('#' + options.dst);
                const that = this;

                src_input.bind('keydown.topic_edit', function() {
                    if (that.timer_id) {
                        clearTimeout(that.timer_id);
                    }
                    that.timer_id = setTimeout(function() {
                        $.get('?action=transliterate', {
                            str: src_input.text()
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

})(jQuery);
