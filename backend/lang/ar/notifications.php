<?php

return [
    'greeting' => 'أهلًا :name،',
    'salutation' => 'فريق الدعم الفني',
    'open_ticket' => 'فتح التذكرة',

    'new_ticket' => [
        'title' => 'طلب جديد :number',
        'body' => ':problem — :place (مقدم الطلب: :requester)',
    ],
    'new_urgent_ticket' => [
        'title' => 'طلب عاجل :number',
        'body' => ':problem — :place. محاضرة أو عمل متوقف بانتظاركم (مقدم الطلب: :requester)',
    ],
    'logged_for_you' => [
        'title' => 'سجّلنا طلبك :number',
        'body' => 'فريق الدعم الفني سجّل لك طلبًا: :problem — :place',
    ],
    'assigned_to_you' => [
        'title' => 'تذكرة جديدة معك :number',
        'body' => ':problem — :place',
    ],
    'picked_up' => [
        'title' => 'تم استلام طلبك :number',
        'body' => ':technician استلم طلبك (:problem) وسيتابعه.',
    ],
    'on_hold' => [
        'title' => 'طلبك :number معلّق',
        'body' => 'السبب: :reason',
    ],
    'resolved' => [
        'title' => 'تم حل طلبك :number',
        'body' => ':problem — من فضلك أكّد أن المشكلة انتهت أو أعد فتح الطلب.',
    ],
    'closed_by_it' => [
        'title' => 'تم إغلاق طلبك :number',
        'body' => ':problem',
    ],
    'cancelled_by_it' => [
        'title' => 'تم إلغاء طلبك :number',
        'body' => ':reason',
    ],
    'reopened' => [
        'title' => 'أعيد فتح :number',
        'body' => 'مقدم الطلب يقول: :reason',
    ],
    'cancelled_by_requester' => [
        'title' => 'ألغى مقدم الطلب :number',
        'body' => ':problem — :place',
    ],
    'reply_from_it' => [
        'title' => 'رد جديد على طلبك :number',
        'body' => ':author: :excerpt',
    ],
    'reply_from_requester' => [
        'title' => 'رد من مقدم الطلب :number',
        'body' => ':author: :excerpt',
    ],
    'internal_note' => [
        'title' => 'ملاحظة داخلية على :number',
        'body' => ':author: :excerpt',
    ],
    'overdue' => [
        'title' => 'تذكرة متأخرة :number',
        'body' => ':problem — :place. تجاوزت الموعد المحدد.',
    ],
];
