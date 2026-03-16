Astero.ComponentsGroup['Widgets'] = [
    'widgets/googlemaps',
    'widgets/embed-video',
    'widgets/chartjs',
    'widgets/lottie',
    /* "widgets/facebookpage", */ 'widgets/paypal',
    /*"widgets/instagram",*/ 'widgets/twitter',
    'widgets/openstreetmap' /*, "widgets/facebookcomments"*/,
];

Astero.Components.extend('_base', 'widgets/googlemaps', {
    name: 'Google Maps',
    attributes: ['data-component-maps'],
    icon: 'ri-map-pin-line',
    dragHtml: '<div class="text-center p-3"><i class="ri-map-pin-line display-4 text-muted"></i></div>',
    html: '<div data-component-maps><iframe frameborder="0" src="https://maps.google.com/maps?q=Bucharest&z=15&t=q&key=&output=embed" width="100%" height="100%" style="width:100%;height:100%;left:0px"></iframe></div>',
    resizable: true, //show select box resize handlers
    resizeMode: 'css',

    //url parameters
    z: 3, //zoom
    q: 'Paris', //location
    t: 'q', //map type q = roadmap, w = satellite
    key: '',

    init: function (node) {
        const iframe = node.querySelector('iframe');
        if (!iframe) return;

        const url = new URL(iframe.getAttribute('src'));
        const params = new URLSearchParams(url.search);

        this.z = params.get('z');
        this.q = params.get('q');
        this.t = params.get('t');
        this.key = params.get('key');

        const zoomInput = document.querySelector('.component-properties input[name=z]');
        if (zoomInput) zoomInput.value = this.z || '';
        const addressInput = document.querySelector('.component-properties input[name=q]');
        if (addressInput) addressInput.value = this.q || '';
        const typeSelect = document.querySelector('.component-properties select[name=t]');
        if (typeSelect) typeSelect.value = this.t || '';
        const keyInput = document.querySelector('.component-properties input[name=key]');
        if (keyInput) keyInput.value = this.key || '';
    },

    onChange: function (node, property, value, input, event) {
        const mapIframe = node.querySelector('iframe');
        if (!mapIframe) return node;

        this[property.key] = value;

        const mapUrl = 'https://maps.google.com/maps?q=' + this.q + '&z=' + this.z + '&t=' + this.t + '&output=embed';

        mapIframe.setAttribute('src', mapUrl);

        return node;
    },

    properties: [
        {
            name: 'Address',
            key: 'q',
            inputtype: TextInput,
        },
        {
            name: 'Map type',
            key: 't',
            inputtype: SelectInput,
            data: {
                options: [
                    {
                        value: 'q',
                        text: 'Roadmap',
                    },
                    {
                        value: 'w',
                        text: 'Satellite',
                    },
                ],
            },
        },
        {
            name: 'Zoom',
            key: 'z',
            inputtype: RangeInput,
            data: {
                max: 20, //max zoom level
                min: 1,
                step: 1,
            },
        },
        {
            name: 'Key',
            key: 'key',
            inputtype: TextInput,
        },
    ],
});

