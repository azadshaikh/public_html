<!-- templates -->

<script id="astero-input-textinput" type="text/html">
    <div>
        <input name="{%=key%}" type="text" class="form-control form-control-sm" />
    </div>
</script>

<script id="astero-input-textareainput" type="text/html">
    <div>
        <textarea name="{%=key%}" {% if (typeof rows !== 'undefined') { %} rows="{%=rows%}" {% } else { %} rows="3" {% } %} class="form-control form-control-sm"></textarea>
    </div>
</script>

<script id="astero-input-checkboxinput" type="text/html">
    <div class="form-check{% if (typeof className !== 'undefined') { %} {%=className%}{% } %}">
        <input name="{%=key%}" class="form-check-input" type="checkbox" id="{%=key%}_check">
        <label class="form-check-label" for="{%=key%}_check">{% if (typeof text !== 'undefined') { %} {%=text%} {% } %}</label>
    </div>
</script>

<script id="astero-input-radioinput" type="text/html">
    <div>
        {% for ( var i = 0; i < options.length; i++ ) { %}
        <label class="form-check-input {% if (typeof inline !== 'undefined' && inline == true) { %}custom-control-inline{% } %}" title="{%=options[i].title%}">
            <input name="{%=key%}" class="form-check-input" type="radio" value="{%=options[i].value%}" id="{%=key%}{%=i%}" {%if (options[i].checked) { %}checked="{%=options[i].checked%}" {% } %}>
            <label class="form-check-label" for="{%=key%}{%=i%}">{%=options[i].text%}</label>
        </label>
        {% } %}
    </div>
</script>

<script id="astero-input-radiobuttoninput" type="text/html">
    <div class="btn-group btn-group-sm {%if (extraclass) { %}{%=extraclass%}{% } %} clearfix" role="group">
        {% var namespace = 'rb-' + Math.floor(Math.random() * 100); %}
        {% for ( var i = 0; i < options.length; i++ ) { %}
        <input name="{%=key%}" class="btn-check" type="radio" value="{%=options[i].value%}" id="{%=namespace%}{%=key%}{%=i%}" {%if (options[i].checked) { %}checked="{%=options[i].checked%}" {% } %} autocomplete="off">
        <label class="btn btn-outline-secondary {%if (options[i].extraclass) { %}{%=options[i].extraclass%}{% } %}" for="{%=namespace%}{%=key%}{%=i%}" title="{%=options[i].title%}">
            {%if (options[i].icon) { %}<i class="{%=options[i].icon%}"></i>{% } %}
            {%=options[i].text%}
        </label>
        {% } %}
    </div>
</script>

<script id="astero-input-toggle" type="text/html">
    <div class="form-check form-switch {% if (typeof className !== 'undefined') { %} {%=className%}{% } %}">
        <input type="checkbox" name="{%=key%}" value="{%=on%}" {%if (off !== null) { %} data-value-off="{%=off%}" {% } %} {%if (on !== null) { %} data-value-on="{%=on%}" {% } %} class="form-check-input" role="switch" id="{%=key%}">
        <label class="form-check-label" for="{%=key%}"></label>
    </div>
</script>

<script id="astero-input-header" type="text/html">
    <h6 class="header">{%=header%}</h6>
</script>

<script id="astero-input-select" type="text/html">
    <div>
        <select class="form-select form-select-sm" name="{%=key%}">
            {% var optgroup = false; for ( var i = 0; i < options.length; i++ ) { %}
            {% if (options[i].optgroup) { %}
            {% if (optgroup) { %}</optgroup>{% } %}
            <optgroup label="{%=options[i].optgroup%}">
                {% optgroup = true; } else { %}
                <option value="{%=options[i].value%}" {% for (attr in options[i]) { if (attr != "value" && attr != "text") { %}{%=attr%}={%=options[i][attr]%}{% } } %}>{%=options[i].text%}</option>
                {% } } %}
        </select>
    </div>
</script>

<script id="astero-input-icon-select" type="text/html">
    <div class="input-list-select">
        <div class="elements">
            <div class="row">
                {% for ( var i = 0; i < options.length; i++ ) { %}
                <div class="col">
                    <div class="element">
                        {%=options[i].value%}
                        <label>{%=options[i].text%}</label>
                    </div>
                </div>
                {% } %}
            </div>
        </div>
    </div>
