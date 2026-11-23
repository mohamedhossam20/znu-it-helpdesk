<?php

/*
 * Arabic messages for the validation rules this app uses. Rules missing here
 * fall back to English (APP_FALLBACK_LOCALE).
 */
return [
    'accepted' => 'يجب قبول :attribute.',
    'after_or_equal' => 'يجب أن يكون :attribute تاريخًا بعد أو يساوي :date.',
    'alpha_dash' => 'يجب أن يحتوي :attribute على حروف وأرقام وشرطات فقط.',
    'alpha_num' => 'يجب أن يحتوي :attribute على حروف وأرقام فقط.',
    'array' => 'يجب أن يكون :attribute قائمة.',
    'between' => [
        'numeric' => 'يجب أن يكون :attribute بين :min و :max.',
        'string' => 'يجب أن يكون طول :attribute بين :min و :max حرفًا.',
    ],
    'boolean' => 'قيمة :attribute يجب أن تكون نعم أو لا.',
    'date' => ':attribute ليس تاريخًا صحيحًا.',
    'different' => 'يجب أن يختلف :attribute عن :other.',
    'email' => 'يجب أن يكون :attribute بريدًا إلكترونيًا صحيحًا.',
    'enum' => 'قيمة :attribute غير صحيحة.',
    'exists' => ':attribute المختار غير موجود.',
    'image' => 'يجب أن يكون :attribute صورة.',
    'in' => 'قيمة :attribute غير صحيحة.',
    'integer' => 'يجب أن يكون :attribute رقمًا صحيحًا.',
    'max' => [
        'array' => 'يجب ألا يزيد :attribute عن :max عناصر.',
        'file' => 'يجب ألا يزيد حجم :attribute عن :max كيلوبايت.',
        'numeric' => 'يجب ألا يزيد :attribute عن :max.',
        'string' => 'يجب ألا يزيد :attribute عن :max حرفًا.',
    ],
    'mimes' => 'يجب أن يكون :attribute ملفًا من نوع: :values.',
    'min' => [
        'numeric' => 'يجب ألا يقل :attribute عن :min.',
        'string' => 'يجب ألا يقل :attribute عن :min أحرف.',
    ],
    'numeric' => 'يجب أن يكون :attribute رقمًا.',
    'required' => 'حقل :attribute مطلوب.',
    'required_unless' => 'حقل :attribute مطلوب.',
    'required_with' => 'حقل :attribute مطلوب عند تحديد :values.',
    'required_without' => 'حقل :attribute مطلوب.',
    'string' => 'يجب أن يكون :attribute نصًا.',
    'unique' => ':attribute مستخدم من قبل.',
    'uploaded' => 'فشل رفع :attribute.',

    'attributes' => [
        'asset_id' => 'الجهاز',
        'body' => 'الرسالة',
        'category_id' => 'نوع المشكلة',
        'comment' => 'التعليق',
        'description' => 'الوصف',
        'email' => 'البريد الإلكتروني',
        'note' => 'السبب',
        'password' => 'كلمة المرور',
        'photos' => 'الصور',
        'photos.*' => 'الصورة',
        'priority' => 'الأولوية',
        'rating' => 'التقييم',
        'room_id' => 'المكان',
        'source' => 'مصدر الطلب',
        'status' => 'الحالة',
    ],
];