Astero.Components.extend('_base', 'widgets/openstreetmap', {
    name: 'Open Street Map',
    attributes: ['data-component-openstreetmap'],
    icon: 'ri-map-pin-line',
    dragHtml: '<div class="text-center p-3"><i class="ri-map-pin-line display-4 text-muted"></i></div>',
    html: `<div data-component-openstreetmap><iframe width="100%" height="100%" frameborder="0" scrolling="no" marginheight="0" marginwidth="0" src="https://www.openstreetmap.org/export/embed.html?bbox=-62.04673002474011%2C16.95487694424327%2C-61.60521696321666%2C17.196751341562923&layer=mapnik"></iframe></div>`,
    resizable: true, //show select box resize handlers
    resizeMode: 'css',

    //url parameters
    bbox: '', //location
    layer: 'mapnik', //map type

    init: function (node) {
        const iframe = node.querySelector('iframe');
        if (!iframe) return;

        const url = new URL(iframe.getAttribute('src'));
        const params = new URLSearchParams(url.search);

        this.bbox = params.get('bbox');
        this.layer = params.get('layer');

        const bboxInput = document.querySelector('.component-properties input[name=bbox]');
        if (bboxInput) bboxInput.value = this.bbox || '';
        const layerInput = document.querySelector('.component-properties input[name=layer]');
        if (layerInput) layerInput.value = this.layer || '';
    },

    onChange: function (node, property, value, input, event) {
        const mapIframe = node.querySelector('iframe');
        if (!mapIframe) return node;

        this[property.key] = value;

        const mapUrl = 'https://www.openstreetmap.org/export/embed.html?bbox=' + this.bbox + '&layer=' + this.layer;

        mapIframe.setAttribute('src', mapUrl);

        return node;
    },

    properties: [
        {
            name: 'Map',
            key: 'bbox',
            inputtype: TextInput,
            /*    },{
        name: "Layer",
        key: "layer",
        inputtype: SelectInput,
        data:{
			options: [{
                value: "",
                text: "Default"
            },{
                value: "Y",
                text: "CyclOSM"
            },{
                value: "C",
                text: "Cycle Map"
            },{
                value: "T",
                text: "Transport Map"
            }]
       }*/
        },
    ],
});

