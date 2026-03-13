<?php

return [
    'sections' => [
        'todos_workspace' => [
            'label' => 'Todos',
            'weight' => 220,
            'area' => 'modules',
            'show_label' => true,
            'items' => [
                'todo_tasks' => [
                    'label' => 'Tasks',
                    'route' => 'todos.index',
                    'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="4" width="14" height="16" rx="2"></rect><path d="m9 10 1.5 1.5L15 7"></path><path d="M9 15h6"></path></svg>',
                    'active_patterns' => ['todos.*'],
                ],
            ],
        ],
    ],
];