</script>

<script id="astero-input-html-list-select" type="text/html">
    <div class="input-html-list-select">
        <div class="current-element"></div>
        <div class="popup">
            <select class="form-select form-select-sm">
                {% var optgroup = false; for ( var i = 0; i < options.length; i++ ) { %}
                {% if (options[i].optgroup) { %}
                {% if (optgroup) { %}</optgroup>{% } %}
                <optgroup label="{%=options[i].optgroup%}">
                    {% optgroup = true; } else { %}
                    <option value="{%=options[i].value%}">{%=options[i].text%}</option>
                    {% } } %}
            </select>
            <div class="search">
                <input class="form-control form-control-sm search" placeholder="Search elements" type="text">
                <button class="clear-backspace">
                    <i class="ri-close-line"></i>
                </button>
            </div>
            <div class="elements">
                {%=elements%}
            </div>
        </div>
    </div>
</script>

<script id="astero-input-html-list-dropdown" type="text/html">
    <div class="input-html-list-select" {% if (typeof id !== "undefined") { %} id={%=id%} {% } %}>
        <div class="current-element"></div>
        <div class="popup">
            <select class="form-select form-select-sm">
                {% var optgroup = false; for ( var i = 0; i < options.length; i++ ) { %}
                {% if (options[i].optgroup) { %}
                {% if (optgroup) { %}</optgroup>{% } %}
                <optgroup label="{%=options[i].optgroup%}">
                    {% optgroup = true; } else { %}
                    <option value="{%=options[i].value%}">{%=options[i].text%}</option>
                    {% } } %}
            </select>
            <div class="search">
                <input class="form-control search" placeholder="Search elements" type="text">
                <button class="clear-backspace">
                    <i class="ri-close-line"></i>
                </button>
            </div>
            <div class="elements">
                {%=elements%}
            </div>
        </div>
    </div>
</script>

<script id="astero-input-dateinput" type="text/html">
    <div>
        <input name="{%=key%}" type="date" class="form-control form-control-sm" {% if (typeof min_date === 'undefined') { %} min="{%=min_date%}" {% } %} {% if (typeof max_date === 'undefined') { %} max="{%=max_date%}" {% } %} />
    </div>
</script>

<script id="astero-input-listinput" type="text/html">
    <div class="sections-container">
        {% for ( var i = 0; i < options.length; i++ ) { %}
        <div class="section-item" draggable="true">
            <div class="controls">
                <div class="handle"></div>
                <div class="info">
                    <div class="name">{%=options[i].name%}
                        <div class="type">{%=options[i].type%}</div>
                    </div>
                </div>
                <div class="buttons">
                    <a class="delete-btn" href="" title="Remove section"><i class="ri-delete-bin-line text-danger"></i></a>
                </div>
            </div>


            <input class="header_check" type="checkbox" id="section-components-{%=options[i].suffix%}">

            <label for="section-components-{%=options[i].suffix%}">
                <div class="header-arrow">
                    <i class="ri-arrow-down-s-line icon-minus"></i>
                    <i class="ri-arrow-right-s-line icon-plus"></i>
                </div>
            </label>

            <div class="tree">
                {%=options[i].name%}
            </div>
        </div>




        {% } %}


        {% if (typeof hide_remove === 'undefined') { %}
        <div class="mt-3">

            <button class="btn btn-sm btn-outline-primary btn-new">
                <i class="ri-add-line la-lg"></i> Add new
            </button>

        </div>
        {% } %}

    </div>
</script>