Astero.Components.extend('_base', 'widgets/embed-video', {
    name: 'Embed Video',
    attributes: ['data-component-video'],
    icon: 'ri-youtube-line',
    dragHtml: '<div class="text-center p-3"><i class="ri-video-line display-4 text-muted"></i></div>', //use image for drag and swap with iframe on drop for drag performance
    html: '<div data-component-video style="width:640px;height:480px;"><iframe frameborder="0" src="https://player.vimeo.com/video/24253126?autoplay=false&controls=false&loop=false&playsinline=true&muted=false" width="100%" height="100%"></iframe></div>',

    //url parameters set with onChange
    t: 'y', //video type
    video_id: '', //video id
    url: '', //html5 video src
    autoplay: false,
    controls: false,
    loop: false,
    playsinline: true,
    muted: false,
    resizable: true, //show select box resize handlers
    resizeMode: 'css', //div unlike img/iframe etc does not have width,height attributes need to use css
    youtubeRegex: /(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]+)/i,
    vimeoRegex: /(?:vimeo\.com(?:[^\d]+))(\d+)/i,

    init: function (node) {
        const iframe = node.querySelector('iframe');
        const video = node.querySelector('video');

        const urlField = document.querySelector('.component-properties [data-key=url]');
        if (urlField?.style) urlField.style.display = 'none';
        const posterField = document.querySelector('.component-properties [data-key=poster]');
        if (posterField?.style) posterField.style.display = 'none';

        //check if html5
        if (video) {
            this.url = video.src;
        } else if (iframe) {
            //vimeo or youtube
            let src = iframe.getAttribute('src');
            let match;

            if (src && src.indexOf('youtube') !== -1 && (match = src.match(this.youtubeRegex))) {
                //youtube
                this.video_id = match[1];
                this.t = 'y';
            } else if (src && src.indexOf('vimeo') !== -1 && (match = src.match(this.vimeoRegex))) {
                //vimeo
                this.video_id = match[1];
                this.t = 'v';
            } else {
                this.t = 'h';
            }
        }

        const idInput = document.querySelector('.component-properties input[name=video_id]');
        if (idInput) idInput.value = this.video_id || '';
        const urlInput = document.querySelector('.component-properties input[name=url]');
        if (urlInput) urlInput.value = this.url || '';
        const typeSelect = document.querySelector('.component-properties select[name=t]');
        if (typeSelect) typeSelect.value = this.t || '';
    },

    onChange: function (node, property, value, input, event) {
        this[property.key] = value;
        let newNode = null;
        const videoIdField = document.querySelector('.component-properties [data-key=video_id]');
        const urlField = document.querySelector('.component-properties [data-key=url]');
        const posterField = document.querySelector('.component-properties [data-key=poster]');

        switch (this.t) {
            case 'y':
                if (videoIdField?.style) videoIdField.style.display = '';
                if (urlField?.style) urlField.style.display = 'none';
                if (posterField?.style) posterField.style.display = 'none';

                newNode =
                    generateElements(`<iframe width="100%" height="100%" allowfullscreen="true" frameborder="0" allow="autoplay"
										src="https://www.youtube.com/embed/${this.video_id}?autoplay=${this.autoplay}&controls=${this.controls}&loop=${this.loop}&playsinline=${this.playsinline}&muted=${this.muted}">
								</iframe>`)[0];
                break;
            case 'v':
                if (videoIdField?.style) videoIdField.style.display = '';
                if (urlField?.style) urlField.style.display = 'none';
                if (posterField?.style) posterField.style.display = 'none';
                newNode =
                    generateElements(`<iframe width="100%" height="100%" allowfullscreen="true" frameborder="0" allow="autoplay"
										src="https://player.vimeo.com/video/${this.video_id}?autoplay=${this.autoplay}&controls=${this.controls}&loop=${this.loop}&playsinline=${this.playsinline}&muted=${this.muted}">
								</iframe>`)[0];
                break;
            case 'h':
                if (videoIdField?.style) videoIdField.style.display = 'none';
                if (urlField?.style) urlField.style.display = '';
                if (posterField?.style) posterField.style.display = '';
                newNode = generateElements(
                    '<video poster="' +
                        this.poster +
                        '" src="' +
                        this.url +
                        '" ' +
                        (this.autoplay ? ' autoplay ' : '') +
                        (this.controls ? ' controls ' : '') +
                        (this.loop ? ' loop ' : '') +
                        (this.playsinline ? ' playsinline ' : '') +
                        (this.muted ? ' muted ' : '') +
                        ' style="height: 100%; width: 100%;"></video>'
                )[0];
                break;
        }

        const oldNode = node.querySelector(':scope > iframe,:scope  > video');
        if (oldNode && newNode) {
            oldNode.replaceWith(newNode);
        }

        return node;
    },

    properties: [
        {
            name: 'Provider',
            key: 't',
            inputtype: SelectInput,
            data: {
                options: [
                    {
                        text: 'Youtube',
                        value: 'y',
                    },
                    {
                        text: 'Vimeo',
                        value: 'v',
                    },
                    {
                        text: 'HTML5',
                        value: 'h',
                    },
                ],
            },
        },
        {
            name: 'Video',
            key: 'video_id',
            inputtype: TextInput,
            onChange: function (node, value, input, component) {
                let youtube =
                    /(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]+)/i;
                let vimeo = /(?:vimeo\.com(?:[^\d]+))(\d+)/i;
                let id = false;
                let t = false;

                if (((id = value.match(youtube)) && (t = 'y')) || ((id = value.match(vimeo)) && (t = 'v'))) {
                    const typeSelect = document.querySelector('.component-properties select[name=t]');
                    if (typeSelect) typeSelect.value = t;
                    const idInput = document.querySelector('.component-properties input[name=video_id]');
                    if (idInput) idInput.value = id[1];

                    component.t = t;
                    component.video_id = id[1];

                    return id[1];
                }

                return node;
            },
        },
        {
            name: 'Poster',
            key: 'poster',
            htmlAttr: 'poster',
            inputtype: ImageInput,
        },
        {
            name: 'Url',
            key: 'url',
            inputtype: TextInput,
        },
        {
            name: 'Width',
            key: 'width',
            htmlAttr: 'style',
            inline: false,
            col: 6,
            inputtype: CssUnitInput,
        },
        {
            name: 'Height',
            key: 'height',
            htmlAttr: 'style',
            inline: false,
            col: 6,
            inputtype: CssUnitInput,
        },
        {
            key: 'video_options',
            inputtype: SectionInput,
            name: false,
            data: { header: 'Options' },
        },
        {
            name: 'Auto play',
            key: 'autoplay',
            htmlAttr: 'autoplay',
            inline: true,
            col: 4,
            inputtype: CheckboxInput,
        },
        {
            name: 'Plays inline',
            key: 'playsinline',
            htmlAttr: 'playsinline',
            inline: true,
            col: 4,
            inputtype: CheckboxInput,
        },
        {
            name: 'Controls',
            key: 'controls',
            htmlAttr: 'controls',
            inline: true,
            col: 4,
            inputtype: CheckboxInput,
        },
        {
            name: 'Loop',
            key: 'loop',
            htmlAttr: 'loop',
            inline: true,
            col: 4,
            inputtype: CheckboxInput,
        },
        {
            name: 'Muted',
            key: 'muted',
            htmlAttr: 'muted',
            inline: true,
            col: 4,
            inputtype: CheckboxInput,
        },
        {
            name: '',
            key: 'autoplay_warning',
            inline: false,
            col: 12,
            inputtype: NoticeInput,
            data: {
                type: 'warning',
                title: 'Autoplay',
                text: 'Most browsers allow auto play only if video is muted and plays inline',
            },
        },
    ],
});

