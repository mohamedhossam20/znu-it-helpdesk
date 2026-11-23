<?php

return [
    'ticket_status' => [
        'new' => 'New',
        'acknowledged' => 'Acknowledged',
        'in_progress' => 'In progress',
        'on_hold' => 'On hold',
        'resolved' => 'Resolved',
        'reopened' => 'Reopened',
        'closed' => 'Closed',
        'cancelled' => 'Cancelled',
    ],
    'ticket_priority' => [
        'low' => 'Low',
        'normal' => 'Normal',
        'high' => 'High',
        'urgent' => 'Urgent',
    ],
    'ticket_source' => [
        'my_device' => 'My device / office',
        'other_location' => 'Other location',
        'qr' => 'Room QR',
        'admin' => 'Logged by IT',
    ],
    'room_type' => [
        'office' => 'Office',
        'lecture_hall' => 'Lecture hall',
        'lab' => 'Lab',
        'meeting_room' => 'Meeting room',
        'server_room' => 'Server room',
        'other' => 'Other',
    ],
    'asset_status' => [
        'in_service' => 'In service',
        'under_repair' => 'Under repair',
        'retired' => 'Retired',
    ],
    'user_role' => [
        'requester' => 'Requester',
        'technician' => 'Technician',
        'it_supervisor' => 'IT supervisor',
        'super_admin' => 'Super admin',
    ],
];
