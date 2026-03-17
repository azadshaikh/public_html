const __ASTERO_INPUTS_ROOT__ =
    typeof globalThis !== 'undefined' ? globalThis : window;
const tmpl = __ASTERO_INPUTS_ROOT__.tmpl;
const generateElements = window.generateElements;

let Input = {
    init: function (name) {},

    onChange: function (event, node, input) {
        if (event && event.target) {
            const e = new CustomEvent('propertyChange', {
                detail: {
                    value: input.value ?? this.value,
                    input: this,
                    origEvent: event,
                },
            });
            event.currentTarget.dispatchEvent(e);
        }
    },

    renderTemplate: function (name, data) {
        return tmpl('astero-input-' + name, data);
    },

    setValue: function (value) {
        if (this.element[0] && value) {
            let input = this.element[0].querySelector('input');

            if (input) {
                input.value = value;
            }
        }
    },

    render: function (name, data) {
        let html = this.renderTemplate(name, data);
        this.element = generateElements(html);

        //bind events
        if (this.events)
            for (let i in this.events) {
                const ev = this.events[i][0];
                const fun = this[this.events[i][1]];
                const el = this.events[i][2];

                this.element[0].addEventListener(
                    ev,
                    function (ev, el, fun, target, event) {
                        if (event.target.closest(el)) {
                            //target, event, element, input
                            return fun.call(event.target, event, target, this);
                        }
                    }.bind(this, ev, el, fun, this.element[0]),
                );
            }

        return this.element[0];
    },
};

let TextInput = {
    ...Input,
    ...{
        events: [
            //event, listener, child element
            ['focusout', 'onChange', 'input'],
        ],

        init: function (data) {
            return this.render('textinput', data);
        },
    },
};

let TextareaInput = {
    ...Input,
    ...{
        events: [['keyup', 'onChange', 'textarea']],

        setValue: function (value) {
            if (this.element[0] && value) {
                let input = this.element[0].querySelector('textarea');

                if (input) {
                    input.value = value;
                }
            }
        },

        init: function (data) {
            return this.render('textareainput', data);
        },
    },
};

let CheckboxInput = {
    ...Input,
    ...{
        events: [['change', 'onCheck', 'input']],

        onCheck: function (event, node, input) {
            input.value = this.checked;
            return input.onChange.call(this, event, node, input);
        },

        setValue: function (value) {
            if (this.element[0]) {
                let input = this.element[0].querySelector('input');

                if (input) {
                    if (value) {
                        input.checked = true;
                    } else {
                        input.checked = false;
                    }
                }
            }
        },

        init: function (data) {
            return this.render('checkboxinput', data);
        },
    },
};

let SelectInput = {
    ...Input,
    ...{
        events: [['change', 'onChange', 'select']],

        setValue: function (value) {
            if (this.element[0] && value) {
                let input = this.element[0].querySelector('select');

                if (input) {
                    input.value = value;
                }
            }
        },

        init: function (data) {
            return this.render('select', data);
        },
    },
};

let IconSelectInput = {
    ...Input,
    ...{
        events: [['change', 'onChange', 'select']],

        setValue: function (value) {
            if (this.element[0] && value) {
                let input = this.element[0].querySelector('select');

                if (input) {
                    input.value = value;
                }
            }
        },

        init: function (data) {
            return this.render('icon-select', data);
        },
    },
};