Astero.Components.extend('_base', 'widgets/facebookcomments', {
    name: 'Facebook Comments',
    attributes: ['data-component-facebookcomments'],
    icon: 'ri-facebook-line',
    dragHtml: '<div class="text-center p-3"><i class="ri-discuss-line display-4 text-muted"></i></div>',
    html:
        '<div  data-component-facebookcomments><script>(function(d, s, id) {\
			  let js, fjs = d.getElementsByTagName(s)[0];\
			  if (d.getElementById(id)) return;\
			  js = d.createElement(s); js.id = id;\
			  js.src = "//connect.facebook.net/en_US/sdk.js#xfbml=1&version=v2.6&appId=";\
			  fjs.parentNode.insertBefore(js, fjs);\
			}(document, \'script\', \'facebook-jssdk\'));</script>\
			<div class="fb-comments" \
			data-href="' +
        window.location.href +
        '" \
			data-numposts="5" \
			data-colorscheme="light" \
			data-mobile="" \
			data-order-by="social" \
			data-width="100%" \
			></div></div>',
    properties: [
        {
            name: 'Href',
            key: 'business',
            htmlAttr: 'data-href',
            child: '.fb-comments',
            inputtype: TextInput,
        },
        {
            name: 'Item name',
            key: 'item_name',
            htmlAttr: 'data-numposts',
            child: '.fb-comments',
            inputtype: TextInput,
        },
        {
            name: 'Color scheme',
            key: 'colorscheme',
            htmlAttr: 'data-colorscheme',
            child: '.fb-comments',
            inputtype: TextInput,
        },
        {
            name: 'Order by',
            key: 'order-by',
            htmlAttr: 'data-order-by',
            child: '.fb-comments',
            inputtype: TextInput,
        },
        {
            name: 'Currency code',
            key: 'width',
            htmlAttr: 'data-width',
            child: '.fb-comments',
            inputtype: TextInput,
        },
    ],
});
/*
Astero.Components.extend("_base", "widgets/instagram", {
    name: "Instagram",
    attributes: ["data-component-instagram"],
    image: "icons/instagram.svg",
    drophtml: '<img src="' + Astero.baseUrl + 'icons/instagram.png">',
    html: '<div align=center data-component-instagram>\
			<blockquote class="instagram-media" data-instgrm-captioned data-instgrm-permalink="https://www.instagram.com/p/tsxp1hhQTG/" data-instgrm-version="8" style=" background:#FFF; border:0; border-radius:3px; box-shadow:0 0 1px 0 rgba(0,0,0,0.5),0 1px 10px 0 rgba(0,0,0,0.15); margin: 1px; max-width:658px; padding:0; width:99.375%; width:-webkit-calc(100% - 2px); width:calc(100% - 2px);"><div style="padding:8px;"> <div style=" background:#F8F8F8; line-height:0; margin-top:40px; padding:50% 0; text-align:center; width:100%;"> <div style=" background:url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAACwAAAAsCAMAAAApWqozAAAABGdBTUEAALGPC/xhBQAAAAFzUkdCAK7OHOkAAAAMUExURczMzPf399fX1+bm5mzY9AMAAADiSURBVDjLvZXbEsMgCES5/P8/t9FuRVCRmU73JWlzosgSIIZURCjo/ad+EQJJB4Hv8BFt+IDpQoCx1wjOSBFhh2XssxEIYn3ulI/6MNReE07UIWJEv8UEOWDS88LY97kqyTliJKKtuYBbruAyVh5wOHiXmpi5we58Ek028czwyuQdLKPG1Bkb4NnM+VeAnfHqn1k4+GPT6uGQcvu2h2OVuIf/gWUFyy8OWEpdyZSa3aVCqpVoVvzZZ2VTnn2wU8qzVjDDetO90GSy9mVLqtgYSy231MxrY6I2gGqjrTY0L8fxCxfCBbhWrsYYAAAAAElFTkSuQmCC); display:block; height:44px; margin:0 auto -44px; position:relative; top:-22px; width:44px;"></div></div> <p style=" margin:8px 0 0 0; padding:0 4px;"> <a href="https://www.instagram.com/p/tsxp1hhQTG/" style=" color:#000; font-family:Arial,sans-serif; font-size:14px; font-style:normal; font-weight:normal; line-height:17px; text-decoration:none; word-wrap:break-word;" target="_blank">Text</a></p> <p style=" color:#c9c8cd; font-family:Arial,sans-serif; font-size:14px; line-height:17px; margin-bottom:0; margin-top:8px; overflow:hidden; padding:8px 0 7px; text-align:center; text-overflow:ellipsis; white-space:nowrap;">A post shared by <a href="https://www.instagram.com/instagram/" style=" color:#c9c8cd; font-family:Arial,sans-serif; font-size:14px; font-style:normal; font-weight:normal; line-height:17px;" target="_blank"> Instagram</a> (@instagram) on <time style=" font-family:Arial,sans-serif; font-size:14px; line-height:17px;" datetime="-">-</time></p></div></blockquote>\
			<script async defer src="//www.instagram.com/embed.js"></script>\
		</div>',
    properties: [{
        name: "Widget id",
        key: "instgrm-permalink",
        htmlAttr: "data-instgrm-permalink",
        child: ".instagram-media",
        inputtype: TextInput
    }],
});
*/
Astero.Components.extend('_base', 'widgets/twitter', {
    name: 'Twitter',
    attributes: ['data-component-twitter'],
    icon: 'ri-twitter-x-line',
    dragHtml: '<div class="text-center p-3"><i class="ri-twitter-x-line display-4 text-muted"></i></div>',
    html: '<div data-component-twitter><iframe width="100%" height="100%"src="https://platform.twitter.com/embed/Tweet.html?embedId=twitter-widget-0&frame=false&hideCard=false&hideThread=false&id=943901463998169088"></iframe></div>',
    resizable: true, //show select box resize handlers
    resizeMode: 'css',
    twitterRegex: /(?:twitter\.com(?:[^\d]+))(\d+)/i,

    tweet: '', //location
    init: function (node) {
        let iframe = node.querySelector('iframe');
        let src = iframe.getAttribute('src');
        let url = new URL(src);
        let params = new URLSearchParams(url.search);

        this.tweet = params.get('id');

        if (!this.tweet) {
            let match;
            if ((match = src.match(this.twitterRegex))) {
                this.tweet = match[1];
            }
        }

        const tweetInput = document.querySelector('.component-properties input[name=tweet]');
        if (tweetInput) tweetInput.value = this.tweet || '';
    },

    onChange: function (node, property, value, input, event) {
        const tweetIframe = node.querySelector('iframe');
        if (!tweetIframe) return node;

        if (property.key == 'tweet') {
            this[property.key] = value;

            const tweetUrl =
                'https://platform.twitter.com/embed/Tweet.html?embedId=twitter-widget-0&frame=false&hideCard=false&hideThread=false&id=' +
                this.tweet;

            tweetIframe.setAttribute('src', tweetUrl);
        }

        return node;
    },

    properties: [
        {
            name: 'Tweet',
            key: 'tweet',
            inputtype: TextInput,
            onChange: function (node, value, input, component) {
                let twitterRegex = /(?:twitter\.com(?:[^\d]+))(\d+)/i;
                let id = false;

                if ((id = value.match(twitterRegex))) {
                    const tweetInput = document.querySelector('.component-properties input[name=tweet]');
                    if (tweetInput) tweetInput.value = id[1];

                    component.tweet = id[1];
                    return id[1];
                }

                return node;
            },
        },
    ],
});