<script id="astero-input-grid" type="text/html">
    <div class="row">
        <div class="col-6">

            <label>Extra small</label>
            <select class="form-select form-select-sm" name="col" autocomplete="off">

                <option value="">None</option>
                {% for ( var i = 1; i <= 12; i++ ) { %}
                <option value="{%=i%}" {% if ((typeof col !== 'undefined') && col == i) { %} selected {% } %}>{%=i%}</option>
                {% } %}

            </select>
        </div>


        <div class="col-6">
            <label>Small</label>
            <select class="form-select form-select-sm" name="col-sm" autocomplete="off">

                <option value="">None</option>
                {% for ( var i = 1; i <= 12; i++ ) { %}
                <option value="{%=i%}" {% if ((typeof col_sm !== 'undefined') && col_sm == i) { %} selected {% } %}>{%=i%}</option>
                {% } %}

            </select>
        </div>

        <div class="col-6">
            <label>Medium</label>
            <select class="form-select form-select-sm" name="col-md" autocomplete="off">

                <option value="">None</option>
                {% for ( var i = 1; i <= 12; i++ ) { %}
                <option value="{%=i%}" {% if ((typeof col_md !== 'undefined') && col_md == i) { %} selected {% } %}>{%=i%}</option>
                {% } %}

            </select>
        </div>

        <div class="col-6">
            <label>Large</label>
            <select class="form-select form-select-sm" name="col-lg" autocomplete="off">

                <option value="">None</option>
                {% for ( var i = 1; i <= 12; i++ ) { %}
                <option value="{%=i%}" {% if ((typeof col_lg !== 'undefined') && col_lg == i) { %} selected {% } %}>{%=i%}</option>
                {% } %}

            </select>
        </div>


        <div class="col-6">
            <label>Extra large </label>
            <select class="form-select form-select-sm" name="col-xl" autocomplete="off">

                <option value="">None</option>
                {% for ( var i = 1; i <= 12; i++ ) { %}
                <option value="{%=i%}" {% if ((typeof col_xl !== 'undefined') && col_xl == i) { %} selected {% } %}>{%=i%}</option>
                {% } %}

            </select>
        </div>

        <div class="col-6">
            <label>Extra extra large</label>
            <select class="form-select form-select-sm" name="col-xxl" autocomplete="off">

                <option value="">None</option>
                {% for ( var i = 1; i <= 12; i++ ) { %}
                <option value="{%=i%}" {% if ((typeof col_xxl !== 'undefined') && col_xxl == i) { %} selected {% } %}>{%=i%}</option>
                {% } %}

            </select>
        </div>

        {% if (typeof hide_remove === 'undefined') { %}
        <div class="col-12">

            <button class="btn btn-sm btn-outline-light text-danger">
                <i class="ri-delete-bin-line bi-trash la-lg"></i> Remove
            </button>

        </div>
        {% } %}

    </div>
</script>

<script id="astero-input-textvalue" type="text/html">
    <div class="row">
        <div class="col-6 mb-1">
            <label>Value</label>
            <input name="value" type="text" value="{%= typeof value !== 'undefined' ? value : '' %}" class="form-control form-control-sm" autocomplete="off" />
        </div>

        <div class="col-6 mb-1">
            <label>Text</label>
            <input name="text" type="text" value="{%= typeof text !== 'undefined' ? text : '' %}" class="form-control form-control-sm" autocomplete="off" />
        </div>

        {% if (typeof hide_remove === 'undefined') { %}
        <div class="col-12">

            <button class="btn btn-sm btn-outline-light text-danger">
                <i class="ri-delete-bin-line bi-lg"></i> Remove
            </button>

        </div>
        {% } %}

    </div>
</script>

<script id="astero-input-rangeinput" type="text/html">
    <div class="input-range">

        <input name="{%=key%}" type="range" min="{%=min%}" max="{%=max%}" step="{%=step%}" class="form-range" data-input-value />
        <input name="{%=key%}" type="number" min="{%=min%}" max="{%=max%}" step="{%=step%}" class="form-control form-control-sm form-control-xs" data-input-value />
    </div>
</script>

<script id="astero-input-imageinput" type="text/html">
    <div>
        <input name="{%=key%}" type="text" class="form-control form-control-sm" />
        <input name="file" type="file" class="form-control form-control-sm" />
    </div>
</script>

<script id="astero-input-imageinput-gallery" type="text/html">
    <div class="image-input">
        <img id="thumb-{%=key%}" class="img-thumbnail p-0" data-target-input="#input-{%=key%}" data-target-thumb="#thumb-{%=key%}" style="cursor:pointer" src="{{ get_placeholder_image_url() }}" width="225" height="225">
        <input name="{%=key%}" type="text" class="form-control form-control-sm mt-1" id="input-{%=key%}" />
        <button name="button" type="button" class="btn n bg-primary-pale btn-sm btn-sm mt-2 w-100" data-target-input="#input-{%=key%}" data-target-thumb="#thumb-{%=key%}"><i class="ri-image-line la-lg me-1"></i><span>Set image</span></button>
    </div>