let HtmlListSelectInput = {
    ...Input,
    ...{
        data: {},
        cache: {},

        events: [
            //["click", "onChange", "li"],
            ['change', 'onListChange', 'select'],
            ['keyup', 'searchElement', 'input.search'],
            ['click', 'clearSearch', 'button.clear-backspace'],
        ],

        clearSearch: function (event, element, input) {
            let search = element.querySelector('input.search');
            if (search) {
                search.value = '';
                input.searchElement(event, element, input);
            }

            search.dispatchEvent(
                new KeyboardEvent('keyup', {
                    bubbles: true,
                    cancelable: true,
                }),
            );
        },

        searchElement: function (event, element, input) {
            const searchText = (this.value || '').toLowerCase();

            delay(() => {
                element.querySelectorAll('li').forEach((el, i) => {
                    const title = (el.title || '').toLowerCase();
                    if (!searchText || title.indexOf(searchText) > -1) {
                        el.style.display = '';
                    } else {
                        el.style.display = 'none';
                    }
                });
            }, 500);
        },

        onElementClick: function (event, element, input) {
            let data = input.data;
            let svg = this.closest(data.insertElement);
            let value = svg.outerHTML ?? '<svg></svg>';
            input.value = value;
            let ret = input.onChange.call(this, event, element, input);

            return element;
        },

        onListChange: function (event, element, input) {
            let url = input.data.url.replace('{value}', this.value);
            let elements = element.querySelector('.elements');

            elements.innerHTML = `<div class="p-4"><div class="spinner-border spinner-border-sm" role="status">
		  <span class="visually-hidden">Loading...</span>
		</div> Loading...</div>`;

            //cache ajax requests
            if (input.cache[url] != undefined) {
                elements.innerHTML = input.cache[url];
            } else {
                fetch(url)
                    .then((response) => {
                        if (!response.ok) {
                            throw new Error(response);
                        }
                        return response.text();
                    })
                    .then((text) => {
                        input.cache[url] = text;
                        elements.innerHTML = text;
                    })
                    .catch((error) => {
                        console.log(error.statusText);
                        displayToast(
                            'bg-danger',
                            'Error',
                            'Error loading list',
                        );
                    });
            }
        },

        setValue: function (value) {
            let select = this.element[0].querySelector('select');
            if (value && select) {
                select.value = value;
            }
        },

        init: function (data) {
            this.data = data;
            this.events.push(['click', 'onElementClick', data.clickElement]);
            let template = this.render('html-list-select', data);
            let select = template.querySelector('select');
            this.onListChange.call(select, new Event('change'), template, this);
            return template;
        },
    },
};

let LinkInput = {
    ...TextInput,
    ...{
        events: [['change', 'onChange', 'input']],
        /*
	setValue: function(value) {
		//value = value.replace(/(?<!\/)www\./, 'https://www.');
		this.element.querySelector('input').value = value;
	},
	*/
        init: function (data) {
            return this.render('textinput', data);
        },
    },
};

let DateInput = {
    ...TextInput,
    ...{
        events: [['change', 'onChange', 'input']],

        init: function (data) {
            return this.render('dateinput', data);
        },
    },
};

let RangeInput = {
    ...Input,
    ...{
        events: [['change', 'onRangeChange', 'input']],

        onRangeChange: function (event, node, input) {
            this.parentNode.querySelector('input[type=number]').value =
                this.value;
            this.parentNode.querySelector('input[type=range]').value =
                this.value;
            return input.onChange.call(this, event, node, input);
        },

        setValue: function (value) {
            this.element[0].querySelector('input[type=number]').value = value;
            this.element[0].querySelector('input[type=range]').value = value;
        },

        init: function (data) {
            return this.render('rangeinput', data);
        },
    },
};

let NumberInput = {
    ...Input,
    ...{
        events: [['change', 'onChange', 'input']],

        init: function (data) {
            return this.render('numberinput', data);
        },
    },
};

let CssUnitInput = {
    ...Input,
    ...{
        number: 0,
        unit: 'px',

        events: [
            ['change', 'onInputChange', 'select'],
            ['change', 'onInputChange', 'input'],
            ['keyup', 'onInputChange', 'input'],
        ],

        onInputChange: function (event, node, input) {
            if (node) {
                let number = node.querySelector('input').value;
                let unit = node.querySelector('select').value;

                if (this.value != '') input[this.name] = this.value; // this.name = unit or number
                if (unit == '') unit = 'px'; //if unit is not set use default px

                let value = '';
                if (unit == 'auto') {
                    node.classList.add('auto');
                    value = unit;
                } else {
                    node.classList.remove('auto');
                    value = number + (unit ? unit : '');
                }

                input.value = value;

                return input.onChange.call(this, event, node, input);
            }
        },

        setValue: function (value) {
            if (value && this.element) {
                let element = this.element[0];
                this.number = parseFloat(value);
                this.unit = value.replace(this.number, '').trim();

                if (this.unit == 'auto') element.classList.add('auto');

                element.querySelector('input[type=number]').value = this.number;
                element.querySelector('select').value = this.unit;
            }
        },

        init: function (data) {
            return this.render('cssunitinput', data);
        },
    },
};

