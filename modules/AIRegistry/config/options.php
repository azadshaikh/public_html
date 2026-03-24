<?php

declare(strict_types=1);

/**
 * Centralized option lists for the AIRegistry module.
 *
 * Single source of truth for capabilities, categories, and modalities.
 * Used by validation rules, form options, definition filters, and API output.
 *
 * Format: ['value' => 'Label'] — services convert to [['value' => '...', 'label' => '...']] as needed.
 */
return [

    'capabilities' => [
        'text' => 'Text',
        'vision' => 'Vision',
        'function_calling' => 'Function Calling',
        'streaming' => 'Streaming',
        'embeddings' => 'Embeddings',
        'audio_input' => 'Audio Input',
        'audio_output' => 'Audio Output',
        'image_generation' => 'Image Generation',
        'reasoning' => 'Reasoning',
        'web_search' => 'Web Search',
        'reranking' => 'Reranking',
    ],

    'categories' => [
        'programming' => 'Programming',
        'roleplay' => 'Roleplay',
        'marketing' => 'Marketing',
        'seo' => 'SEO',
        'technology' => 'Technology',
        'science' => 'Science',
        'translation' => 'Translation',
        'legal' => 'Legal',
        'finance' => 'Finance',
        'health' => 'Health',
        'trivia' => 'Trivia',
        'academia' => 'Academia',
    ],

    'input_modalities' => [
        'text' => 'Text',
        'image' => 'Image',
        'file' => 'File',
        'audio' => 'Audio',
        'video' => 'Video',
    ],

    'output_modalities' => [
        'text' => 'Text',
        'image' => 'Image',
        'audio' => 'Audio',
        'embeddings' => 'Embeddings',
    ],

];