</script>

<script id="astero-input-videoinput-gallery" type="text/html">
    <div class="video-input">
        <video id="thumb-v{%=key%}" class="img-thumbnail p-0" data-target-input="#input-v{%=key%}" data-target-thumb="#thumb-v{%=key%}" style="cursor:pointer" src="" width="225" height="225" playsinline loop muted controls poster="{{ get_placeholder_image_url() }}"></video>
        <input name="v{%=key%}" type="text" class="form-control form-control-sm mt-1" id="input-v{%=key%}" />
        <button name="button" type="button" class="btn bg-primary-pale btn-sm mt-2 w-100" data-target-input="#input-v{%=key%}" data-target-thumb="#thumb-v{%=key%}"><i class="ri-play-circle-line la-lg"></i><span>Set video</span></button>
    </div>
</script>

<script id="astero-input-colorinput-native" type="text/html">
    <div>
        <input name="{%=key%}" {%  if (typeof palette !== 'undefined') { %} list="{%=key%}-color-palette" {% } %} type="color" {% if (typeof value !== 'undefined' && value != false) { %} value="{%=value%}" {% } %} pattern="#[a-f0-9]{6}" class="form-control form-control-sm form-control-color" />
        {%  if (typeof palette !== 'undefined') { %}
        <datalist id="{%=key%}-color-palette">
            {% for (const color in palette) { %}
            <option>{%=palette[color]%}</option>
            {% } %}
            {% } %}
    </div>
</script>

<script id="astero-input-colorinput" type="text/html">
    <div class="clr-field" style="{% if (typeof value !== 'undefined' && value != false) { %}color: {%=value%}" {% } %}">
        <button type="button" aria-labelledby="clr-open-label"></button>
        <input name="{%=key%}" type="text" {% if (typeof value !== 'undefined' && value != false) { %} value="{%=value%}" {% } %} class="coloris form-control form-control-sm" />
    </div>
</script>

<script id="astero-input-bootstrap-color-picker-input" type="text/html">
    <div>
        <div id="cp2" class="input-group" title="Using input value">
            <input name="{%=key%}" type="text" {% if (typeof value !== 'undefined' && value != false) { %} value="{%=value%}" {% } %} class="form-control form-control-sm" />
            <span class="input-group-append">
                <span class="input-group-text colorpicker-input-addon"><i></i></span>
            </span>
        </div>
    </div>
</script>

<script id="astero-input-numberinput" type="text/html">
    <div>
        <input name="{%=key%}" type="number" value="{%= typeof value !== 'undefined' ? value : '' %}" {% if (typeof min !== 'undefined' && min != false) { %}min="{%=min%}" {% } %} {% if (typeof max !== 'undefined' && max != false) { %}max="{%=max%}" {% } %} {% if (typeof step !== 'undefined' && step != false) { %}step="{%=step%}" {% } %} class="form-control form-control-sm" />
    </div>
</script>

<script id="astero-input-button" type="text/html">
    <div>
        <button class="btn btn-sm {% if (typeof className !== 'undefined') { %} {%=className%} {% } else { %}btn-outline-primary{% } %}">
            <i class="bi  {% if (typeof icon !== 'undefined') { %} {%=icon%} {% } else { %} bi-plus {% } %} bi-lg"></i> {%=text%}
        </button>
    </div>
</script>

