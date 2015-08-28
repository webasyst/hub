if (!RedactorPlugins) var RedactorPlugins = {};

(function() { "use strict";

    RedactorPlugins.codeblock = RedactorCodeblockPluginFactory('codeblock', 'pre', 're-icon re-html', 'Insert code block');
    RedactorPlugins.blockquote = RedactorCodeblockPluginFactory('blockquote', 'blockquote', 're-icon re-clips', 'Insert quote');
    if ($.wa) {
        // Use at your own risk, no backwards compatibility or anything. Just saying.
        $.wa.RedactorCodeblockPluginFactory = RedactorCodeblockPluginFactory;
    }

    function RedactorCodeblockPluginFactory(plugin_id, tag, toolbar_button_class, title) {
        return function() {
            var plugin;
            return {
                locfn: window.$_ || function(a) { return a },
                init: function() {
                    plugin = this[plugin_id];
                    title = plugin.locfn(title);

                    plugin.initToolbar();
                    plugin.initEvents();
                    plugin.initModal();
                },

                initToolbar: function() {
                    var button = this.button.add(plugin_id, title);
                    this.button.addCallback(button, plugin.show);
                    this.button.setAwesome(plugin_id, toolbar_button_class);
                },

                initEvents: function() {
                    var offset_before_move = null;
                    this.$editor.on('keydown.redactor-'+plugin_id, $.proxy(function(e) {
                        if (e.which == this.keyCode.DOWN || e.which == this.keyCode.UP) {
                            offset_before_move = this.caret.getOffset();
                        }
                    }, this));
                    this.$editor.on('keyup.redactor-'+plugin_id, $.proxy(function(e) {
                        if (e.which != this.keyCode.DOWN && e.which != this.keyCode.UP) {
                            return;
                        }
                        if (offset_before_move != this.caret.getOffset()) {
                            return;
                        }
                        var $pre = $(this.selection.getBlock());
                        if (!$pre.length || !$pre.is(tag)) {
                            return;
                        }

                        var $node;
                        if (e.which == this.keyCode.DOWN) {
                            $node = $pre.next();
                        } else {
                            $node = $pre.prev();
                        }
                        if ($node.length) {
                            if ($.trim($node.text()) == '') {
                                $node.html('&#8203;');
                            } else {
                                return;
                            }
                        } else {
                            $node = plugin.insertNewline($pre);
                            if (e.which == this.keyCode.UP) {
                                $node.insertBefore($pre);
                            }
                        }
                        this.caret.setStart($node);
                    }, this));
                },

                initModal: function() {
                    this.modal.addTemplate(plugin_id, plugin.getTemplate());
                    this.modal.addCallback(plugin_id, function() {
                        this.modal.createCancelButton();
                        this.modal.createActionButton(plugin.locfn('Insert')).on('click', function() {
                            plugin.insertSnippet(
                                $('#h-'+plugin_id+'-textarea').val()
                            );
                        });
                    });
                },

                show: function() {
                    // Creare a history point for undo/redo
                    this.buffer.set();
                    // Remember position where to insert the code
                    this.selection.save();
                    // Open the dialog we set up previously during init()
                    this.modal.load(plugin_id, title, 800);
                    this.modal.show();
                    $('#h-'+plugin_id+'-textarea').focus();
                },
                getTemplate: function() {
                    var str = '<div id="h-'+plugin_id+'-modal" style="height: 350px; overflow-y: auto;">' +
                            '<section>' +
                                '<textarea id="h-'+plugin_id+'-textarea" style="height: 200px"></textarea>' +
                            '</section>' +
                        '</div>';
                    return str;
                },
                insertSnippet: function(body) {
                    this.modal.close();
                    this.selection.restore();
                    plugin.insertNewline($('<'+tag+'>').text(body).insertAfter(this.selection.getCurrent()));
                    this.code.sync();
                },
                insertNewline: function($prev) {
                    return $('<p>&#8203;</p>').insertAfter($prev);
                }
            };
        };
    }
})();