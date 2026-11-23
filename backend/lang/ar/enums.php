<?php

return [
    'ticket_status' => [
        'new' => 'جديد',
        'acknowledged' => 'تم الاستلام',
        'in_progress' => 'جاري العمل',
        'on_hold' => 'معلّق',
        'resolved' => 'تم الحل',
        'reopened' => 'أعيد فتحه',
        'closed' => 'مغلق',
        'cancelled' => 'ملغي',
    ],
    'ticket_priority' => [
        'low' => 'منخفضة',
        'normal' => 'عادية',
        'high' => 'عالية',
        'urgent' => 'عاجلة',
    ],
    'ticket_source' => [
        'my_device' => 'جهازي / مكتبي',
        'other_location' => 'مكان آخر',
        'qr' => 'QR القاعة',
        'admin' => 'سجّله فريق IT',
    ],
    'room_type' => [
        'office' => 'مكتب',
        'lecture_hall' => 'قاعة محاضرات',
        'lab' => 'معمل',
        'meeting_room' => 'قاعة اجتماعات',
        'server_room' => 'غرفة سيرفرات',
        'other' => 'أخرى',
    ],
    'asset_status' => [
        'in_service' => 'في الخدمة',
        'under_repair' => 'تحت الصيانة',
        'retired' => 'خارج الخدمة',
    ],
    'user_role' => [
        'requester' => 'مقدم طلب',
        'technician' => 'فني',
        'it_supervisor' => 'مشرف IT',
        'super_admin' => 'مدير النظام',
    ],
];
