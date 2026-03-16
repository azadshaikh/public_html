<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines contain the default error messages used by
    | the validator class. Some of these rules have multiple versions such
    | as the size rules. Feel free to tweak each of these messages here.
    |
    */

    'accepted' => ':attribute को स्वीकार किया जाना चाहिए।',
    'accepted_if' => ':other :value होने पर :attribute को स्वीकार किया जाना चाहिए।',
    'active_url' => ':attribute एक मान्य URL नहीं है।',
    'after' => ':attribute :date के बाद की तारीख होनी चाहिए।',
    'after_or_equal' => ':attribute :date के बाद या उसके बराबर की तारीख होनी चाहिए।',
    'alpha' => ':attribute में केवल अक्षर होने चाहिए।',
    'alpha_dash' => ':attribute में केवल अक्षर, संख्याएँ, डैश और अंडरस्कोर होने चाहिए।',
    'alpha_num' => ':attribute में केवल अक्षर और संख्याएँ होनी चाहिए।',
    'any_of' => ':attribute अमान्य है।',
    'array' => ':attribute एक सरणी होनी चाहिए।',
    'ascii' => ':attribute में केवल एकल-बाइट अल्फ़ान्यूमेरिक वर्ण और प्रतीक होने चाहिए।',
    'before' => ':attribute :date से पहले की तारीख होनी चाहिए।',
    'before_or_equal' => ':attribute :date से पहले या उसके बराबर की तारीख होनी चाहिए।',
    'between' => [
        'array' => ':attribute में :min से :max आइटम होने चाहिए।',
        'file' => ':attribute :min से :max किलोबाइट के बीच होना चाहिए।',
        'numeric' => ':attribute :min से :max के बीच होना चाहिए।',
        'string' => ':attribute :min से :max वर्णों के बीच होना चाहिए।',
    ],
    'boolean' => ':attribute सही या गलत होना चाहिए।',
    'can' => ':attribute में एक अनधिकृत मान है।',
    'confirmed' => ':attribute की पुष्टि मेल नहीं खाती।',
    'contains' => ':attribute में एक आवश्यक मान गायब है।',
    'current_password' => 'पासवर्ड गलत है।',
    'date' => ':attribute एक मान्य तारीख नहीं है।',
    'date_equals' => ':attribute :date के बराबर तारीख होनी चाहिए।',
    'date_format' => ':attribute :format प्रारूप से मेल नहीं खाता।',
    'decimal' => ':attribute में :decimal दशमलव स्थान होने चाहिए।',
    'declined' => ':attribute को अस्वीकार किया जाना चाहिए।',
    'declined_if' => ':other :value होने पर :attribute को अस्वीकार किया जाना चाहिए।',
    'different' => ':attribute और :other अलग होने चाहिए।',
    'digits' => ':attribute :digits अंक होने चाहिए।',
    'digits_between' => ':attribute :min से :max अंकों के बीच होना चाहिए।',
    'dimensions' => ':attribute में अमान्य छवि आयाम हैं।',
    'distinct' => ':attribute फ़ील्ड का एक डुप्लिकेट मान है।',
    'doesnt_end_with' => ':attribute निम्नलिखित में से किसी के साथ समाप्त नहीं होना चाहिए: :values।',
    'doesnt_start_with' => ':attribute निम्नलिखित में से किसी के साथ शुरू नहीं होना चाहिए: :values।',
    'email' => ':attribute एक मान्य ईमेल पता होना चाहिए।',
    'ends_with' => ':attribute निम्नलिखित में से किसी के साथ समाप्त होना चाहिए: :values।',
    'enum' => 'चयनित :attribute अमान्य है।',
    'exists' => 'चयनित :attribute अमान्य है।',
    'extensions' => ':attribute में निम्नलिखित एक्सटेंशन में से एक होना चाहिए: :values।',
    'file' => ':attribute एक फ़ाइल होनी चाहिए।',
    'filled' => ':attribute फ़ील्ड का मान होना चाहिए।',
    'gt' => [
        'array' => ':attribute में :value से अधिक आइटम होने चाहिए।',
        'file' => ':attribute :value किलोबाइट से बड़ा होना चाहिए।',
        'numeric' => ':attribute :value से बड़ा होना चाहिए।',
        'string' => ':attribute :value वर्णों से बड़ा होना चाहिए।',
    ],
    'gte' => [
        'array' => ':attribute में :value या अधिक आइटम होने चाहिए।',
        'file' => ':attribute :value किलोबाइट से बड़ा या बराबर होना चाहिए।',
        'numeric' => ':attribute :value से बड़ा या बराबर होना चाहिए।',
        'string' => ':attribute :value वर्णों से बड़ा या बराबर होना चाहिए।',
    ],
    'hex_color' => ':attribute एक मान्य हेक्साडेसिमल रंग होना चाहिए।',
    'image' => ':attribute एक छवि होनी चाहिए।',
    'in' => 'चयनित :attribute अमान्य है।',
    'in_array' => ':attribute फ़ील्ड :other में मौजूद होना चाहिए।',
    'integer' => ':attribute एक पूर्णांक होना चाहिए।',
    'ip' => ':attribute एक मान्य IP पता होना चाहिए।',
    'ipv4' => ':attribute एक मान्य IPv4 पता होना चाहिए।',
    'ipv6' => ':attribute एक मान्य IPv6 पता होना चाहिए।',
    'json' => ':attribute एक मान्य JSON स्ट्रिंग होनी चाहिए।',
    'list' => ':attribute एक सूची होनी चाहिए।',
    'lowercase' => ':attribute लोअरकेस होना चाहिए।',
    'lt' => [
        'array' => ':attribute में :value से कम आइटम होने चाहिए।',
        'file' => ':attribute :value किलोबाइट से छोटा होना चाहिए।',
        'numeric' => ':attribute :value से छोटा होना चाहिए।',
        'string' => ':attribute :value वर्णों से छोटा होना चाहिए।',
    ],
    'lte' => [
        'array' => ':attribute में :value से अधिक आइटम नहीं होने चाहिए।',
        'file' => ':attribute :value किलोबाइट से छोटा या बराबर होना चाहिए।',
        'numeric' => ':attribute :value से छोटा या बराबर होना चाहिए।',
        'string' => ':attribute :value वर्णों से छोटा या बराबर होना चाहिए।',
    ],
    'mac_address' => ':attribute एक मान्य MAC पता होना चाहिए।',
    'max' => [
        'array' => ':attribute में :max से अधिक आइटम नहीं होने चाहिए।',
        'file' => ':attribute :max किलोबाइट से बड़ा नहीं होना चाहिए।',
        'numeric' => ':attribute :max से बड़ा नहीं होना चाहिए।',
        'string' => ':attribute :max वर्णों से बड़ा नहीं होना चाहिए।',
    ],
    'max_digits' => ':attribute में :max से अधिक अंक नहीं होने चाहिए।',
    'mimes' => ':attribute निम्नलिखित प्रकार की फ़ाइल होनी चाहिए: :values।',
    'mimetypes' => ':attribute निम्नलिखित प्रकार की फ़ाइल होनी चाहिए: :values।',
    'min' => [
        'array' => ':attribute में कम से कम :min आइटम होने चाहिए।',
        'file' => ':attribute कम से कम :min किलोबाइट होनी चाहिए।',
        'numeric' => ':attribute कम से कम :min होना चाहिए।',
        'string' => ':attribute कम से कम :min वर्ण होने चाहिए।',
    ],
    'min_digits' => ':attribute में कम से कम :min अंक होने चाहिए।',
    'missing' => ':attribute फ़ील्ड गायब होना चाहिए।',
    'missing_if' => ':other :value होने पर :attribute फ़ील्ड गायब होना चाहिए।',
    'missing_unless' => ':other :values में न होने पर :attribute फ़ील्ड गायब होना चाहिए।',
    'missing_with' => ':values मौजूद होने पर :attribute फ़ील्ड गायब होना चाहिए।',
    'missing_with_all' => ':values मौजूद होने पर :attribute फ़ील्ड गायब होना चाहिए।',
    'multiple_of' => ':attribute :value का गुणज होना चाहिए।',
    'not_in' => 'चयनित :attribute अमान्य है।',
    'not_regex' => ':attribute प्रारूप अमान्य है।',
    'numeric' => ':attribute एक संख्या होनी चाहिए।',
    'password' => [
        'letters' => ':attribute में कम से कम एक अक्षर होना चाहिए।',
        'mixed' => ':attribute में कम से कम एक अपरकेस और एक लोअरकेस अक्षर होना चाहिए।',
        'numbers' => ':attribute में कम से कम एक संख्या होनी चाहिए।',
        'symbols' => ':attribute में कम से कम एक प्रतीक होना चाहिए।',
        'uncompromised' => 'दिया गया :attribute डेटा लीक में दिखाई दिया है। कृपया एक अलग :attribute चुनें।',
    ],
    'present' => ':attribute फ़ील्ड मौजूद होना चाहिए।',
    'present_if' => ':other :value होने पर :attribute फ़ील्ड मौजूद होना चाहिए।',
    'present_unless' => ':other :values में न होने पर :attribute फ़ील्ड मौजूद होना चाहिए।',
    'present_with' => ':values मौजूद होने पर :attribute फ़ील्ड मौजूद होना चाहिए।',
    'present_with_all' => ':values मौजूद होने पर :attribute फ़ील्ड मौजूद होना चाहिए।',
    'prohibited' => ':attribute फ़ील्ड निषिद्ध है।',
    'prohibited_if' => ':other :value होने पर :attribute फ़ील्ड निषिद्ध है।',
    'prohibited_if_accepted' => ':other स्वीकृत होने पर :attribute फ़ील्ड निषिद्ध है।',
    'prohibited_if_declined' => ':other अस्वीकृत होने पर :attribute फ़ील्ड निषिद्ध है।',
    'prohibited_unless' => ':other :values में न होने पर :attribute फ़ील्ड निषिद्ध है।',
    'prohibits' => ':attribute फ़ील्ड :other के मौजूद होने को रोकता है।',
    'regex' => ':attribute प्रारूप अमान्य है।',
    'required' => ':attribute फ़ील्ड आवश्यक है।',
    'required_array_keys' => ':attribute फ़ील्ड में निम्नलिखित प्रविष्टियां होनी चाहिए: :values।',
    'required_if' => ':other :value होने पर :attribute फ़ील्ड आवश्यक है।',
    'required_if_accepted' => ':other स्वीकृत होने पर :attribute फ़ील्ड आवश्यक है।',
    'required_if_declined' => ':other अस्वीकृत होने पर :attribute फ़ील्ड आवश्यक है।',
    'required_unless' => ':other :values में न होने पर :attribute फ़ील्ड आवश्यक है।',
    'required_with' => ':values मौजूद होने पर :attribute फ़ील्ड आवश्यक है।',
    'required_with_all' => ':values मौजूद होने पर :attribute फ़ील्ड आवश्यक है।',
    'required_without' => ':values मौजूद न होने पर :attribute फ़ील्ड आवश्यक है।',
    'required_without_all' => ':values में से कोई भी मौजूद न होने पर :attribute फ़ील्ड आवश्यक है।',
    'same' => ':attribute और :other मेल खाने चाहिए।',
    'size' => [
        'array' => ':attribute में :size आइटम होने चाहिए।',
        'file' => ':attribute :size किलोबाइट होनी चाहिए।',
        'numeric' => ':attribute :size होना चाहिए।',
        'string' => ':attribute :size वर्ण होने चाहिए।',
    ],
    'starts_with' => ':attribute निम्नलिखित में से किसी के साथ शुरू होना चाहिए: :values।',
    'string' => ':attribute एक स्ट्रिंग होनी चाहिए।',
    'timezone' => ':attribute एक मान्य टाइमज़ोन होना चाहिए।',
    'unique' => ':attribute पहले से लिया जा चुका है।',
    'uploaded' => ':attribute अपलोड करने में विफल।',
    'uppercase' => ':attribute अपरकेस होना चाहिए।',
    'url' => ':attribute एक मान्य URL होना चाहिए।',
    'ulid' => ':attribute एक मान्य ULID होना चाहिए।',
    'uuid' => ':attribute एक मान्य UUID होना चाहिए।',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Here you may specify custom validation messages for attributes using the
    | convention "attribute.rule" to name the lines. This makes it quick to
    | specify a specific custom language line for a given attribute rule.
    |
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    |
    | The following language lines are used to swap our attribute placeholder
    | with something more reader friendly such as "E-Mail Address" instead
    | of "email". This simply helps us make our message more expressive.
    |
    */

    'attributes' => [],

];
