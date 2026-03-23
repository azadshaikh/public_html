<?php

return [
    'name' => 'Helpdesk',
    'status_options' => [
        ['label' => 'Active', 'value' => 'active'],
        ['label' => 'Inactive', 'value' => 'inactive'],
    ],
    'visibility_options' => [
        ['label' => 'Public', 'value' => 'public'],
        ['label' => 'Private', 'value' => 'private'],
    ],
    'ticket_length_options' => [
        ['label' => '1 (0)', 'value' => 1],
        ['label' => '2 (00)', 'value' => 2],
        ['label' => '3 (000)', 'value' => 3],
        ['label' => '4 (0000)', 'value' => 4],
        ['label' => '5 (00000)', 'value' => 5],
        ['label' => '6 (000000)', 'value' => 6],
    ],
    'priority_options' => [
        ['label' => 'Low', 'value' => 'low'],
        ['label' => 'Medium', 'value' => 'medium'],
        ['label' => 'High', 'value' => 'high'],
        ['label' => 'Critical', 'value' => 'critical'],
    ],
    'ticket_status_options' => [
        ['label' => 'Open', 'value' => 'open'],
        ['label' => 'Pending', 'value' => 'pending'],
        ['label' => 'Resolved', 'value' => 'resolved'],
        ['label' => 'On Hold', 'value' => 'on_hold'],
        ['label' => 'Closed', 'value' => 'closed'],
        ['label' => 'Cancelled', 'value' => 'cancelled'],
    ],
];
