/**
 * Builder Media Plugin - Integrates Astero Page Builder with Unified Media Picker API
 *
 * This plugin provides seamless integration between the page builder's ImageInput/VideoInput
 * components and the unified media library system.
 *
 * @requirements 2.4, 5.4 - Builder plugin integration with unified media picker API
 */
const BuilderMediaPlugin = {
    currentInput: null,
    currentCallback: null,

    /**
     * Initialize the plugin
     */
    init: function () {
        this.bindEvents();
    },

    /**
     * Bind event listeners for media selection
     */
    bindEvents: function () {
        // Listen for media selection events from the unified media picker
        window.addEventListener('media-selected', (e) => {
            if (this.currentCallback && e.detail?.selectedMedia) {
                this.handleMediaSelection(e.detail.selectedMedia);
            }
        });

        // Listen for media-picker:selected events (dispatched by openBuilderMediaPicker)
        document.addEventListener('media-picker:selected', (e) => {
            if (this.currentCallback && e.detail?.media) {
                this.handleMediaSelection(e.detail.media);
            }
        });

        // Listen for modal close/cancel to reset state
        window.addEventListener('media-selection-cancelled', () => {
            this.resetState();
        });
    },

    /**
     * Handle media selection from the unified picker
     * @param {Object} media - Selected media object
     */
    handleMediaSelection: function (media) {
        if (this.currentCallback) {
            this.currentCallback(media);
        }
        this.resetState();
    },

    /**
     * Reset the plugin state
     */
    resetState: function () {
        this.currentCallback = null;
        this.currentInput = null;
    },

    /**
     * Open the media picker modal using the unified API
     *
     * @param {HTMLInputElement} input - The input element to update
     * @param {Function} [callback] - Optional callback after selection
     */
    openModal: function (input, callback) {
        this.currentInput = input;
        this.currentCallback = callback || this.defaultCallback.bind(this);

        // Determine media type from input context
        const mediaType = this.getMediaType(input);

        // Use the unified media picker API only.
        if (typeof window.openBuilderMediaPicker === 'function') {
            window.openBuilderMediaPicker(input, this.currentCallback, {
                type: mediaType,
                title: mediaType === 'video' ? 'Select Video' : 'Select Image',
            });
            return;
        }

        console.error(
            'BuilderMediaPlugin: openBuilderMediaPicker is not available',
        );
    },

    /**
     * Determine the media type based on input context
     * @param {HTMLInputElement} input
     * @returns {string} - 'image' or 'video'
     */
    getMediaType: function (input) {
        if (input.closest('.video-input')) {
            return 'video';
        }
        if (input.name?.includes('video') || input.id?.includes('video')) {
            return 'video';
        }
        return 'image';
    },

    /**
     * Update input element from selected media
     * @param {HTMLInputElement} input
     * @param {Object} media
     */
    updateInputFromMedia: function (input, media) {
        if (!input || !media) return;

        const mediaUrl = this.extractMediaUrl(media);
        if (!mediaUrl) return;

        // Update the input value
        if (input.tagName === 'INPUT') {
            input.value = mediaUrl;
            input.dispatchEvent(new Event('change', { bubbles: true }));
        }

        // Update preview element
        this.updatePreview(input, media);
    },

    /**
     * Extract URL from media object
     * @param {Object|string} media
     * @returns {string}
     */
    extractMediaUrl: function (media) {
        if (typeof media === 'string') {
            return media;
        }
        if (media.url) {
            return media.url;
        }
        if (media.path) {
            return media.path;
        }
        if (Array.isArray(media) && media.length > 0) {
            return media[0].url || media[0].path || media[0];
        }
        return '';
    },

    /**
     * Update preview element (image or video thumbnail)
     * @param {HTMLInputElement} input
     * @param {Object} media
     */
    updatePreview: function (input, media) {
        const container = input.closest('.image-input, .video-input');
        if (!container) return;

        const mediaUrl = this.extractMediaUrl(media);
        const isVideo = container.classList.contains('video-input');

        if (isVideo) {
            const video = container.querySelector('video');
            if (video) {
                video.src = mediaUrl;
            }
        } else {
            const img = container.querySelector('img');
            if (img) {
                img.src = mediaUrl;
                img.alt = media.alt || media.name || '';
            }
        }
    },

    /**
     * Default callback for media selection
     * @param {Object} mediaData
     */
    defaultCallback: function (mediaData) {
        if (!this.currentInput || !mediaData) return;

        this.updateInputFromMedia(this.currentInput, mediaData);
    },

    // Legacy modal fallback removed: builder now uses unified media picker only.
};

