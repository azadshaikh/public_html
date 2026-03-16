function getNodeTree(node, parent, allowedComponents, idToNode = {}) {
    function getNodeTreeTraverse(node, parent, id = '') {
        if (node.hasChildNodes()) {
            for (let j = 0; j < node.childNodes.length; j++) {
                const child = node.childNodes[j];

                //skip text and comments nodes
                if (child.nodeType == 3 || child.nodeType == 8) {
                    continue;
                }

                let element;
                let matchChild;
                if (child && child['attributes'] != undefined && (matchChild = Astero.Components.matchNode(child))) {
                    if (Array.isArray(allowedComponents) && allowedComponents.indexOf(matchChild.type) == -1) {
                        element = getNodeTreeTraverse(child, parent);
                        continue;
                    }

                    let title = '';
                    //if (matchChild.type === "elements/section") {
                    title = child.id ? child.id : child.title ? child.title : (child.ariaLabel ?? '');
                    //}

                    element = {
                        name: matchChild.name,
                        image: matchChild.image,
                        type: matchChild.type,
                        title,
                        node: child,
                        id: id + '-' + j,
                        children: [],
                    };

                    element.children = [];
                    parent.push(element);
                    idToNode[id + '-' + j] = child;

                    element = getNodeTreeTraverse(child, element.children, id + '-' + j);
                } else {
                    element = getNodeTreeTraverse(child, parent, id + '-' + j);
                }
            }
        }

        return false;
    }

    getNodeTreeTraverse(node, parent, '1');
}

function drawComponentsTree(tree) {
    let j = 1;
    let prefix = Math.floor(Math.random() * 100);

    function drawComponentsTreeTraverse(tree) {
        let list = document.createElement('ol');
        j++;

        for (const i in tree) {
            let node = tree[i];
            let id = node.id;
            let li;

            if (!id) {
                id = prefix + '-' + j + '-' + i;
            }

            let title = node.title ? friendlyName(node.title.substr(0, 21)) : '';
            if (title) {
                title = ` - <span class="text-secondary">${title}</span>`;
            }

            if (tree[i].children.length > 0) {
                li = generateElements(
                    '<li data-component="' +
                        node.name +
                        '">\
                                <label for="id' +
                        id +
                        '" style="background-image:url(' +
                        Astero.imgBaseUrl +
                        node.image +
                        ')">\
                                    <span>' +
                        node.name +
                        '</span>' +
                        title +
                        '\
                                </label>\
                                <input type="checkbox" id="id' +
                        id +
                        '">\
                            </li>'
                )[0];
                li.append(drawComponentsTreeTraverse(node.children));
            } else {
                li = generateElements(
                    '<li data-component="' +
                        node.name +
                        '" class="file">\
                            <label for="id' +
                        id +
                        '" style="background-image:url(' +
                        Astero.imgBaseUrl +
                        node.image +
                        ')">\
                                <span>' +
                        node.name +
                        '</span>' +
                        title +
                        '\
                            </label>\
                            <input type="checkbox" id="id' +
                        id +
                        '">\
                            </li>'
                )[0];
            }

            li._treeNode = node.node;
            list.append(li);
        }

        return list;
    }

    return drawComponentsTreeTraverse(tree);
}

let selected = null;