Astero.Components.extend('_base', 'widgets/paypal', {
    name: 'Paypal',
    attributes: ['data-component-paypal'],
    icon: 'ri-paypal-line',
    html: '<form action="https://www.paypal.com/cgi-bin/webscr" method="post" data-component-paypal>\
\
				<!-- Identify your business so that you can collect the payments. -->\
				<input type="hidden" name="business"\
					value="givanz@yahoo.com">\
\
				<!-- Specify a Donate button. -->\
				<input type="hidden" name="cmd" value="_donations">\
\
				<!-- Specify details about the contribution -->\
				<input type="hidden" name="item_name" value="Astero">\
				<input type="hidden" name="item_number" value="Support">\
				<input type="hidden" name="currency_code" value="USD">\
\
				<!-- Display the payment button. -->\
				<input type="image" name="submit"\
				src="https://www.paypalobjects.com/en_US/i/btn/btn_donate_LG.gif"\
				alt="Donate">\
				<img alt="" width="1" height="1"\
				src="https://www.paypalobjects.com/en_US/i/scr/pixel.gif" >\
\
			</form>',
    properties: [
        {
            name: 'Email',
            key: 'business',
            htmlAttr: 'value',
            child: "input[name='business']",
            inputtype: TextInput,
        },
        {
            name: 'Item name',
            key: 'item_name',
            htmlAttr: 'value',
            child: "input[name='item_name']",
            inputtype: TextInput,
        },
        {
            name: 'Item number',
            key: 'item_number',
            htmlAttr: 'value',
            child: "input[name='item_number']",
            inputtype: TextInput,
        },
        {
            name: 'Currency code',
            key: 'currency_code',
            htmlAttr: 'value',
            child: "input[name='currency_code']",
            inputtype: TextInput,
        },
    ],
});

