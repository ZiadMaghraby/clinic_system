<?php

return [
    'required' => 'حقل :attribute مطلوب.', 'required_if' => 'حقل :attribute مطلوب عند اختيار :value.',
    'email' => 'يرجى إدخال بريد إلكتروني صالح في :attribute.', 'unique' => 'قيمة :attribute مستخدمة بالفعل.',
    'confirmed' => 'تأكيد :attribute غير مطابق.', 'string' => 'يجب أن يكون :attribute نصًا.',
    'integer' => 'يجب أن يكون :attribute عددًا صحيحًا.', 'numeric' => 'يجب أن يكون :attribute رقمًا.',
    'date' => 'يجب أن يكون :attribute تاريخًا صالحًا.', 'date_format' => 'صيغة :attribute المطلوبة هي :format.',
    'after' => 'يجب أن يكون :attribute بعد :date.', 'after_or_equal' => 'يجب أن يكون :attribute في :date أو بعده.',
    'before_or_equal' => 'يجب أن يكون :attribute في :date أو قبله.', 'in' => 'قيمة :attribute غير صالحة.', 'exists' => 'القيمة المختارة في :attribute غير موجودة.',
    'array' => 'يجب أن يكون :attribute قائمة.', 'distinct' => 'قيم :attribute يجب ألا تتكرر.', 'current_password' => 'كلمة المرور الحالية غير صحيحة.',
    'lowercase' => 'يجب كتابة :attribute بحروف صغيرة.',
    'min' => ['string' => 'يجب ألا يقل :attribute عن :min حرفًا.', 'numeric' => 'يجب ألا يقل :attribute عن :min.', 'array' => 'اختر :min عناصر على الأقل في :attribute.'],
    'max' => ['string' => 'يجب ألا يزيد :attribute عن :max حرفًا.', 'numeric' => 'يجب ألا يزيد :attribute عن :max.', 'array' => 'اختر :max عناصر كحد أقصى في :attribute.'],
    'between' => ['numeric' => 'يجب أن يكون :attribute بين :min و:max.'],
    'password' => ['letters' => 'يجب أن تحتوي كلمة المرور على حروف.', 'numbers' => 'يجب أن تحتوي كلمة المرور على أرقام.'],
    'attributes' => ['name' => 'الاسم', 'email' => 'البريد الإلكتروني', 'password' => 'كلمة المرور', 'phone' => 'الهاتف', 'appointment_date' => 'تاريخ الموعد', 'appointment_time' => 'وقت الموعد', 'doctor_id' => 'الطبيب', 'patient_id' => 'المريض', 'notes' => 'الملاحظات', 'reason' => 'سبب الزيارة', 'working_days' => 'أيام العمل', 'starts_at' => 'بداية العمل', 'ends_at' => 'نهاية العمل', 'consultation_fee' => 'رسوم الكشف', 'date_of_birth' => 'تاريخ الميلاد'],
];