Astero.SectionList = {
    selector: '.sections-container',
    allowedComponents: {},

    init: function (allowedComponents = {}) {
        this.allowedComponents = allowedComponents;

        const container = document.querySelector(this.selector);
        const sectionsList = document.querySelector('.sections-list');
        if (!container || !sectionsList) {
            return;
        }

        container.addEventListener('click', function (e) {
            // Skip if clicking on any button
            if (e.target.closest('.buttons a')) {
                return;
            }

            let element = e.target.closest('.section-item');
            if (element) {
                let node = element._node;
                if (node) {
                    node.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'center' });
                    Astero.Builder.selectNode(node);
                    Astero.Builder.loadNodeComponent(node);
                }
            }
        });

        container.addEventListener('dblclick', function (e) {
            let element = e.target.closest(':scope > div');
            if (element) {
                const node = element._node;
                node.click();
            }
        });

        container.addEventListener('click', function (e) {
            let element = e.target.closest('li[data-component] label');
            if (element) {
                let node = element.parentNode._node;
                if (node) {
                    node.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'center' });
                    node.click();
                }
            }
        });

        container.addEventListener('mouseenter', function (e) {
            let element = e.target.closest('li[data-component] label');
            if (element) {
                const node = element.parentNode._node;
                if (node && node.style) {
                    node.style.outline = '1px dashed blue';
                }
            }
        });

        container.addEventListener('mouseleave', function (e) {
            let element = e.target.closest('li[data-component] label');
            if (element) {
                const node = element.parentNode._node;
                if (node && node.style) {
                    node.style.outline = '';
                    if (node.getAttribute('style') === '') node.removeAttribute('style');
                }
            }
        });

        container.addEventListener('click', async function (e) {
            let element = e.target.closest('.delete-btn');
            if (element) {
                let section = element.closest('.section-item');
                let node = section._node;

                // Check if the element to be deleted is within an enabled area
                if (!node.closest('[data-astero-enabled]')) {
                    console.warn('Cannot delete: element is outside enabled area');
                    if (typeof displayToast !== 'undefined') {
                        displayToast('bg-warning', 'Cannot Delete', 'Cannot delete elements outside enabled areas.');
                    } else {
                        alert('Cannot delete: element is outside enabled area');
                    }
                    e.stopPropagation();
                    e.preventDefault();
                    return;
                }

                // Prevent deletion of elements with data-astero-enabled attribute
                if (node.hasAttribute('data-astero-enabled')) {
                    console.warn('Cannot delete: this element is an editable container and cannot be removed');
                    if (typeof displayToast !== 'undefined') {
                        displayToast(
                            'bg-warning',
                            'Cannot Delete',
                            'Cannot delete editable containers. This would break the editing functionality.'
                        );
                    } else {
                        alert('Cannot delete: this element is an editable container and cannot be removed');
                    }
                    e.stopPropagation();
                    e.preventDefault();
                    return;
                }

                // Show confirmation dialog
                const confirmed = await confirmDelete('section');
                if (!confirmed) {
                    e.stopPropagation();
                    e.preventDefault();
                    return;
                }

                node.remove();
                section.remove();

                // Refresh the section list
                self.loadSections();
                Astero.TreeList.loadComponents();

                e.stopPropagation();
                e.preventDefault();
            }
        });

        let sectionIn;
        let img = document.querySelector('.block-preview img');
        const hasPreview = !!img;
        let hideTimeout;

        // Show preview on mouseenter for each section item
        sectionsList.addEventListener(
            'mouseenter',
            function (e) {
                if (!hasPreview) return;
                let element = e.target.closest('li[data-type]');
                if (element) {
                    // Clear any pending hide timeout
                    if (hideTimeout) {
                        clearTimeout(hideTimeout);
                        hideTimeout = null;
                    }

                    if (sectionIn != element) {
                        let imgElement = element.querySelector('img');
                        if (imgElement) {
                            let src = imgElement.getAttribute('src');
                            sectionIn = element;
                            img.setAttribute('src', src);
                            img.style.display = 'block';
                        }
                    }
                }
            },
            true
        ); // Use capture phase to ensure we catch the event

        // Hide preview on mouseleave from the entire sections list
        sectionsList.addEventListener('mouseleave', function (e) {
            if (!hasPreview) return;
            // Add a small delay to prevent flickering when moving between sections
            hideTimeout = setTimeout(function () {
                sectionIn = null;
                img.setAttribute('src', '');
                img.style.display = 'none';
            }, 100);
        });

        // Also hide when moving between sections quickly
        sectionsList.addEventListener('mouseover', function (e) {
            if (!hasPreview) return;
            let element = e.target.closest('li[data-type]');
            if (!element && sectionIn) {
                // Mouse is over sections-list but not over a specific section item
                hideTimeout = setTimeout(function () {
                    sectionIn = null;
                    img.setAttribute('src', '');
                    img.style.display = 'none';
                }, 50);
            } else if (element) {
                // Clear any pending hide timeout
                if (hideTimeout) {
                    clearTimeout(hideTimeout);
                    hideTimeout = null;
                }

                if (sectionIn != element) {
                    let imageElement = element.querySelector('img');
                    if (imageElement) {
                        let src = imageElement.getAttribute('src');
                        sectionIn = element;
                        img.setAttribute('src', src);
                        img.style.display = 'block';
                    }
                }
            }
        });

        // Additional safety: hide preview when mouse leaves individual section items
        sectionsList.addEventListener(
            'mouseleave',
            function (e) {
                if (!hasPreview) return;
                let element = e.target.closest('li[data-type]');
                if (element && sectionIn === element) {
                    // Only hide if we're leaving the currently active section
                    hideTimeout = setTimeout(function () {
                        if (sectionIn === element) {
                            // Double check it's still the same element
                            sectionIn = null;
                            img.setAttribute('src', '');
                            img.style.display = 'none';
                        }
                    }, 150);
                }
            },
            true
        );

        // Move section up
        container.addEventListener('click', function (e) {
            let element = e.target.closest('.move-up-btn');
            if (element) {
                let section = element.closest('.section-item');
                let node = section._node;

                // Check if section can be moved up
                if (!node.previousElementSibling) {
                    e.preventDefault();
                    e.stopPropagation();
                    return;
                }

                Astero.Builder.moveNodeUp(node);
                self.loadSections();
                Astero.TreeList.loadComponents();

                // Scroll to the moved section in the canvas
                node.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'center' });

                e.preventDefault();
                e.stopPropagation();
            }
        });

        // Move section down
        container.addEventListener('click', function (e) {
            let element = e.target.closest('.move-down-btn');
            if (element) {
                let section = element.closest('.section-item');
                let node = section._node;

                // Check if section can be moved down
                if (!node.nextElementSibling) {
                    e.preventDefault();
                    e.stopPropagation();
                    return;
                }

                Astero.Builder.moveNodeDown(node);
                self.loadSections();
                Astero.TreeList.loadComponents();

                // Scroll to the moved section in the canvas
                node.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'center' });

                e.preventDefault();
                e.stopPropagation();
            }
        });

        let self = this;
        sectionsList.addEventListener('click', async function (e) {
            let element = e.target.closest('.add-section-btn');
            if (element) {
                let item = element.closest('li');
                const slug = item.dataset.type;
                const category = item.dataset.section;

                // Prefer Registry (supports Theme/Database/Builtin). Fall back to legacy storage.
                let section = Astero.Sections.get(slug);
                let html = section?.html;

                if ((!html || !section) && Astero.Registry) {
                    try {
                        // Try to resolve from Registry cache by category first
                        let block = null;
                        if (category) {
                            const cacheKey = 'section:' + category;
                            let list = Astero.Registry.cache?.[cacheKey] || [];
                            block = list.find((b) => b.slug === slug || b.id === slug) || null;

                            if (!block) {
                                await Astero.Registry.loadCategory('section', category);
                                list = Astero.Registry.cache?.[cacheKey] || [];
                                block = list.find((b) => b.slug === slug || b.id === slug) || null;
                            }
                        }

                        // If still not found (no category / not cached), ask providers directly
                        if (!block) {
                            const providers = Object.values(Astero.Registry.providers || {}).sort(
                                (a, b) => (b.priority ?? 0) - (a.priority ?? 0)
                            );
                            for (const provider of providers) {
                                block = await provider.getBlock?.(slug);
                                if (block) break;
                            }
                        }

                        if (block) {
                            html = await Astero.Registry.getBlockHtml(block);
                        } else if (section) {
                            // last resort: try Registry HTML fetch for legacy section object
                            html = await Astero.Registry.getBlockHtml(section);
                        }
                    } catch (err) {
                        console.error('[Navigation] Failed to fetch section HTML:', err);
                    }
                }

                if (!html) {
                    console.warn('Cannot add section: no HTML available for', slug);
                    if (typeof displayToast !== 'undefined') {
                        displayToast('bg-warning', 'Cannot Add Section', 'Section template not available.');
                    }
                    e.preventDefault();
                    return;
                }

                let node = generateElements(html)[0];
                let sectionType = node.tagName.toLowerCase();

                // Find the first editable area to add the section
                let editableArea = Astero.Builder.frameBody.querySelector('[data-astero-enabled]');
                if (!editableArea || editableArea.hasAttribute('data-astero-disabled')) {
                    // Show error message if no editable area found
                    console.warn('Cannot add section: no editable area found');
                    if (typeof displayToast !== 'undefined') {
                        displayToast(
                            'bg-warning',
                            'Cannot Add Section',
                            "No editable area found. Add 'data-astero-enabled' attribute to a container element."
                        );
                    } else {
                        alert(
                            'Cannot add section: No editable area found. Add data-astero-enabled attribute to a container element.'
                        );
                    }
                    e.preventDefault();
                    return;
                }

                // Add the section to the editable area instead of directly to body
                let afterSection = editableArea.querySelector(':scope > ' + sectionType + ':last-of-type');

                if (afterSection) {
                    afterSection.after(node);
                } else {
                    // For sections like nav/header, try to maintain proper order within the editable area
                    if (sectionType == 'nav') {
                        let existingNav = editableArea.querySelector(
                            ':scope > nav:first, :scope > header:last-of-type'
                        );
                        if (existingNav) {
                            existingNav.before(node);
                        } else {
                            editableArea.prepend(node);
                        }
                    } else if (sectionType != 'footer') {
                        let footer = editableArea.querySelector(':scope > footer:last-of-type');
                        if (footer) {
                            footer.before(node);
                        } else {
                            editableArea.append(node);
                        }
                    } else {
                        editableArea.append(node);
                    }
                }

                //node.click();
                Astero.Builder.selectNode(node);
                Astero.Builder.loadNodeComponent(node);
                /*
                Astero.Builder.frameHtml.animate({
                    scrollTop: node.offset().top
                }, 1000);

                delay(() => node.click(), 1000);
                */

                Astero.Undo.addMutation({
                    type: 'childList',
                    target: node.parentNode,
                    addedNodes: [node],
                    nextSibling: node.nextSibling,
                });

                self.loadSections();
                Astero.TreeList.loadComponents();
                Astero.TreeList.selectComponent(node);
                node.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'center' });

                e.preventDefault();
            }
        });

        // Click handler for blocks list add button
        document.querySelector('.blocks-list')?.addEventListener('click', async function (e) {
            let element = e.target.closest('.add-section-btn');
            if (element) {
                let item = element.closest('li');
                const slug = item.dataset.type;
                const category = item.dataset.section;

                // Prefer Registry (supports Theme/Database/Builtin). Fall back to legacy storage.
                let block = Astero.Blocks.get(slug);
                let html = block?.html;

                if ((!html || !block) && Astero.Registry) {
                    try {
                        let resolved = null;
                        if (category) {
                            const cacheKey = 'block:' + category;
                            let list = Astero.Registry.cache?.[cacheKey] || [];
                            resolved = list.find((b) => b.slug === slug || b.id === slug) || null;

                            if (!resolved) {
                                await Astero.Registry.loadCategory('block', category);
                                list = Astero.Registry.cache?.[cacheKey] || [];
                                resolved = list.find((b) => b.slug === slug || b.id === slug) || null;
                            }
                        }

                        if (!resolved) {
                            const providers = Object.values(Astero.Registry.providers || {}).sort(
                                (a, b) => (b.priority ?? 0) - (a.priority ?? 0)
                            );
                            for (const provider of providers) {
                                resolved = await provider.getBlock?.(slug);
                                if (resolved) break;
                            }
                        }

                        if (resolved) {
                            html = await Astero.Registry.getBlockHtml(resolved);
                        } else if (block) {
                            html = await Astero.Registry.getBlockHtml(block);
                        }
                    } catch (err) {
                        console.error('[Navigation] Failed to fetch block HTML:', err);
                    }
                }

                if (!html) {
                    console.warn('Cannot add block: no HTML available for', slug);
                    if (typeof displayToast !== 'undefined') {
                        displayToast('bg-warning', 'Cannot Add Block', 'Block template not available.');
                    }
                    e.preventDefault();
                    return;
                }

                let node = generateElements(html)[0];

                // Find the selected element or editable area to add the block
                let targetElement = Astero.Builder.selectedEl;
                if (!targetElement) {
                    targetElement = Astero.Builder.frameBody.querySelector('[data-astero-enabled]');
                }

                if (!targetElement) {
                    console.warn('Cannot add block: no target element found');
                    if (typeof displayToast !== 'undefined') {
                        displayToast('bg-warning', 'Cannot Add Block', 'Please select an element first.');
                    }
                    e.preventDefault();
                    return;
                }

                // Add block after the selected element or append to target
                if (Astero.Builder.selectedEl) {
                    Astero.Builder.selectedEl.after(node);
                } else {
                    targetElement.append(node);
                }

                Astero.Builder.selectNode(node);
                Astero.Builder.loadNodeComponent(node);

                Astero.Undo.addMutation({
                    type: 'childList',
                    target: node.parentNode,
                    addedNodes: [node],
                    nextSibling: node.nextSibling,
                });

                Astero.TreeList.loadComponents();
                Astero.TreeList.selectComponent(node);
                node.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'center' });

                e.preventDefault();
            }
        });

        // Click handler for components list add button
        document.querySelector('.components-list')?.addEventListener('click', function (e) {
            let element = e.target.closest('.add-section-btn');
            if (element) {
                let item = element.closest('li');
                let component = Astero.Components.get(item.dataset.type);
                if (!component || !component.html) return;

                let node = generateElements(component.html)[0];

                // Find the selected element or editable area to add the component
                let targetElement = Astero.Builder.selectedEl;
                if (!targetElement) {
                    targetElement = Astero.Builder.frameBody.querySelector('[data-astero-enabled]');
                }

                if (!targetElement) {
                    console.warn('Cannot add component: no target element found');
                    if (typeof displayToast !== 'undefined') {
                        displayToast('bg-warning', 'Cannot Add Component', 'Please select an element first.');
                    }
                    e.preventDefault();
                    return;
                }

                // Add component after the selected element or append to target
                if (Astero.Builder.selectedEl) {
                    Astero.Builder.selectedEl.after(node);
                } else {
                    targetElement.append(node);
                }

                Astero.Builder.selectNode(node);
                Astero.Builder.loadNodeComponent(node);

                Astero.Undo.addMutation({
                    type: 'childList',
                    target: node.parentNode,
                    addedNodes: [node],
                    nextSibling: node.nextSibling,
                });

                Astero.TreeList.loadComponents();
                Astero.TreeList.selectComponent(node);
                node.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'center' });

                e.preventDefault();
            }
        });

        container.addEventListener('click', function (e) {
            let element = e.target.closest('.properties-btn');
            if (element) {
                let section = element.closest('.section-item');
                let node = section._node;

                node.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'center' });
                Astero.Builder.selectNode(node);
                Astero.Builder.loadNodeComponent(node);

                e.preventDefault();
                e.stopPropagation();
            }
        });
    },

    getSections: function () {
        let sections = [];
        // Query only within enabled areas
        let enabledAreas = window.FrameDocument.body.querySelectorAll('[data-astero-enabled]');
        let sectionListQuery = 'section, header, footer, main, nav';
        let sectionList = [];

        if (enabledAreas.length > 0) {
            enabledAreas.forEach((area) => {
                sectionList.push(...area.querySelectorAll(sectionListQuery));
            });
        } else {
            // Fallback to the old behavior if no enabled areas are found, to avoid breaking pages without it.
            sectionList = window.FrameDocument.body.querySelectorAll(sectionListQuery);
        }

        sectionList.forEach(function (node, i) {
            let name = '';

            // Priority 1: id attribute
            if (node.id) {
                name = node.id;
            }
            // Priority 2: title attribute
            else if (node.title) {
                name = node.title;
            }
            // Priority 3: aria-label
            else if (node.ariaLabel) {
                name = node.ariaLabel;
            }
            // Priority 4: data-section attribute
            else if (node.dataset.section) {
                name = node.dataset.section;
            }
            // Priority 5: First heading text (h1-h6) inside the section
            else {
                let heading = node.querySelector('h1, h2, h3, h4, h5, h6');
                if (heading && heading.textContent) {
                    name = heading.textContent.trim().substring(0, 50);
                    if (heading.textContent.length > 50) name += '...';
                }
            }

            // Final fallback: element type + index
            if (!name) {
                let type = node.tagName.toLowerCase();
                name = type.charAt(0).toUpperCase() + type.slice(1) + ' ' + (i + 1);
            }

            let section = {
                name: name.replace(/[^\w\s.'-]+/g, ' ').trim(),
                id: node.id,
                type: node.tagName.toLowerCase(),
                node: node,
            };
            sections.push(section);
        });

        return sections;
    },

    loadComponents: function (sectionListItem, section, allowedComponents = {}) {
        let tree = [];
        getNodeTree(section, tree, allowedComponents);

        let html = drawComponentsTree(tree);
        const list = sectionListItem?.querySelector('ol');
        if (!list) return;
        list.replaceWith(html);
    },

    addSection: function (data) {
        let section = generateElements(tmpl('astero-section', data))[0];
        section._node = data.node;
        document.querySelector(this.selector).append(section);

        //this.loadComponents(section, data.node, this.allowedComponents);
    },

    loadSections: function () {
        let sections = this.getSections();
        let container = document.querySelector(this.selector);
        if (!container) return;

        container.replaceChildren();
        for (const i in sections) {
            this.addSection(sections[i]);
        }
    },
};

Astero.TreeList = {
    selector: '#tree-list',

    container: null,

    tree: [],

    idToNode: {},

    init: function () {
        // header move
        this.container = document.querySelector(this.selector);
        if (!this.container) return;
        let header = this.container.querySelector('.header');
        if (!header) return;
        let isDown = false;
        let offset = [0, 0];
        let self = this;

        header.addEventListener(
            'mousedown',
            function (e) {
                if (e.which == 1) {
                    //left click
                    isDown = true;
                    offset = [self.container.offsetLeft - e.clientX, self.container.offsetTop - e.clientY];
                }
            },
            true
        );

        document.addEventListener(
            'mouseup',
            function () {
                isDown = false;
            },
            true
        );

        document.addEventListener('mousemove', function (event) {
            if (isDown) {
                event.preventDefault();
                let left = Math.max(event.clientX + offset[0], 0);
                let top = Math.max(event.clientY + offset[1], 0);

                if (left >= 0 && left < window.innerWidth - self.container.clientWidth)
                    self.container.style.left = left + 'px';
                if (top >= 0 && top < window.innerHeight - self.container.clientHeight)
                    self.container.style.top = top + 'px';
            }
        });

        document.querySelector(this.selector)?.addEventListener('click', function (e) {
            let element = e.target.closest('li[data-component]');
            if (element) {
                const node = element._treeNode;
                node.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'center' });
                //node.click();
                Astero.Builder.selectNode(node);
                Astero.Builder.loadNodeComponent(node);

                document.querySelector(self.selector + ' .active')?.classList.remove('active');
                element.querySelector('label').classList.add('active');
            }
        });

        document.querySelector(this.selector)?.addEventListener('mousemove', function (e) {
            let element = e.target.closest('li[data-component]');
            if (element) {
                const node = element._treeNode;

                node.dispatchEvent(
                    new MouseEvent('mousemove', {
                        bubbles: true,
                        cancelable: true,
                    })
                );
            }
        });
    },

    selectComponent: function (node) {
        if (!this.container) return false;
        let id;
        for (const i in this.idToNode) {
            if (node == this.idToNode[i]) {
                id = i;
                break;
            }
        }

        if (id) {
            let element = document.getElementById('id' + id);

            this.container.querySelector('.active')?.classList.remove('active');
            //collapse all
            let checkboxes = this.container.querySelectorAll('input[type=checkbox]:checked');
            for (let i = 0, len = checkboxes.length; i < len; i++) {
                checkboxes[i].checked = false;
                let label = checkboxes[i].labels[0];
                if (label) {
                    label.classList.remove('active');
                }
            }

            //expand parents
            if (element) {
                let parent = element;
                let current = element;
                while ((parent = current.closest('li'))) {
                    current = parent.parentNode;
                    let input = parent.querySelector('input');
                    if (input && input.hasAttribute('type') && input.type == 'checkbox') {
                        input.checked = true;
                    }
                }

                element.checked = true;
                element.labels[0].classList.add('active');
                element.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'center' });
            }
        }

        return false;
    },

    loadComponents: function () {
        if (!this.container) return;
        Astero.SectionList.loadSections();

        this.tree = [];
        this.idToNode = {};

        const enabledAreas = window.FrameDocument.body.querySelectorAll('[data-astero-enabled]');

        if (enabledAreas.length > 0) {
            enabledAreas.forEach((area) => {
                getNodeTree(area, this.tree, {}, this.idToNode);
            });
        } else {
            // Fallback to scanning the whole body if no enabled areas are found
            getNodeTree(window.FrameDocument.body, this.tree, {}, this.idToNode);
        }

        let ol = drawComponentsTree(this.tree);
        let list = this.container.querySelector('.tree > ol');
        if (!list) return;
        list.replaceWith(ol);
    },
};

