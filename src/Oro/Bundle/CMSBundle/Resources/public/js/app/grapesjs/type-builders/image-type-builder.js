import __ from 'orotranslation/js/translator';
import BaseTypeBuilder from 'orocms/js/app/grapesjs/type-builders/base-type-builder';
import openDigitalAssetsCommand from 'orocms/js/app/grapesjs/modules/open-digital-assets-command';
import DigitalAssetHelper from 'orocms/js/app/grapesjs/helpers/digital-asset-helper';

const ImageTypeBuilder = BaseTypeBuilder.extend({
    parentType: 'image',

    modelMixin: {
        defaults: {
            tagName: 'img'
        },

        getAttrToHTML() {
            const attrs = this.constructor.__super__.getAttrToHTML.call(this);
            if (attrs['data-src-exp']) {
                attrs['src'] = attrs['data-src-exp'];
                delete attrs['data-src-exp'];
            }
            return attrs;
        }
    },

    viewMixin: {
        onActive: function(e) {
            if (e) {
                e.stopPropagation();
            }

            if (this.model.get('editable')) {
                this._openDigitalAssetManager(this.model);
            }
        },

        /**
         * @inheritDoc
         */
        updateAttributes: function(...args) {
            this.constructor.__super__.updateAttributes.apply(this, args);
            this.postRender();
        },

        postRender() {
            const {$el, model} = this;

            const attrs = model.get('attributes');
            const imageSrc = DigitalAssetHelper.getImageUrlFromTwigTag(attrs['data-src-exp']);

            if (imageSrc) {
                $el.attr('src', imageSrc).removeClass(this.classEmpty);
            } else {
                $el.attr('src', '').addClass(this.classEmpty);
            }
        },

        _openDigitalAssetManager: function(digitalAssetImageComponentModel) {
            this.em.get('Commands').run(
                'open-digital-assets',
                {
                    target: digitalAssetImageComponentModel,
                    title: __('oro.cms.wysiwyg.digital_asset.image.title'),
                    routeName: 'oro_digital_asset_widget_choose_image',
                    onSelect: function(digitalAssetModel) {
                        const {digitalAssetId, uuid, title} = digitalAssetModel.get('previewMetadata');

                        digitalAssetImageComponentModel
                            .addAttributes({
                                'alt': title || '',
                                'data-src-exp': `{{ wysiwyg_image('${digitalAssetId}','${uuid}') }}`
                            });
                    }
                }
            );
        }
    },

    commands: {
        'open-digital-assets': openDigitalAssetsCommand
    },

    constructor: function ImageTypeBuilder(options) {
        ImageTypeBuilder.__super__.constructor.call(this, options);
    },

    createPanelButton() {
        if (this.editor.ComponentRestriction.isAllow(['img'])) {
            this.editor.BlockManager.add(this.componentType, {
                label: __('oro.cms.wysiwyg.component.digital_asset.image'),
                attributes: {
                    'class': 'fa fa-picture-o'
                }
            });
        }
    },

    registerEditorCommands() {
        if (this.editor.Commands.has('open-digital-assets')) {
            return;
        }

        ImageTypeBuilder.__super__.registerEditorCommands.call(this);
    }
});

export default ImageTypeBuilder;