/**
 * Enhanced ImageInput with Unified Media Picker integration
 *
 * Provides image selection functionality for the page builder using
 * the unified media picker API.
 *
 * @requirements 2.4, 5.4 - Builder plugin integration
 */
window.ImageInput = {
    ...(window.ImageInput || {}),
    ...{
        tag: 'img',

        events: [
            ['change', 'onImageChange', 'input[type=text]'],
            ['click', 'onClick', 'button'],
            ['click', 'onClick', 'img'],
        ],

        /**
         * Set the value of the image input
         * @param {string} value - Image URL or path
         */
        setValue: function (value) {
            if (value && value.indexOf('data:image') == -1 && value != 'none') {
                this.element[0].querySelector('input[type="text"]').value =
                    value;
                let src = this.resolveImageUrl(value);
                this.element[0].querySelector(this.tag).src = src;
            } else {
                this.element[0].querySelector(this.tag).src =
                    '/assets/images/image-add-line.svg';
            }
        },

        /**
         * Resolve image URL with proper base path
         * @param {string} value - Image URL or path
         * @returns {string} - Resolved URL
         */
        resolveImageUrl: function (value) {
            // Check if it's already an absolute URL or a known path
            if (
                value.indexOf('//') > -1 ||
                value.indexOf('/assets/') === 0 ||
                value.indexOf('media/') > -1 ||
                value.indexOf('image-cache/') > -1 ||
                value.indexOf('storage/') > -1
            ) {
                return value;
            }
            return Astero.builderAssetsUrl + value;
        },

        /**
         * Handle image change event
         */
        onImageChange: function (event, node, input) {
            let self = this;
            let src = self.value;
            let tag = input.tag;

            let img = node.querySelector(tag);
            if (img.src) {
                src = img.getAttribute('src');
            }

            if (src) {
                input.value = src;
                input.onChange.call(self, event, node, input);
            }

            // Reselect image after loading to adjust highlight box size
            if (Astero.Builder.selectedEl) {
                Astero.Builder.selectedEl.addEventListener(
                    'load',
                    function onLoad() {
                        if (Astero.Builder.selectedEl) {
                            Astero.Builder.selectedEl.click();
                        }
                        this.removeEventListener('load', onLoad);
                    },
                );
            }
        },

        /**
         * Handle click event to open media picker
         */
        onClick: function (e, element) {
            e.preventDefault();

            // Find the input element - use the clicked element (e.target) for context
            let clickedElement = e.target || e.currentTarget || this;
            let input = ImageInput.findTargetInput(clickedElement);

            if (input && input.tagName === 'INPUT') {
                // Store reference to the image element for updating after selection
                const container = input.closest('.image-input');
                const imgElement = container?.querySelector('img');

                BuilderMediaPlugin.openModal(input, function (media) {
                    // Update the input value
                    const url = BuilderMediaPlugin.extractMediaUrl(media);
                    if (url) {
                        input.value = url;
                        // Update the thumbnail preview
                        if (imgElement) {
                            imgElement.src = url;
                        }

                        // Update the selected element in the builder with alt and title
                        const selectedEl = Astero?.Builder?.selectedEl;
                        if (selectedEl && selectedEl.tagName === 'IMG') {
                            // Set alt text from media
                            if (media.alt) {
                                selectedEl.setAttribute('alt', media.alt);
                                // Update the alt input in the right panel
                                const altInput =
                                    document.querySelector('#input-alt');
                                if (altInput) {
                                    altInput.value = media.alt;
                                }
                            }
                            // Set title from media name or title
                            const title = media.title || media.name || '';
                            if (title) {
                                selectedEl.setAttribute('title', title);
                                // Update the title input in the right panel if it exists
                                const titleInput =
                                    document.querySelector('#input-title');
                                if (titleInput) {
                                    titleInput.value = title;
                                }
                            }
                        }

                        // Trigger change event to update the builder
                        input.dispatchEvent(
                            new Event('change', { bubbles: true }),
                        );
                        input.dispatchEvent(
                            new Event('focusout', { bubbles: true }),
                        );
                    }
                });
            } else {
                console.warn(
                    'ImageInput: Could not find target input for media selection',
                );
            }
        },

        /**
         * Find the target input element
         * @param {HTMLElement} el - The clicked element
         * @returns {HTMLInputElement|null}
         */
        findTargetInput: function (el) {
            if (!el) return null;

            // Check for data-target-input attribute which contains an ID selector
            const targetSelector =
                el.getAttribute('data-target-input') ||
                el
                    .closest('[data-target-input]')
                    ?.getAttribute('data-target-input');
            if (targetSelector) {
                // The selector is like "#input-src" - query the document
                const input = document.querySelector(targetSelector);
                if (input) return input;
            }

            // Fallback: look for input in the container
            const container = el.closest('.image-input, .video-input');
            if (container) {
                const input = container.querySelector('input[type="text"]');
                if (input) return input;
            }

            // Legacy fallbacks
            return (
                el.parentElement?.querySelector('input[type="text"]') ||
                el.previousElementSibling ||
                (el.tagName === 'INPUT' ? el : null)
            );
        },

        init: function (data) {
            return this.render('imageinput-gallery', data);
        },
    },
};

