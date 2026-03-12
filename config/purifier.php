<?php

/**
 * Ok, glad you are here
 * first we get a config instance, and set the settings
 * $config = HTMLPurifier_Config::createDefault();
 * $config->set('Core.Encoding', $this->config->get('purifier.encoding'));
 * $config->set('Cache.SerializerPath', $this->config->get('purifier.cachePath'));
 * if ( ! $this->config->get('purifier.finalize')) {
 *     $config->autoFinalize = false;
 * }
 * $config->loadArray($this->getConfig());
 *
 * You must NOT delete the default settings
 * anything in settings should be compacted with params that needed to instance HTMLPurifier_Config.
 *
 * @link http://htmlpurifier.org/live/configdoc/plain.html
 */

return [
    'encoding' => 'UTF-8',
    'finalize' => true,
    'ignoreNonStrings' => false,
    'cachePath' => storage_path('app/purifier'),
    'cacheFileMode' => 0755,
    'settings' => [
        'default' => [
            'HTML.Doctype' => 'HTML 4.01 Transitional',
            'HTML.Allowed' => 'div,b,strong,i,em,u,a[href|title],ul,ol,li,p[style],br,span[style],img[width|height|alt|src]',
            'CSS.AllowedProperties' => 'font,font-size,font-weight,font-style,font-family,text-decoration,padding-left,color,background-color,text-align',
            'AutoFormat.AutoParagraph' => false,
            'AutoFormat.RemoveEmpty' => true,
        ],
        'test' => [
            'Attr.EnableID' => 'true',
        ],
        'youtube' => [
            'HTML.SafeIframe' => 'true',
            'URI.SafeIframeRegexp' => '%^(http://|https://|//)(www.youtube.com/embed/|player.vimeo.com/video/)%',
        ],
        'custom_definition' => [
            'id' => 'html5-definitions',
            'rev' => 1,
            'debug' => false,
            'elements' => [
                // http://developers.whatwg.org/sections.html
                ['section', 'Block', 'Flow', 'Common'],
                ['nav',     'Block', 'Flow', 'Common'],
                ['article', 'Block', 'Flow', 'Common'],
                ['aside',   'Block', 'Flow', 'Common'],
                ['header',  'Block', 'Flow', 'Common'],
                ['footer',  'Block', 'Flow', 'Common'],

                // Content model actually excludes several tags, not modelled here
                ['address', 'Block', 'Flow', 'Common'],
                ['hgroup', 'Block', 'Required: h1 | h2 | h3 | h4 | h5 | h6', 'Common'],

                // http://developers.whatwg.org/grouping-content.html
                ['figure', 'Block', 'Optional: (figcaption, Flow) | (Flow, figcaption) | Flow', 'Common'],
                ['figcaption', 'Inline', 'Flow', 'Common'],

                // http://developers.whatwg.org/the-video-element.html#the-video-element
                ['video', 'Block', 'Optional: (source, Flow) | (Flow, source) | Flow', 'Common', [
                    'src' => 'URI',
                    'type' => 'Text',
                    'width' => 'Length',
                    'height' => 'Length',
                    'poster' => 'URI',
                    'preload' => 'Enum#auto,metadata,none',
                    'controls' => 'Bool',
                ]],
                ['source', 'Block', 'Flow', 'Common', [
                    'src' => 'URI',
                    'type' => 'Text',
                ]],

                // http://developers.whatwg.org/text-level-semantics.html
                ['s',    'Inline', 'Inline', 'Common'],
                ['var',  'Inline', 'Inline', 'Common'],
                ['sub',  'Inline', 'Inline', 'Common'],
                ['sup',  'Inline', 'Inline', 'Common'],
                ['mark', 'Inline', 'Inline', 'Common'],
                ['wbr',  'Inline', 'Empty', 'Core'],

                // http://developers.whatwg.org/edits.html
                ['ins', 'Block', 'Flow', 'Common', ['cite' => 'URI', 'datetime' => 'CDATA']],
                ['del', 'Block', 'Flow', 'Common', ['cite' => 'URI', 'datetime' => 'CDATA']],
            ],
            'attributes' => [
                ['iframe', 'allowfullscreen', 'Bool'],
                ['table', 'height', 'Text'],
                ['td', 'border', 'Text'],
                ['th', 'border', 'Text'],
                ['tr', 'width', 'Text'],
                ['tr', 'height', 'Text'],
                ['tr', 'border', 'Text'],
            ],
        ],
        'custom_attributes' => [
            ['a', 'target', 'Enum#_blank,_self,_target,_top'],
        ],
        'custom_elements' => [
            ['u', 'Inline', 'Inline', 'Common'],
        ],

        /*
         * Builder config - more permissive for page builder content.
         * Allows most HTML5 elements and data attributes for components.
         * Still strips dangerous elements like <script>, <object>, <embed>.
         */
        'builder' => [
            'HTML.Doctype' => 'HTML 4.01 Transitional',
            'HTML.Allowed' => 'div[class|id|style|data-*],section[class|id|style|data-*],article[class|id|style|data-*],header[class|id|style|data-*],footer[class|id|style|data-*],nav[class|id|style|data-*],aside[class|id|style|data-*],main[class|id|style|data-*],h1[class|id|style],h2[class|id|style],h3[class|id|style],h4[class|id|style],h5[class|id|style],h6[class|id|style],p[class|id|style],span[class|id|style],a[href|title|class|id|style|target|rel],ul[class|id|style],ol[class|id|style],li[class|id|style],br,hr[class|style],strong,b,em,i,u,s,small,mark,sub,sup,blockquote[class|id|style|cite],pre[class|id|style],code[class],img[src|alt|title|width|height|class|id|style|loading],figure[class|id|style],figcaption[class|id|style],video[src|poster|width|height|controls|autoplay|loop|muted|class|id|style],audio[src|controls|autoplay|loop|muted|class|id|style],source[src|type],picture[class|id|style],iframe[src|width|height|frameborder|allowfullscreen|class|id|style|loading],table[class|id|style],thead[class|style],tbody[class|style],tfoot[class|style],tr[class|style],th[class|style|colspan|rowspan|scope],td[class|style|colspan|rowspan],caption[class|style],form[class|id|style|action|method],input[type|name|value|placeholder|class|id|style|required|disabled|readonly|checked|min|max|step|pattern],textarea[name|placeholder|class|id|style|rows|cols|required|disabled|readonly],select[name|class|id|style|required|disabled|multiple],option[value|selected|disabled],optgroup[label|disabled],button[type|class|id|style|disabled],label[for|class|id|style],fieldset[class|id|style],legend[class|id|style],details[class|id|style|open],summary[class|id|style],address[class|id|style],time[datetime|class|id|style],svg[class|id|style|width|height|viewBox|fill|xmlns],path[d|fill|stroke|stroke-width|class],circle[cx|cy|r|fill|stroke|class],rect[x|y|width|height|fill|stroke|rx|ry|class],line[x1|y1|x2|y2|stroke|stroke-width|class],polyline[points|fill|stroke|class],polygon[points|fill|stroke|class],text[x|y|fill|class|style],g[class|id|style|transform],use[href|xlink:href|class|id|style],symbol[id|viewBox|class]',
            'CSS.AllowedProperties' => '*',
            'Attr.AllowedClasses' => null, // Allow all classes
            'Attr.EnableID' => true,
            'AutoFormat.AutoParagraph' => false,
            'AutoFormat.RemoveEmpty' => false,
            'HTML.SafeIframe' => true,
            'URI.SafeIframeRegexp' => '%^(https?:)?//(www\.youtube\.com/embed/|player\.vimeo\.com/video/|www\.google\.com/maps/embed|maps\.google\.com/|www\.openstreetmap\.org/)%',
        ],
    ],

];
