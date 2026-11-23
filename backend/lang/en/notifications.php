<?php

return [
    'greeting' => 'Hello :name,',
    'salutation' => 'IT Support',
    'open_ticket' => 'Open ticket',

    'new_ticket' => [
        'title' => 'New ticket :number',
        'body' => ':problem — :place (requested by :requester)',
    ],
    'new_urgent_ticket' => [
        'title' => 'Urgent ticket :number',
        'body' => ':problem — :place. A class or work is waiting (requested by :requester)',
    ],
    'logged_for_you' => [
        'title' => 'We logged your request :number',
        'body' => 'IT logged a request for you: :problem — :place',
    ],
    'assigned_to_you' => [
        'title' => 'New ticket for you :number',
        'body' => ':problem — :place',
    ],
    'picked_up' => [
        'title' => 'Your request :number was picked up',
        'body' => ':technician is handling your request (:problem).',
    ],
    'on_hold' => [
        'title' => 'Your request :number is on hold',
        'body' => 'Reason: :reason',
    ],
    'resolved' => [
        'title' => 'Your request :number was resolved',
        'body' => ':problem — please confirm it is fixed or reopen the request.',
    ],
    'closed_by_it' => [
        'title' => 'Your request :number was closed',
        'body' => ':problem',
    ],
    'cancelled_by_it' => [
        'title' => 'Your request :number was cancelled',
        'body' => ':reason',
    ],
    'reopened' => [
        'title' => ':number was reopened',
        'body' => 'The requester says: :reason',
    ],
    'cancelled_by_requester' => [
        'title' => 'Requester cancelled :number',
        'body' => ':problem — :place',
    ],
    'reply_from_it' => [
        'title' => 'New reply on your request :number',
        'body' => ':author: :excerpt',
    ],
    'reply_from_requester' => [
        'title' => 'Requester replied on :number',
        'body' => ':author: :excerpt',
    ],
    'internal_note' => [
        'title' => 'Internal note on :number',
        'body' => ':author: :excerpt',
    ],
    'overdue' => [
        'title' => 'Overdue ticket :number',
        'body' => ':problem — :place. It is past its due time.',
    ],
];
