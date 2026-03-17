Astero.ComponentsGroup = {};
Astero.SectionsGroup = {};
Astero.BlocksGroup = {};

Astero.Components = {
    _components: {},

    _nodesLookup: {},

    _attributesLookup: {},

    _classesLookup: {},

    _classesRegexLookup: {},

    componentPropertiesElement: '#right-panel .component-properties',

    componentPropertiesDefaultSection: 'content',

    get: function (type) {
        return this._components[type];
    },

    updateProperty: function (type, key, value) {
        let properties = this._components[type]['properties'];
        for (let property in properties) {
            if (key == properties[property]['key']) {
                return (this._components[type]['properties'][property] =
                    Object.assign(properties[property], value));
            }
        }
    },

    getProperty: function (type, key) {
        let properties = this._components[type]
            ? this._components[type]['properties']
            : [];
        for (let property in properties) {
            if (key == properties[property]['key']) {
                return properties[property];
            }
        }
    },

    add: function (type, data) {
        data.type = type;

        this._components[type] = data;

        if (data.nodes) {
            for (let i in data.nodes) {
                this._nodesLookup[data.nodes[i]] = data;
            }
        }

        if (data.attributes) {
            if (data.attributes.constructor === Array) {
                for (let i in data.attributes) {
                    this._attributesLookup[data.attributes[i]] = data;
                }
            } else {
                for (let i in data.attributes) {
                    if (typeof this._attributesLookup[i] === 'undefined') {
                        this._attributesLookup[i] = {};
                    }

                    if (
                        typeof this._attributesLookup[i][data.attributes[i]] ===
                        'undefined'
                    ) {
                        this._attributesLookup[i][data.attributes[i]] = {};
                    }

                    this._attributesLookup[i][data.attributes[i]] = data;
                }
            }
        }

        if (data.classes) {
            for (let i in data.classes) {
                this._classesLookup[data.classes[i]] = data;
            }
        }

        if (data.classesRegex) {
            for (let i in data.classesRegex) {
                this._classesRegexLookup[data.classesRegex[i]] = data;
            }
        }
    },

    extend: function (inheritType, type, data) {
        let newData = data;

        let inheritData;

        if ((inheritData = this._components[inheritType])) {
            newData = { ...inheritData, ...data };
            newData.properties = (
                data.properties ? data.properties : []
            ).concat(inheritData.properties ? inheritData.properties : []);
        }

        //sort by order
        newData.properties.sort(function (a, b) {
            if (typeof a.sort === 'undefined') a.sort = 0;
            if (typeof b.sort === 'undefined') b.sort = 0;

            if (a.sort < b.sort) return -1;
            if (a.sort > b.sort) return 1;
            return 0;
        });

        this.add(type, newData);
    },

    matchNode: function (node) {
        let component = {};

        if (!node || !node.tagName) return false;

        if (node.attributes && node.attributes.length) {
            //search for attributes
            for (let i in node.attributes) {
                if (node.attributes[i]) {
                    const attr = node.attributes[i].name;
                    const value = node.attributes[i].value;

                    if (attr in this._attributesLookup) {
                        component = this._attributesLookup[attr];

                        //currently we check that is not a component by looking at name attribute
                        //if we have a collection of objects it means that attribute value must be checked
                        if (typeof component['name'] === 'undefined') {
                            if (value in component) {
                                return component[value];
                            }
                        } else {
                            return component;
                        }
                    }
                }
            }

            for (let i in node.attributes) {
                const attr = node.attributes[i].name;
                const value = node.attributes[i].value;

                //check for node classes
                if (attr == 'class') {
                    const classes = value.split(' ');

                    for (const j in classes) {
                        if (classes[j] in this._classesLookup)
                            return this._classesLookup[classes[j]];
                    }

                    for (const regex in this._classesRegexLookup) {
                        const regexObj = new RegExp(regex);
                        if (regexObj.exec(value)) {
                            return this._classesRegexLookup[regex];
                        }
                    }
                }
            }
        }

        const tagName = node.tagName.toLowerCase();
        if (tagName in this._nodesLookup) return this._nodesLookup[tagName];

        return false;
        //return false;
    },

    render: function (type, panel = false) {
        let component = this._components[type];
        if (!component) return;

        if (!panel) {
            //panel = document.querySelector(this.componentPropertiesElement);
            panel = this.componentPropertiesElement;
        }

        let defaultSection = this.componentPropertiesDefaultSection;
        let componentsPanelSections = {};

        document.querySelectorAll(panel + ' .tab-pane').forEach((el, i) => {
            let sectionName = el.dataset.section;
            componentsPanelSections[sectionName] = el;
            for (const item of el.querySelectorAll(
                'label:not([data-header="default"]) + input,' +
                    'label:not([data-header="default"]),' +
                    '.section:not([data-section="default"])',
            )) {
                item.remove();
            }
        });

        let defaultPanel = componentsPanelSections[defaultSection];
        if (!defaultPanel) {
            return;
        }

        let section = defaultPanel.querySelector(
            '.section[data-section="default"]',
        );

        if (!(Astero.preservePropertySections && section)) {
            let template = tmpl('astero-input-sectioninput', {
                key: 'default',
                header: component.name,
            });

            defaultPanel.replaceChildren();
            defaultPanel.append(generateElements(template)[0]);

            section = defaultPanel.querySelector('.section');
        }

        const defaultHeader = defaultPanel.querySelector(
            '[data-header="default"] span',
        );
        if (defaultHeader) {
            defaultHeader.innerHTML = component.name;
        }
        section?.replaceChildren();

        if (component.beforeInit)
            component.beforeInit(Astero.Builder.selectedEl);

        let element;
        let selectedElement;

        let fn = function (component, property) {
            if (property.input) {
                property.input.addEventListener('propertyChange', (event) => {
                    element = selectedElement = Astero.Builder.selectedEl;

                    // Early return if no element is selected
                    if (!selectedElement) {
                        console.warn('No element selected for property change');
                        return;
                    }

                    let value = event.detail.value,
                        input = event.detail.input,
                        origEvent = event.detail.origEvent;

                    let oldValue = null;
                    let oldStyle = null;
                    let mutation = null;

                    if (property.child)
                        element = element.querySelector(property.child);
                    if (property.parent && element)
                        element = element.closest(property.parent);

                    if (property.onChange) {
                        let ret = property.onChange(
                            element,
                            value,
                            input,
                            component,
                            origEvent,
                        );
                        //if on change returns an object then is returning the dom node otherwise is returning the new value
                        if (typeof ret == 'object') {
                            element = ret;
                        } else {
                            value = ret;
                        }
                    } /* else */
                    if (property.htmlAttr && element) {
                        oldValue = element.getAttribute(property.htmlAttr);

                        if (
                            property.htmlAttr == 'class' &&
                            property.validValues &&
                            element
                        ) {
                            if (property.validValues) {
                                element.classList.remove(
                                    ...property.validValues.filter((v) => v),
                                );
                            }
                            if (value) {
                                element.classList.add(...value.split(' '));
                            }
                        } else if (property.htmlAttr == 'style' && element) {
                            //keep old style for undo
                            oldStyle =
                                window.FrameDocument.getElementById(
                                    'pagebuilder-styles',
                                ).textContent;
                            element = Astero.StyleManager.setStyle(
                                element,
                                property.key,
                                value,
                            );
                        } else if (
                            property.htmlAttr == 'innerHTML' &&
                            element
                        ) {
                            element = Astero.ContentManager.setHtml(
                                element,
                                value,
                            );
                        } else if (
                            property.htmlAttr == 'innerText' &&
                            element
                        ) {
                            element = Astero.ContentManager.setText(
                                element,
                                value,
                            );
                        } else if (element) {
                            //if value is empty then remove attribute useful for attributes without values like disabled
                            if (value) {
                                element.setAttribute(property.htmlAttr, value);
                            } else {
                                element.removeAttribute(property.htmlAttr);
                            }
                        }

                        if (property.htmlAttr == 'style' && element) {
                            mutation = {
                                type: 'style',
                                target: element,
                                attributeName: property.htmlAttr,
                                oldValue: oldStyle,
                                newValue:
                                    window.FrameDocument.getElementById(
                                        'pagebuilder-styles',
                                    ).textContent,
                            };

                            Astero.Undo.addMutation(mutation);
                        } else if (element) {
                            Astero.Undo.addMutation({
                                type: 'attributes',
                                target: element,
                                attributeName: property.htmlAttr,
                                oldValue: oldValue,
                                newValue: element.getAttribute(
                                    property.htmlAttr,
                                ),
                            });
                        }
                    }

                    if (component.onChange && element) {
                        const ret =
                            component.onChange.length >= 4
                                ? component.onChange(
                                      element,
                                      property,
                                      value,
                                      input,
                                      origEvent,
                                  )
                                : component.onChange(
                                      element,
                                      value,
                                      input,
                                      component,
                                      origEvent,
                                  );
                        if (typeof ret !== 'undefined') {
                            element = ret;
                        }
                    }

                    if (property.child || property.parent) {
                        Astero.Builder.selectNode(selectedElement);
                    } else if (element) {
                        Astero.Builder.selectNode(element);
                    }

                    return element;
                });
            }

            return property.input;
        };

        let nodeElement = Astero.Builder.selectedEl;

        for (let i in component.properties) {
            let property = component.properties[i];
            let element = nodeElement;

            if (property.beforeInit) property.beforeInit(element);

            if (property.child)
                element = element.querySelector(property.child) ?? element;
            if (property.parent)
                element = element.closest(property.parent) ?? element;

            if (property.data) {
                property.data['key'] = property.key;
            } else {
                property.data = { key: property.key };
            }

            if (typeof property.group === 'undefined') property.group = null;

            property.input = property.inputtype.init(property.data, element);

            let value;
            if (property.init) {
                property.inputtype.setValue(property.init(element));
            } else if (property.htmlAttr) {
                if (property.htmlAttr == 'style') {
                    //value = element.css(property.key);//jquery css returns computed style
                    value = Astero.StyleManager.getStyle(element, property.key); //getStyle returns declared style
                } else if (property.htmlAttr == 'innerHTML') {
                    value = Astero.ContentManager.getHtml(element);
                } else if (property.htmlAttr == 'innerText') {
                    value = Astero.ContentManager.getText(element);
                } else {
                    value = element.getAttribute(property.htmlAttr);
                }

                //if attribute is class check if one of valid values is included as class to set the select
                if (
                    value &&
                    property.htmlAttr == 'class' &&
                    property.validValues
                ) {
                    let valid = value.split(' ').filter(function (el) {
                        return property.validValues.indexOf(el) != -1;
                    });

                    if (valid && valid.length) {
                        value = valid[0];
                    } else {
                        value = '';
                    }
                }

                if (!value && property.defaultValue) {
                    value = property.defaultValue;
                }

                property.inputtype.setValue(value);
            } else {
                if (!value && property.defaultValue) {
                    value = property.defaultValue;
                }

                property.inputtype.setValue(value);
            }

            fn(component, property);

            let propertySection = defaultSection;
            if (property.section) {
                propertySection = property.section;
            }

            const targetPanel =
                componentsPanelSections[propertySection] || defaultPanel;
            if (!targetPanel) continue;

            if (property.inputtype == SectionInput) {
                section = targetPanel.querySelector(
                    '.section[data-section="' + property.key + '"]',
                );

                if (Astero.preservePropertySections && section) {
                    section.replaceChildren();
                } else {
                    targetPanel.append(property.input);
                    section = targetPanel.querySelector(
                        '.section[data-section="' + property.key + '"]',
                    );
                }
            } else {
                let row = generateElements(
                    tmpl('astero-property', property),
                )[0];
                row.querySelector('.input').append(property.input);
                section?.append(row);
            }

            if (property.inputtype.afterInit) {
                property.inputtype.afterInit(property.input);
            }

            if (property.afterInit) {
                property.afterInit(element, property.input);
            }
        }

        if (component.init) component.init(nodeElement);
    },
};

Astero.Blocks = {
    _blocks: {},

    get: function (type) {
        return this._blocks[type];
    },

    add: function (type, data) {
        data.type = type;
        this._blocks[type] = data;
    },
};

Astero.Sections = {
    _sections: {},

    get: function (type) {
        return this._sections[type];
    },

    add: function (type, data) {
        data.type = type;
        this._sections[type] = data;
    },
};