Astero.Components.extend('_base', 'widgets/facebookpage', {
    name: 'Facebook Page Plugin',
    attributes: ['data-component-facebookpage'],
    icon: 'ri-facebook-line',
    dropHtml: '<div class="text-center p-3"><i class="ri-facebook-box-line display-4 text-muted"></i></div>',
    html: `<div data-component-facebookpage><div class="fb-page"
			 data-href="https://www.facebook.com/facebook"
			 data-tabs="timeline"
			 data-width=""
			 data-height=""
			 data-small-header="true"
			 data-adapt-container-width="true"
			 data-hide-cover="false"
			 data-show-facepile="true">

				<blockquote cite="https://www.facebook.com/facebook" class="fb-xfbml-parse-ignore">
					<a href="https://www.facebook.com/facebook">Facebook</a>
				</blockquote>

			</div>

			<div id="fb-root"></div>
			<script async defer crossorigin="anonymous" src="https://connect.facebook.net/ro_RO/sdk.js#xfbml=1&version=v15.0" nonce="o7Y7zPjy"></script>
		</div>`,

    properties: [
        {
            name: 'Small header',
            key: 'small-header',
            htmlAttr: 'data-small-header',
            child: '.fb-page',
            inputtype: TextInput,
        },
        {
            name: 'Adapt container width',
            key: 'adapt-container-width',
            htmlAttr: 'data-adapt-container-width',
            child: '.fb-page',
            inputtype: TextInput,
        },
        {
            name: 'Hide cover',
            key: 'hide-cover',
            htmlAttr: 'data-hide-cover',
            child: '.fb-page',
            inputtype: TextInput,
        },
        {
            name: 'Show facepile',
            key: 'show-facepile',
            htmlAttr: 'data-show-facepile',
            child: '.fb-page',
            inputtype: TextInput,
        },
        {
            name: 'App Id',
            key: 'appid',
            htmlAttr: 'data-appId',
            child: '.fb-page',
            inputtype: TextInput,
        },
    ],
    onChange: function (node, property, value, input, event) {
        let newElement = generateElements(this.html)[0];
        let fbPage = newElement.querySelector('.fb-page');
        if (fbPage) {
            fbPage.setAttribute(property.htmlAttr, value);
        }

        const frameDoc = window.FrameDocument;
        const frameHead = frameDoc?.head;
        const frameBody = frameDoc?.body;

        frameHead?.querySelector('[data-fbcssmodules]')?.remove();
        frameBody?.querySelector('[data-fbcssmodules]')?.remove();
        frameHead?.querySelector("script[src^='https://connect.facebook.net']")?.remove();

        const parent = node?.parentElement;
        if (parent) {
            parent.innerHTML = newElement.innerHTML;
            return parent.querySelector('.fb-page') || node;
        }
        return node;
    },
});