let ColorInput = {
    ...Input,
    ...{
        //html5 color input only supports setting values as hex colors even if the picker returns only rgb
        rgb2hex: function (value) {
            if (value) {
                value = value.trim();

                const rgb = value.match(
                    /^rgba?[\s+]?\([\s+]?(\d+)[\s+]?,[\s+]?(\d+)[\s+]?,[\s+]?(\d+)[\s+]?/i,
                );

                if (rgb) {
                    return rgb && rgb.length === 4
                        ? '#' +
                              ('0' + parseInt(rgb[1], 10).toString(16)).slice(
                                  -2,
                              ) +
                              ('0' + parseInt(rgb[2], 10).toString(16)).slice(
                                  -2,
                              ) +
                              ('0' + parseInt(rgb[3], 10).toString(16)).slice(
                                  -2,
                              )
                        : rgb;
                }
            }

            return value;
        },

        events: [['change', 'onChange', 'input']],

        setValue: function (value) {
            if (this.element && this.element[0] && value) {
                let input = this.element[0].querySelector('input');

                if (input) {
                    input.value = this.rgb2hex(value);
                }
            }
        },

        init: function (data) {
            //if no palette provided use default
            if (!data.palette) {
                data.palette = Astero.ColorPalette.getAll();
            }

            return this.render('colorinput', data);
        },
    },
};

// Expose inputs needed by plugin modules.
(typeof globalThis !== 'undefined' ? globalThis : window).ColorInput =
    ColorInput;

let VideoInput = {
    ...TextInput,
    ...{
        events: [['focusout', 'onChange', 'input']],

        init: function (data) {
            return this.render('textinput', data);
        },
    },
};

let ImageInput = {
    ...Input,
    ...{
        events: [
            ['focusout', 'onChange', 'input[type=text]'],
            ['change', 'onUpload', 'input[type=file]'],
        ],

        setValue: function (value) {
            //don't set blob value to avoid slowing down the page
            if (
                this.element?.[0] &&
                value &&
                value.indexOf('data:image') == -1
            ) {
                const input =
                    this.element[0].querySelector('input[type="text"]');
                if (input) {
                    input.value = value;
                }
            }
        },

        onUpload: function (event, node, input) {
            const file = this.files?.[0];
            if (!file) return;

            const reader = new FileReader();
            reader.onload = (e) => {
                const image = e.target?.result || '';

                input.value = image;
                input.onChange.call(this, event, node, input);

                const uploadUrl = input.data?.uploadUrl || 'upload.php';
                if (!uploadUrl || !window.fetch) return;

                const formData = new FormData();
                formData.append('file', file);

                fetch(uploadUrl, {
                    method: 'POST',
                    body: formData,
                })
                    .then((response) => {
                        if (!response.ok) {
                            throw response;
                        }
                        return response.text();
                    })
                    .then((data) => {
                        if (!data) return;

                        input.value = data;
                        input.onChange.call(this, event, node, input);

                        const textInput =
                            node?.querySelector('input[type="text"]');
                        if (textInput) {
                            textInput.value = data;
                        }
                    })
                    .catch((error) => {
                        const message = error?.statusText || 'Upload failed';
                        if (typeof displayToast !== 'undefined') {
                            displayToast('bg-danger', 'Error', message);
                        } else {
                            alert(message);
                        }
                    });
            };
            reader.readAsDataURL(file);
        },
    },
};

// Expose inputs needed by plugin modules.
(typeof globalThis !== 'undefined' ? globalThis : window).ImageInput =
    ImageInput;

let FileUploadInput = {
    ...TextInput,
    ...{
        events: [['focusout', 'onChange', 'input']],

        init: function (data) {
            return this.render('textinput', data);
        },
    },
};

let RadioInput = {
    ...Input,
    ...{
        events: [['change', 'onChange', 'input']],

        setValue: function (value) {
            if (this.element[0] && value) {
                let input = this.element[0].querySelector('input');

                if (input) {
                    if (value == input.value) {
                        input.setAttribute('checked', 'true');
                        input.checked = true;
                    } else {
                        input.checked = false;
                        input.removeAttribute('checked');
                    }
                }
            }
        },

        init: function (data) {
            return this.render('radioinput', data);
        },
    },
};

let RadioButtonInput = {
    ...RadioInput,
    ...{
        setValue: function (value) {
            if (this.element[0] && value) {
                let inputs = this.element[0].querySelectorAll('input');
                let selected = null;
                inputs.forEach((el, i) => {
                    if (value == el.value) {
                        selected = el;
                    } else {
                        el.checked = false;
                        el.removeAttribute('checked');
                    }
                });

                if (selected) {
                    selected.checked = true;
                    selected.setAttribute('checked', 'checked');
                }
            }
        },

        init: function (data) {
            return this.render('radiobuttoninput', data);
        },
    },
};

let ToggleInput = {
    ...Input,
    ...{
        events: [['change', 'onToggleChange', 'input']],

        onToggleChange: function (event, node, input) {
            input.value = this.checked
                ? this.getAttribute('data-value-on')
                : this.getAttribute('data-value-off');
            return input.onChange.call(this, event, node, input);
        },

        setValue: function (value) {
            if (this.element[0]) {
                let input = this.element[0].querySelector('input');

                if (input) {
                    if (value == input.getAttribute('data-value-on')) {
                        input.checked = true;
                        input.setAttribute('checked', true);
                    } else {
                        input.checked = false;
                        input.removeAttribute('checked');
                    }
                }
            }
        },

        init: function (data) {
            return this.render('toggle', data);
        },
    },
};

let ValueTextInput = {
    ...TextInput,
    ...{
        events: [['focusout', 'onChange', 'input']],

        init: function (data) {
            return this.render('textinput', data);
        },
    },
};

let GridLayoutInput = {
    ...TextInput,
    ...{
        events: [['focusout', 'onChange', 'input']],

        init: function (data) {
            return this.render('textinput', data);
        },
    },
};

let ProductsInput = {
    ...TextInput,
    ...{
        events: [['focusout', 'onChange', 'input']],

        init: function (data) {
            return this.render('textinput', data);
        },
    },
};

let GridInput = {
    ...Input,
    ...{
        events: [
            ['change', 'onChange', 'select' /*'select'*/],
            ['click', 'onChange', 'button' /*'select'*/],
        ],

        setValue: function (value) {
            if (this.element[0] && value) {
                let input = this.element[0].querySelector('select');

                if (input) {
                    input.value = value;
                    input.querySelector('option[selected]').selected = true;
                }
            }
        },

        init: function (data) {
            return this.render('grid', data);
        },
    },
};

let TextValueInput = {
    ...Input,
    ...{
        events: [
            ['focusout', 'onChange', 'input'],
            ['click', 'onChange', 'button' /*'select'*/],
        ],

        setValue: function (value) {
            return false;
        },

        init: function (data) {
            return this.render('textvalue', data);
        },
    },
};

let ButtonInput = {
    ...Input,
    ...{
        events: [['click', 'onChange', 'button' /*'select'*/]],

        setValue: function (value) {
            if (this.element[0] && value) {
                let input = this.element[0].querySelector('button');

                if (input) {
                    input.value = value;
                }
            }
        },

        init: function (data) {
            return this.render('button', data);
        },
    },
};

let SectionInput = {
    ...Input,
    ...{
        events: [
            //["click", "onChange", "button" /*'select'*/],
        ],

        setValue: function (value) {
            return false;
        },

        init: function (data) {
            return this.render('sectioninput', data);
        },
    },
};

let ListInput = {
    ...Input,
    ...{
        events: [
            ['change', 'onChange', 'select'],
            ['click', 'remove', '.delete-btn'],
            ['click', 'add', '.btn-new'],
            ['click', 'select', '.section-item'],
        ],

        remove: function (event, node, input) {
            let sectionItem = this.closest('.section-item');
            let index = [...sectionItem.parentNode.children].indexOf(
                sectionItem,
            ); //sectionItem.index();
            let data = input.data;

            if (data.removeElement) {
                let container = input.node;
                if (data.container) {
                    container =
                        container.querySelector(data.container) || container;
                }
                const target = container?.querySelector(
                    data.selector + ':nth-child(' + (index + 1) + ')',
                );
                target?.remove();
            }
            sectionItem.remove();

            event.action = 'remove';
            event.index = index;
            input.onChange(event, node, input, this);
            event.preventDefault();
            return false;
        },

        add: function (event, node, input) {
            let data = input.data;
            let newElement = input.data.newElement ?? false;
            if (newElement) {
                let container = input.node;
                if (data.container) {
                    container =
                        container.querySelector(data.container) || container;
                }
                if (container) {
                    container.append(generateElements(newElement)[0]);
                }
            }

            event.action = 'add';
            input.onChange(event, node, input, this);
            return false;
        },

        select: function (event, node, input) {
            let sectionItem = this.closest('.section-item');
            if (sectionItem.parentNode) {
                let index = [...sectionItem.parentNode.children].indexOf(
                    sectionItem,
                ); //sectionItem.index();

                event.action = 'select';
                event.index = index;
                input.onChange(event, node, input, this);
            }
            return false;
        },

        setValue: function (value) {},

        init: function (data, node) {
            this.component = data.component;
            this.selector = data.selector;
            this.node = node;

            let elements = this.node.querySelectorAll(
                data.container + ' ' + this.selector,
            );
            let options = [];

            elements.forEach(function (e, i) {
                let element = e;
                if (data.nameElement) {
                    element = element.querySelector(data.nameElement);
                }
                let nameSource = element?.textContent || '';
                let useText = data.name === 'text' || !data.name;
                let name = useText
                    ? nameSource.substr(0, 15)
                    : element?.id || '';
                options.push({
                    name: name,
                    type: (data.prefix ?? '') + (i + 1) + (data.suffix ?? ''),
                });
            });

            data.options = options;
            data.elements = elements;
            this.data = data;

            return this.render('listinput', data);
        },
    },
};

const ChoicesInput = {
    ...Input,
    ...{
        events: [
            ['change', 'onChange', 'input'],
            ['addItem', 'onChange', 'input'],
            ['removeItem', 'onChange', 'input'],
        ],

        init: function (data) {
            const element = this.render('textinput', data);
            const input = element.querySelector('input');

            if (input) {
                // Initialize Choices.js
                const choices = new Choices(input, {
                    removeItemButton: true,
                    duplicateItemsAllowed: false,
                    ...data.choicesConfig, // Allow passing config from data
                });

                // Store instance on element for later access if needed
                input.choicesInstance = choices;
            }

            return element;
        },

        setValue: function (value) {
            const input = this.element[0].querySelector('input');
            if (input && input.choicesInstance) {
                if (value) {
                    input.choicesInstance.setValue(value.split(' '));
                } else {
                    input.choicesInstance.clearStore();
                }
            }
        },
    },
};

// Map old names to new ChoicesInput for backward compatibility
const AutocompleteInput = ChoicesInput;
const AutocompleteList = ChoicesInput;
const TagsInput = {
    ...ChoicesInput,
    // Tags specific config if different from default ChoicesInput
    init: function (data) {
        // Force specific config for tags if needed, otherwise just use ChoicesInput logic
        const element = this.render('textinput', data);
        const input = element.querySelector('input');

        if (input) {
            const choices = new Choices(input, {
                removeItemButton: true,
                duplicateItemsAllowed: false,
                delimiter: ' ',
                editItems: true,
                maxItemCount: 10,
                paste: false,
                searchEnabled: false,
                ...data.choicesConfig,
            });
            input.choicesInstance = choices;
        }
        return element;
    },
};

let NoticeInput = {
    ...Input,
    ...{
        events: [],

        init: function (data) {
            return this.render('noticeinput', data);
        },
    },
};

// Expose input types for legacy plugins and cross-module access.
const __ASTERO_INPUTS_GLOBAL__ =
    typeof globalThis !== 'undefined' ? globalThis : window;
Object.assign(__ASTERO_INPUTS_GLOBAL__, {
    Input,
    TextInput,
    TextareaInput,
    CheckboxInput,
    SelectInput,
    IconSelectInput,
    HtmlListSelectInput,
    LinkInput,
    DateInput,
    RangeInput,
    NumberInput,
    CssUnitInput,
    ColorInput,
    ImageInput,
    FileUploadInput,
    RadioInput,
    RadioButtonInput,
    ToggleInput,
    VideoInput,
    ValueTextInput,
    GridLayoutInput,
    ProductsInput,
    GridInput,
    TextValueInput,
    ButtonInput,
    SectionInput,
    ListInput,
    AutocompleteInput,
    TagsInput, // Still exposing TagsInput but mapped to new logic
    NoticeInput,
    ChoicesInput, // Expose new one too
});
