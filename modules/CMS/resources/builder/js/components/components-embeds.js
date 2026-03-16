Astero.ComponentsGroup['Embeds'] = ['embeds/embed'];

Astero.Components.extend('_base', 'embeds/embed', {
    name: 'Embed',
    attributes: ['data-component-oembed'],
    icon: 'ri-code-s-slash-line',
    //dragHtml: '<img src="' + Astero.baseUrl + 'icons/maps.png">',
    html: `<div data-component-oembed data-url="">
			<div class="alert alert-light  m-5" role="alert">
				<i class="ri-code-s-slash-line ri-4x"></i>
				<h6>Enter url to embed</h6>
			</div></div>`,

    properties: [
        {
            name: 'Url',
            key: 'url',
            htmlAttr: 'data-url',
            inputtype: TextInput,
            onChange: function (node, value) {
                node.innerHTML = `<div class="alert alert-light d-flex justify-content-center">
				  <div class="spinner-border m-5" role="status">
					<span class="visually-hidden">Loading...</span>
				  </div>
				</div>`;

                getOembed(value)
                    .then((response) => {
                        node.innerHTML = response.html;
                        let containerW = node.offsetWidth;
                        let iframe = node.querySelector('iframe');
                        if (iframe) {
                            let ratio = containerW / iframe.offsetWidth;
                            iframe.setAttribute('width', width * ratio);
                            iframe.setAttribute('height', height * ratio);
                        }

                        let arr = node.querySelectorAll('script').forEach((script) => {
                            let newScript = Astero.Builder.frameDoc.createElement('script');
                            newScript.src = script.src;
                            script.replaceWith(newScript);
                        });
                    })
                    .catch((error) => console.log(error));

                return node;
            },
        },
        {
            name: 'Width',
            key: 'width',
            child: 'iframe',
            htmlAttr: 'width',
            inputtype: CssUnitInput,
        },
        {
            name: 'Height',
            key: 'height',
            child: 'iframe',
            htmlAttr: 'height',
            inputtype: CssUnitInput,
        },
    ],
});

for (const provider of [
    'youtube',
    'vimeo',
    'dailymotion',
    'flickr',
    'smugmug',
    'scribd',
    'twitter',
    'soundcloud',
    'slideshare',
    'spotify',
    'imgur',
    'issuu',
    'mixcloud',
    'ted',
    'animoto',
    'tumblr',
    'kickstarter',
    'reverbnation',
    'reddit',
    'speakerdeck',
    'screencast',
    'amazon',
    'someecards',
    'tiktok',
    'pinterest',
    'wolfram',
    'anghami',
]) {
    Astero.Components.add('embeds/' + provider, {
        name: provider,
        icon: 'ri-code-s-slash-line',
        html: `<div data-component-oembed data-url="">
				<div class="alert alert-light  m-5" role="alert">
					<i class="ri-code-s-slash-line ri-4x"></i>
					<h6>Enter ${provider} url to embed</h6>
				</div></div>`,
    });
    Astero.ComponentsGroup['Embeds'].push('embeds/' + provider);
}