Astero.Components.extend('_base', 'widgets/chartjs', {
    name: 'Chart.js',
    attributes: ['data-component-chartjs'],
    icon: 'ri-bar-chart-line',
    dragHtml: '<div class="text-center p-3"><i class="ri-bar-chart-line display-4 text-muted"></i></div>',
    html: '<div data-component-chartjs class="chartjs" data-chart=\'{\
			"type": "line",\
			"data": {\
				"labels": ["Red", "Blue", "Yellow", "Green", "Purple", "Orange"],\
				"datasets": [{\
					"data": [12, 19, 3, 5, 2, 3],\
					"fill": false,\
					"borderColor":"rgba(255, 99, 132, 0.2)"\
				},{\
					"fill": false,\
					"data": [3, 15, 7, 4, 19, 12],\
					"borderColor": "rgba(54, 162, 235, 0.2)"\
				}]\
			}}\' style="min-height:240px;min-width:240px;width:100%;height:100%;position:relative">\
			  <canvas></canvas>\
			</div>',
    chartjs: null,
    ctx: null,
    node: null,

    config: {
        /*
			type: 'line',
			data: {
				labels: ["Red", "Blue", "Yellow", "Green", "Purple", "Orange"],
				datasets: [{
					data: [12, 19, 3, 5, 2, 3],
					fill: false,
					borderColor:'rgba(255, 99, 132, 0.2)',
				},{
					fill: false,
					data: [3, 15, 7, 4, 19, 12],
					borderColor: 'rgba(54, 162, 235, 0.2)',
				}]
			},*/
    },

    dragStart: function (node) {
        //check if chartjs is included and if not add it when drag starts to allow the script to load
        const body = Astero.Builder.frameBody;
        if (!body) return node;
        const frameDoc = body.ownerDocument;

        if (!body.querySelector('#chartjs-script')) {
            const lib = frameDoc.createElement('script');
            const code = frameDoc.createElement('script');

            lib.id = 'chartjs-script';
            lib.src = 'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.7.2/Chart.bundle.min.js';
            code.text = `
                function initCharts(onlyNew = false) {
                    if (typeof Chart === 'undefined') return;

                    const selector = onlyNew ? '.chartjs:not([data-chartjs-initialized])' : '.chartjs';
                    document.querySelectorAll(selector).forEach((el) => {
                        const canvas = el.querySelector('canvas');
                        if (!canvas) return;

                        const ctx = canvas.getContext('2d');
                        const config = JSON.parse(el.dataset.chart || '{}');
                        if (el._chartInstance) {
                            el._chartInstance.destroy();
                        }
                        el._chartInstance = new Chart(ctx, config);
                        el.setAttribute('data-chartjs-initialized', 'true');
                    });
                }

                if (document.readyState !== 'loading') {
                    initCharts();
                } else {
                    document.addEventListener('DOMContentLoaded', () => initCharts());
                }`;

            body.appendChild(lib);
            body.appendChild(code);

            lib.addEventListener('load', function () {
                Astero.Builder.iframe.contentWindow.initCharts?.(true);
            });
        } else {
            Astero.Builder.iframe.contentWindow.initCharts?.(true);
        }

        return node;
    },

    drawChart: function () {
        if (this.chartjs != null) this.chartjs.destroy();
        this.node.dataset.chart = JSON.stringify(this.config);

        const config = Object.assign({}, this.config); //avoid passing by reference to avoid chartjs to fill the object
        this.chartjs = new Chart(this.ctx, config);
    },

    init: function (node) {
        this.node = node;
        this.ctx = node.querySelector('canvas').getContext('2d');
        this.config = JSON.parse(node.dataset.chart);
        this.drawChart();

        return node;
    },

    beforeInit: function (node) {
        return node;
    },

    properties: [
        {
            name: 'Type',
            key: 'type',
            inputtype: SelectInput,
            data: {
                options: [
                    {
                        text: 'Line',
                        value: 'line',
                    },
                    {
                        text: 'Bar',
                        value: 'bar',
                    },
                    {
                        text: 'Pie',
                        value: 'pie',
                    },
                    {
                        text: 'Doughnut',
                        value: 'doughnut',
                    },
                    {
                        text: 'Polar Area',
                        value: 'polarArea',
                    },
                    {
                        text: 'Bubble',
                        value: 'bubble',
                    },
                    {
                        text: 'Scatter',
                        value: 'scatter',
                    },
                    {
                        text: 'Radar',
                        value: 'radar',
                    },
                ],
            },
            init: function (node) {
                return JSON.parse(node.dataset.chart).type;
            },
            onChange: function (node, value, input, component) {
                component.config.type = value;
                component.drawChart();

                return node;
            },
        },
    ],
});

