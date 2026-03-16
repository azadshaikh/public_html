Astero.ComponentsGroup['Server Components'] = [
    'components/products',
    'components/product',
    'components/categories',
    'components/manufacturers',
    'components/search',
    'components/user',
    'components/product_gallery',
    'components/cart',
    'components/checkout',
    'components/filters',
    'components/product',
    'components/slider',
];

Astero.Components.add('components/product', {
    name: 'Product',
    attributes: ['data-component-product'],

    icon: 'ri-map-pin-line',
    html: '<iframe frameborder="0" src="https://maps.google.com/maps?&z=1&t=q&output=embed"></iframe>',

    properties: [
        {
            name: 'Id',
            key: 'id',
            htmlAttr: 'id',
            inputtype: TextInput,
        },
        {
            name: 'Select',
            key: 'id',
            htmlAttr: 'id',
            inputtype: SelectInput,
            data: {
                options: [
                    {
                        value: '',
                        text: 'None',
                    },
                    {
                        value: 'pull-left',
                        text: 'Left',
                    },
                    {
                        value: 'pull-right',
                        text: 'Right',
                    },
                ],
            },
        },
        {
            name: 'Select 2',
            key: 'id',
            htmlAttr: 'id',
            inputtype: SelectInput,
            data: {
                options: [
                    {
                        value: '',
                        text: 'nimic',
                    },
                    {
                        value: 'pull-left',
                        text: 'gigi',
                    },
                    {
                        value: 'pull-right',
                        text: 'vasile',
                    },
                    {
                        value: 'pull-right',
                        text: 'sad34',
                    },
                ],
            },
        },
    ],
});

Astero.Components.add('components/products', {
    name: 'Products',
    attributes: ['data-component-products'],

    icon: 'ri-shopping-bag-3-line',
    html: '<div class="mb-3"><label>Your response:</label><textarea class="form-control"></textarea></div>',

    toggleGroupVisibility: function (group) {
        document.querySelectorAll('.mb-3[data-group]').forEach((el) => {
            el.style.display = 'none';
        });

        if (group) {
            document.querySelectorAll('.mb-3[data-group="' + group + '"]').forEach((el) => {
                el.style.display = '';
            });
            return;
        }

        const firstGroup = document.querySelector('.mb-3[data-group]');
        if (firstGroup) {
            firstGroup.style.display = '';
        }
    },
    init: function (node) {
        const group = node?.dataset?.type;
        this.toggleGroupVisibility(group);
    },
    properties: [
        {
            name: false,
            key: 'type',
            inputtype: RadioButtonInput,
            htmlAttr: 'data-type',
            data: {
                inline: true,
                extraclass: 'btn-group-fullwidth',
                options: [
                    {
                        value: 'autocomplete',
                        text: 'Autocomplete',
                        title: 'Autocomplete',
                        icon: 'ri-search-line',
                        checked: true,
                    },
                    {
                        value: 'automatic',
                        icon: 'ri-settings-3-line',
                        text: 'Configuration',
                        title: 'Configuration',
                    },
                ],
            },
            onChange: function (element, value, input, component) {
                component?.toggleGroupVisibility(input.value);

                return element;
            },
            init: function (node) {
                return node.dataset.type;
            },
        },
        {
            name: 'Products',
            key: 'products',
            group: 'autocomplete',
            htmlAttr: 'data-products',
            inputtype: AutocompleteList,
            data: {
                url: '/admin/?module=editor/editor&action=productsAutocomplete',
            },
        },
        {
            name: 'Number of products',
            group: 'automatic',
            key: 'limit',
            htmlAttr: 'data-limit',
            inputtype: NumberInput,
            data: {
                value: '8', //default
                min: '1',
                max: '1024',
                step: '1',
            },
            getFromNode: function (node) {
                return 10;
            },
        },
        {
            name: 'Start from page',
            group: 'automatic',
            key: 'page',
            htmlAttr: 'data-page',
            data: {
                value: '1', //default
                min: '1',
                max: '1024',
                step: '1',
            },
            inputtype: NumberInput,
            getFromNode: function (node) {
                return 0;
            },
        },
        {
            name: 'Order by',
            group: 'automatic',
            key: 'order',
            htmlAttr: 'data-order',
            inputtype: SelectInput,
            data: {
                options: [
                    {
                        value: 'price_asc',
                        text: 'Price Ascending',
                    },
                    {
                        value: 'price_desc',
                        text: 'Price Descending',
                    },
                    {
                        value: 'date_asc',
                        text: 'Date Ascending',
                    },
                    {
                        value: 'date_desc',
                        text: 'Date Descending',
                    },
                    {
                        value: 'sales_asc',
                        text: 'Sales Ascending',
                    },
                    {
                        value: 'sales_desc',
                        text: 'Sales Descending',
                    },
                ],
            },
        },
        {
            name: 'Category',
            group: 'automatic',
            key: 'category',
            htmlAttr: 'data-category',
            inputtype: AutocompleteList,
            data: {
                url: '/admin/?module=editor/editor&action=productsAutocomplete',
            },
        },
        {
            name: 'Manufacturer',
            group: 'automatic',
            key: 'manufacturer',
            htmlAttr: 'data-manufacturer',
            inputtype: AutocompleteList,
            data: {
                url: '/admin/?module=editor/editor&action=productsAutocomplete',
            },
        },
        {
            name: 'Manufacturer 2',
            group: 'automatic',
            key: 'manufacturer 2',
            htmlAttr: 'data-manufacturer2',
            inputtype: AutocompleteList,
            data: {
                url: '/admin/?module=editor/editor&action=productsAutocomplete',
            },
        },
    ],
});