/**
 * Enhanced VideoInput with Unified Media Picker integration
 *
 * Provides video selection functionality for the page builder using
 * the unified media picker API.
 *
 * @requirements 2.4, 5.4 - Builder plugin integration
 */
window.VideoInput = {
    ...ImageInput,
    ...{
        tag: 'video',

        events: [
            ['change', 'onChange', 'input[type=text]'],
            ['click', 'onClick', 'button'],
            ['click', 'onClick', 'video'],
        ],

        /**
         * Set the value of the video input
         * @param {string} value - Video URL or path
         */
        setValue: function (value) {
            if (value && value != 'none') {
                this.element[0].querySelector('input[type="text"]').value =
                    value;
                let src = this.resolveVideoUrl(value);
                this.element[0].querySelector(this.tag).src = src;
            } else {
                this.element[0].querySelector(this.tag).src = '';
            }
        },

        /**
         * Resolve video URL with proper base path
         * @param {string} value - Video URL or path
         * @returns {string} - Resolved URL
         */
        resolveVideoUrl: function (value) {
            // Check if it's already an absolute URL or a media library path
            if (
                value.indexOf('//') > -1 ||
                value.indexOf('media/') > -1 ||
                value.indexOf('storage/') > -1
            ) {
                return value;
            }
            return Astero.builderAssetsUrl + value;
        },

        /**
         * Handle click event to open media picker for video
         */
        onClick: function (e, element) {
            e.preventDefault();

            // Find the input element - use the clicked element for context
            let clickedElement = e.target || e.currentTarget || this;
            let input = ImageInput.findTargetInput(clickedElement);

            if (input && input.tagName === 'INPUT') {
                // Store reference to the video element for updating after selection
                const container = input.closest('.video-input');
                const videoElement = container?.querySelector('video');

                BuilderMediaPlugin.openModal(input, function (media) {
                    // Update the input value
                    const url = BuilderMediaPlugin.extractMediaUrl(media);
                    if (url) {
                        input.value = url;
                        // Update the video preview
                        if (videoElement) {
                            videoElement.src = url;
                        }
                        // Trigger change event to update the builder
                        input.dispatchEvent(
                            new Event('change', { bubbles: true }),
                        );
                        input.dispatchEvent(
                            new Event('focusout', { bubbles: true }),
                        );
                    }
                });
            } else {
                console.warn(
                    'VideoInput: Could not find target input for media selection',
                );
            }
        },

        init: function (data) {
            return this.render('videoinput-gallery', data);
        },
    },
};

// Initialize the plugin when DOM is ready
document.addEventListener('DOMContentLoaded', function () {
    BuilderMediaPlugin.init();
});

// Also initialize immediately if DOM is already loaded
if (document.readyState !== 'loading') {
    BuilderMediaPlugin.init();
}

// Export for global access
window.BuilderMediaPlugin = BuilderMediaPlugin;

// Export ImageInput and VideoInput for use in other builder components
window.ImageInput = ImageInput;
window.VideoInput = VideoInput;
