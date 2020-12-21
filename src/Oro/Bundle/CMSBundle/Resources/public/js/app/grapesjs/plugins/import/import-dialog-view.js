import BaseView from 'oroui/js/app/views/base/view';
import template from 'tpl-loader!orocms/templates/grapesjs-import-dialog-template.html';
import DialogWidget from 'oro/dialog-widget';
import {stripRestrictedAttrs, escapeWrapper} from 'orocms/js/app/grapesjs/plugins/grapesjs-style-isolation';
import _ from 'underscore';
import __ from 'orotranslation/js/translator';
import $ from 'jquery';
import ApiAccessor from 'oroui/js/tools/api-accessor';
import LoadingMaskView from 'oroui/js/app/views/loading-mask-view';

const REGEXP_TWIG_TAGS = /\{\{([\w\s\'\_\-\,\(\)]+)\}\}/gi;

const ImportDialogView = BaseView.extend({
    /**
     * @inheritDoc
     */
    optionNames: BaseView.prototype.optionNames.concat([
        'editor', 'importViewerOptions',
        'modalImportLabel', 'modalImportTitle', 'modalImportButton',
        'validateApiProps', 'entityClass', 'fieldName'
    ]),

    /**
     * @inheritDoc
     */
    autoRender: true,

    /**
     * @property {GrapesJS.Instance}
     */
    editor: null,

    /**
     * @property {CodeMirror.Model}
     */
    codeViewer: null,

    /**
     * @property {CodeMirror.Editor}
     */
    viewerEditor: null,

    /**
     * @property {Object}
     */
    importViewerOptions: {
        codeName: 'htmlmixed',
        theme: 'hopscotch',
        readOnly: 0
    },

    /**
     * @property {String}
     */
    modalImportLabel: __('oro.cms.wysiwyg.import.label'),

    /**
     * @property {String}
     */
    modalImportTitle: __('oro.cms.wysiwyg.import.title'),

    /**
     * @property {String}
     */
    modalImportButton: __('oro.cms.wysiwyg.import.button'),

    /**
     * @property {Object}
     */
    dialog: null,

    /**
     * @property {Object}
     */
    dialogOptions: {},

    /**
     * @property {String}
     */
    commandId: null,

    /**
     * @property {String}
     */
    content: '',

    /**
     * @property {Boolean}
     */
    disabled: false,

    /**
     * @property {Function}
     */
    template: template,

    validateApiProps: {
        http_method: 'POST',
        route: 'oro_cms_wysiwyg_validation_validate'
    },

    twigApiResolverProps: {
        http_method: 'POST',
        route: 'oro_cms_wysiwyg_content_resolve'
    },

    entityClass: null,

    fieldName: '',

    markers: [],

    prevContent: '',

    listen: {
        'layout:reposition mediator': 'adjustHeight'
    },

    /**
     * @constructor
     * @param options
     */
    constructor: function ImportDialogView(options) {
        this.checkContent = _.throttle(_.bind(this.checkContent, this), 2000);
        ImportDialogView.__super__.constructor.call(this, options);
    },

    /**
     * @inheritDoc
     * @param options
     */
    initialize(options) {
        this.codeViewer = this.editor.CodeManager.getViewer('CodeMirror').clone();

        this.codeViewer.set(this.importViewerOptions);

        this.content = this.getImportContent();
        this.validateApiAccessor = new ApiAccessor(this.validateApiProps);
        this.twigResolverAccessor = new ApiAccessor(this.twigApiResolverProps);

        ImportDialogView.__super__.initialize.call(this, options);
    },

    /**
     * @inheritDoc
     * @returns {{modalImportButton: ImportDialogView.modalImportButton}}
     */
    getTemplateData() {
        return {
            modalImportButton: this.modalImportButton
        };
    },

    /**
     * @inheritDoc
     */
    render() {
        ImportDialogView.__super__.render.call(this);

        this.codeViewer.init(this.$el.find('[data-role="code"]')[0]);
        this.viewerEditor = this.codeViewer.editor;

        this.codeViewer.setContent(stripRestrictedAttrs(this.content));

        this.importButton = this.$el.find('[data-role="import"]');

        this.dialog = new DialogWidget({
            autoRender: true,
            el: this.el,
            title: this.modalImportTitle,
            loadingElement: this.editor.getEl(),
            dialogOptions: {
                allowMaximize: true,
                autoResize: false,
                resizable: false,
                modal: true,
                height: 400,
                minHeight: 435,
                minWidth: 856,
                appendTo: this.editor.getEl(),
                dialogClass: 'ui-dialog--import-template',
                close: _.bind(function() {
                    this.dispose();
                }, this)
            }
        });

        this.viewerEditor.refresh();
        this.dialog.widget.on('resize', () => {
            this.adjustHeight();
        });

        this.viewerEditor.refresh();
        this.adjustHeight();

        if (this.editor.ComponentRestriction.allowTags) {
            this.checkContent(this.viewerEditor);
        }

        this.subview('loadingMask', new LoadingMaskView({
            container: this.dialog.loadingElement
        }));

        this.bindEvents();
    },

    /**
     * Binding event listeners
     */
    bindEvents() {
        if (this.editor.ComponentRestriction.allowTags) {
            this.viewerEditor.on('change', codeEditor => {
                this.importButton.attr('disabled', true);
                this.checkContent(codeEditor);
            });
            this.viewerEditor.on('blur', _.bind(this.checkContent, this));
        }

        this.importButton.on('click', _.bind(this.onImportCode, this));
    },

    /**
     * Unbinding event listeners
     */
    unbindEvents: function() {
        this.viewerEditor.off('change');
    },

    /**
     * @inheritDoc
     */
    dispose: function() {
        if (this.disposed) {
            return;
        }

        if (this.commandId) {
            this.editor.stopCommand(this.commandId);
        }

        this.unbindEvents();

        ImportDialogView.__super__.dispose.call(this);
    },

    /**
     * Get content for editor
     * @returns {string}
     */
    getImportContent() {
        return this.editor.getHtml() + '<style>' + this.editor.getCss() + '</style>';
    },

    /**
     * Check if content change
     */
    isChange() {
        return this.prevContent !== this.viewerEditor.getValue();
    },

    /**
     * Check content in editor
     * @param {Editor.Instance} codeEditor
     * @returns {number}
     */
    async checkContent(codeEditor) {
        if (!this.isChange()) {
            return true;
        }

        const messages = [];
        const {success, errors} = await this.validateContent();

        if (this.disposed) {
            return;
        }

        this.importButton.attr('disabled', !success);
        this.disabled = !success;

        this.markers.forEach(marker => marker.clear());
        errors.forEach(({line, message}) => {
            this.markers.push(
                this.viewerEditor.markText(
                    {
                        line: line - 1,
                        ch: 0
                    },
                    {
                        line: line - 1,
                        ch: 1000
                    },
                    {
                        className: 'cm-error'
                    }
                )
            );
            messages.push(message);
        });

        this.validationMessage(messages.join('\n'));

        return success;
    },

    twigResolver(twigContent) {
        this.disabled = false;
        this.subview('loadingMask').show();
        this.importButton.attr('disabled', true);
        return this.twigResolverAccessor.send({}, {
            content: twigContent
        }).then(({content, success}) => {
            if (!success) {
                this.disabled = true;
                this.validationMessage(__('oro.cms.wysiwyg.import.message.twig_exp'));
            }
            this.importButton.attr('disabled', !success);
            return content;
        }).catch(() => {
            this.validationMessage(__('oro.cms.wysiwyg.import.message.twig_exp'));
            this.disabled = true;
        }).always(() => {
            this.subview('loadingMask').hide();
        });
    },

    /**
     * Async validate content
     * @returns {Promise<{readonly errors?: *, readonly success?: *}>}
     */
    validateContent() {
        const content = this.viewerEditor.getValue();

        this.disabled = true;
        this.prevContent = content;

        return this.validateApiAccessor.send({}, {
            content: content.replace(/<style>(.|\n)*?<\/style>/g, ''),
            className: this.entityClass,
            fieldName: this.fieldName
        }).then(({success, errors}) => {
            return {success, errors: _.sortBy(errors, 'line')};
        });
    },

    /**
     * Remove excess style tags
     */
    clearStyleTags() {
        if (this.isolatedContentNode.find('style').length) {
            this.isolatedContentNode.find('style').remove();
            this.isolatedContent = this.isolatedContentNode.prop('outerHTML').trim();
        }
    },

    /**
     * Render validation message
     * @param message
     */
    validationMessage(message) {
        const vMessage = this.$el.find('.validation-failed');

        if (message) {
            if (vMessage.length) {
                vMessage.text(message);
            } else {
                this.$el.append($('<span />', {
                    'class': 'validation-failed'
                }).text(message));
                this.$el.addClass('has-message');
            }
        } else {
            vMessage.remove();
            this.$el.removeClass('has-message');
        }

        this.adjustHeight();
    },

    /**
     * Handle import content
     */
    async onImportCode() {
        let content = this.viewerEditor.getValue().trim();

        REGEXP_TWIG_TAGS.lastIndex = 0;
        if (REGEXP_TWIG_TAGS.test(content)) {
            content = await this.twigResolver(content);
        }

        if (!this.disabled) {
            this.editor.CssComposer.clear();
            this.editor.setComponents(escapeWrapper(content));
            this.dialog.remove();
        }
    },

    /**
     * Adjust height code editor
     */
    adjustHeight() {
        const height = this.$el.find('.validation-failed').height() || 0;
        this.viewerEditor.setSize(null, this.dialog.widget.height() - height);
        this.dialog.resetDialogPosition();
    }
});

export default ImportDialogView;