Astero.Components.add('components/manufacturers', {
    name: 'Manufacturers',
    classes: ['component_manufacturers'],
    icon: 'ri-building-line',
    html: '<div class="mb-3"><label>Your response:</label><textarea class="form-control"></textarea></div>',
    properties: [
        {
            nolabel: false,
            inputtype: TextInput,
            data: { text: 'Fields' },
        },
        {
            name: 'Name',
            key: 'category',
            inputtype: TextInput,
        },
        {
            name: 'Image',
            key: 'category',
            inputtype: TextInput,
        },
    ],
});

Astero.Components.add('components/categories', {
    name: 'Categories',
    classes: ['component_categories'],
    icon: 'ri-folder-open-line',
    html: '<div class="mb-3"><label>Your response:</label><textarea class="form-control"></textarea></div>',
    properties: [
        {
            name: 'Name',
            key: 'name',
            htmlAttr: 'src',
            inputtype: FileUploadInput,
        },
    ],
});
Astero.Components.add('components/search', {
    name: 'Search',
    classes: ['component_search'],
    icon: 'ri-search-line',
    html: '<div class="mb-3"><label>Your response:</label><textarea class="form-control"></textarea></div>',
    properties: [
        {
            name: 'asdasdad',
            key: 'src',
            htmlAttr: 'src',
            inputtype: FileUploadInput,
        },
        {
            name: '34234234',
            key: 'width',
            htmlAttr: 'width',
            inputtype: TextInput,
        },
        {
            name: 'd32d23',
            key: 'height',
            htmlAttr: 'height',
            inputtype: TextInput,
        },
    ],
});
Astero.Components.add('components/user', {
    name: 'User',
    classes: ['component_user'],
    icon: 'ri-user-line',
    html: '<div class="mb-3"><label>Your response:</label><textarea class="form-control"></textarea></div>',
    properties: [
        {
            name: 'asdasdad',
            key: 'src',
            htmlAttr: 'src',
            inputtype: FileUploadInput,
        },
        {
            name: '34234234',
            key: 'width',
            htmlAttr: 'width',
            inputtype: TextInput,
        },
        {
            name: 'd32d23',
            key: 'height',
            htmlAttr: 'height',
            inputtype: TextInput,
        },
    ],
});
Astero.Components.add('components/product_gallery', {
    name: 'Product gallery',
    classes: ['component_product_gallery'],
    icon: 'ri-gallery-line',
    html: '<div class="mb-3"><label>Your response:</label><textarea class="form-control"></textarea></div>',
    properties: [
        {
            name: 'asdasdad',
            key: 'src',
            htmlAttr: 'src',
            inputtype: FileUploadInput,
        },
        {
            name: '34234234',
            key: 'width',
            htmlAttr: 'width',
            inputtype: TextInput,
        },
        {
            name: 'd32d23',
            key: 'height',
            htmlAttr: 'height',
            inputtype: TextInput,
        },
    ],
});
Astero.Components.add('components/cart', {
    name: 'Cart',
    classes: ['component_cart'],
    icon: 'ri-shopping-cart-line',
    html: '<div class="mb-3"><label>Your response:</label><textarea class="form-control"></textarea></div>',
    properties: [
        {
            name: 'asdasdad',
            key: 'src',
            htmlAttr: 'src',
            inputtype: FileUploadInput,
        },
        {
            name: '34234234',
            key: 'width',
            htmlAttr: 'width',
            inputtype: TextInput,
        },
        {
            name: 'd32d23',
            key: 'height',
            htmlAttr: 'height',
            inputtype: TextInput,
        },
    ],
});
Astero.Components.add('components/checkout', {
    name: 'Checkout',
    classes: ['component_checkout'],
    icon: 'ri-secure-payment-line',
    html: '<div class="mb-3"><label>Your response:</label><textarea class="form-control"></textarea></div>',
    properties: [
        {
            name: 'asdasdad',
            key: 'src',
            htmlAttr: 'src',
            inputtype: FileUploadInput,
        },
        {
            name: '34234234',
            key: 'width',
            htmlAttr: 'width',
            inputtype: TextInput,
        },
        {
            name: 'd32d23',
            key: 'height',
            htmlAttr: 'height',
            inputtype: TextInput,
        },
    ],
});
Astero.Components.add('components/filters', {
    name: 'Filters',
    classes: ['component_filters'],
    icon: 'ri-filter-3-line',
    html: '<div class="mb-3"><label>Your response:</label><textarea class="form-control"></textarea></div>',
    properties: [
        {
            name: 'asdasdad',
            key: 'src',
            htmlAttr: 'src',
            inputtype: FileUploadInput,
        },
        {
            name: '34234234',
            key: 'width',
            htmlAttr: 'width',
            inputtype: TextInput,
        },
        {
            name: 'd32d23',
            key: 'height',
            htmlAttr: 'height',
            inputtype: TextInput,
        },
    ],
});
Astero.Components.add('components/product', {
    name: 'Product',
    classes: ['component_product'],
    icon: 'ri-shirt-line',
    html: '<div class="mb-3"><label>Your response:</label><textarea class="form-control"></textarea></div>',
    properties: [
        {
            name: 'asdasdad',
            key: 'src',
            htmlAttr: 'src',
            inputtype: FileUploadInput,
        },
        {
            name: '34234234',
            key: 'width',
            htmlAttr: 'width',
            inputtype: TextInput,
        },
        {
            name: 'd32d23',
            key: 'height',
            htmlAttr: 'height',
            inputtype: TextInput,
        },
    ],
});
Astero.Components.add('components/slider', {
    name: 'Slider',
    classes: ['component_slider'],
    icon: 'ri-slideshow-line',
    html: '<div class="form-group"><label>Your response:</label><textarea class="form-control"></textarea></div>',
    properties: [
        {
            name: 'asdasdad',
            key: 'src',
            htmlAttr: 'src',
            inputtype: FileUploadInput,
        },
        {
            name: '34234234',
            key: 'width',
            htmlAttr: 'width',
            inputtype: TextInput,
        },
        {
            name: 'd32d23',
            key: 'height',
            htmlAttr: 'height',
            inputtype: TextInput,
        },
    ],
});