Astero.Breadcrumb = {
    tree: false,

    init: function () {
        this.tree = document.querySelector('.breadcrumb-navigator > .breadcrumb');
        if (!this.tree) return;
        this.tree.replaceChildren();

        this.tree.addEventListener('click', function (e) {
            let element = e.target.closest('.breadcrumb-item');
            if (element) {
                let node = element._node;
                if (node) {
                    //node.click();
                    Astero.Builder.selectNode(node);
                    Astero.Builder.loadNodeComponent(node);
                    node.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'center' });
                }

                e.preventDefault();
            }
        });

        let currentHoverNode;
        this.tree.addEventListener('mousemove', function (e) {
            if (e.target == currentHoverNode) return;
            currentHoverNode = e.target;

            let element = e.target.closest('.breadcrumb-item');
            if (element) {
                let node = element._node;

                node.dispatchEvent(
                    new MouseEvent('mousemove', {
                        bubbles: true,
                        cancelable: true,
                    })
                );
            }
        });
    },

    addElement: function (data, element) {
        let li = generateElements(tmpl('astero-breadcrumb-navigaton-item', data))[0];
        li._node = element;
        this.tree.prepend(li);
    },

    loadBreadcrumb: function (element) {
        this.tree.replaceChildren();
        let currentElement = element;

        while (currentElement.parentElement) {
            let elementType = Astero.Builder._getElementType(currentElement);
            let el = elementType[1].toLowerCase();

            this.addElement(
                {
                    name: el + ' ' + elementType[0],
                    className: 'el-' + el,
                },
                currentElement
            );

            // Stop if we hit the editable container or body/html
            if (currentElement.hasAttribute('data-astero-enabled')) break;
            if (currentElement.tagName === 'BODY' || currentElement.tagName === 'HTML') break;

            currentElement = currentElement.parentElement;
        }
    },
};