function lottieAfterDrop(node) {
    //check if lottie js is included and if not add it when drag starts to allow the script to load
    const body = Astero.Builder.frameBody;

    if (!body.querySelector('#lottie-js')) {
        const frameDoc = body.ownerDocument;
        let lib = frameDoc.createElement('script');
        let code = frameDoc.createElement('script');
        lib.id = 'lottie-js';
        lib.type = 'text/javascript';
        lib.src = 'https://cdnjs.cloudflare.com/ajax/libs/bodymovin/5.12.2/lottie.min.js';
        code.type = 'text/javascript';
        code.text = `
		let lottie = [];
		function initLottie(onlyNew = false) {
			if (typeof bodymovin == "undefined") return;


			let list = document.querySelectorAll('.lottie' + (onlyNew ? ":not(.lottie-initialized)" : "") );
			list.forEach(el => {
				el.replaceChildren();
				let animItem = bodymovin.loadAnimation({
				  wrapper: el,
				  animType: 'svg',
				  loop: (el.dataset.loop  == "true" ? true : false),
				  autoplay: (el.dataset.autoplay == "true" ? true : false),
				  path: el.dataset.path
				});

			});
		}

		if (document.readyState !== 'loading') {
			initLottie();
		  } else {
			document.addEventListener('DOMContentLoaded', initLottie);
		  }`;

        body.appendChild(lib);
        body.appendChild(code);

        lib.addEventListener('load', function () {
            Astero.Builder.iframe.contentWindow.initLottie();
        });
    } else {
        Astero.Builder.iframe.contentWindow.initLottie(true);
    }

    return node;
}

Astero.Components.add('widgets/lottie', {
    name: 'Lottie',
    icon: 'ri-movie-line',
    attributes: ['data-component-lottie'],
    html: `
	  <div class="lottie" data-component-lottie data-path="https://labs.nearpod.com/bodymovin/demo/markus/isometric/markus2.json" data-loop="true" data-autoplay="true">
	  </div>
	`,
    afterDrop: lottieAfterDrop,

    onChange: function (node, property, value, input, event) {
        Astero.Builder.iframe.contentWindow.initLottie?.();
        Astero.Builder.selectNode(node);
        return node;
    },

    properties: [
        {
            name: 'Path',
            key: 'path',
            //inputtype: ImageInput,
            inputtype: TextInput,
            htmlAttr: 'data-path',
        },
        {
            name: 'Autoplay',
            key: 'autoplay',
            htmlAttr: 'data-autoplay',
            inputtype: CheckboxInput,
            inline: true,
            col: 4,
        },
        { name: 'Loop', key: 'loop', htmlAttr: 'data-loop', inputtype: CheckboxInput, inline: true, col: 4 },
    ],
});