<script id="astero-input-cssunitinput" type="text/html">
    <div class="input-group css-unit" id="cssunit-{%=key%}">
        <input name="number" type="number" {% if (typeof value !== 'undefined' && value != false) { %} value="{%=value%}" {% } %} {% if (typeof min !== 'undefined' && min != false) { %}min="{%=min%}" {% } %} {% if (typeof max !== 'undefined' && max != false) { %}max="{%=max%}" {% } %} {% if (typeof step !== 'undefined' && step != false) { %}step="{%=step%}" {% } %} class="form-control form-control-sm" />
        <select class="form-select form-select-sm small-arrow" name="unit">
            <option value="em">em</option>
            <option value="rem">rem</option>
            <option value="px">px</option>
            <option value="%">%</option>
            <option value="vw">vw</option>
            <option value="vh">vh</option>
            <option value="ex">ex</option>
            <option value="ch">ch</option>
            <option value="cm">cm</option>
            <option value="mm">mm</option>
            <option value="in">in</option>
            <option value="pt">pt</option>
            <option value="auto">auto</option>
            <option value="">-</option>
        </select>
    </div>
</script>

<script id="astero-breadcrumb-navigaton-item" type="text/html">
    <li class="breadcrumb-item"><a href="#" {% if (typeof className !== 'undefined') { %}class="{%=className%}" {% } %}>{%=name%}</a></li>
</script>

<script id="astero-input-sectioninput" type="text/html">
    <div>
        {% var namespace = '-' + Math.floor(Math.random() * 1000); %}
        <label class="header" data-header="{%=key%}" for="header_{%=key%}{%=namespace%}" {% if (typeof group !== 'undefined' && group != null) { %}data-group="{%=group%}" {% } %}>
            <span>{%=header%}</span>
            <div class="header-arrow">
                <i class="ri-add-line icon-plus"></i>
                <i class="ri-subtract-line icon-minus"></i>
            </div>
        </label>
        <input class="header_check" type="checkbox" {% if (typeof expanded !== 'undefined' && expanded == false) { %} {% } else { %}checked="true" {% } %} id="header_{%=key%}{%=namespace%}">
        <div class="section row" data-section="{%=key%}" {% if (typeof group !== 'undefined' && group != null) { %}data-group="{%=group%}" {% } %}></div>
    </div>
</script>

<script id="astero-property" type="text/html">
    <div class="mb-3 {% if (typeof col !== 'undefined' && col != false) { %} col-sm-{%=col%} {% } else { %}row{% } %} {% if (typeof inline !== 'undefined' && inline == true) { %}inline{% } %} " data-key="{%=key%}" {% if (typeof group !== 'undefined' && group != null) { %}data-group="{%=group%}" {% } %}>

        {% if (typeof name !== 'undefined' && name != false) { %}<label class="{% if (typeof inline === 'undefined' ) { %}col-sm-4{% } %} form-label" for="input-model">{%=name%}</label>{% } %}

        <div class="{% if (typeof inline === 'undefined') { %}col-sm-{% if (typeof name !== 'undefined' && name != false) { %}8{% } else { %}12{% } } %} input"></div>

    </div>
</script>

<script id="astero-input-autocompletelist" type="text/html">
    <div>
        <input name="{%=key%}" type="text" class="form-control form-control-sm" />
        <div class="form-control autocomplete-list" style="min-height: 150px; overflow: auto;">
        </div>
    </div>
</script>

<script id="astero-input-tagsinput" type="text/html">
    <div>
        <div class="form-control tags-input p-0" style="height:auto;">
            <input name="{%=key%}" type="text" class="form-control form-control-sm" style="border:none;min-width:60px;" />
        </div>
    </div>
</script>

<script id="astero-input-noticeinput" type="text/html">
    <div>
        <div class="alert alert-dismissible fade show alert-{%=type%}" role="alert">
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            <strong class="d-block mb-1">{%=title%}</strong>

            {%=text%}
        </div>
    </div>
</script>

<script id="astero-section" type="text/html">
    <div class="section-item">
        <div class="controls">
            <div class="info">
                <div class="name">{%=name%}</div>
            </div>
            <div class="buttons">
                <a class="move-up-btn" href="#" title="Move up"><i class="ri-arrow-up-s-line"></i></a>
                <a class="move-down-btn" href="#" title="Move down"><i class="ri-arrow-down-s-line"></i></a>
                <a class="properties-btn" href="#" title="Properties"><i class="ri-settings-3-line"></i></a>
                <a class="delete-btn" href="#" title="Remove section"><i class="ri-delete-bin-line"></i></a>
            </div>
        </div>
    </div>
</script>

<!--end templates -->
