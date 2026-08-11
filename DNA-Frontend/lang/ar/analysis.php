<?php

return [
    'result' => [
        'title' => 'نتيجة التحليل',
        'file' => 'الملف',
        'checksum' => 'البصمة',
        // بلا «في»، لأن ما يليه وقت نسبي: «حُلِّل منذ ٣ دقائق».
        'created' => 'حُلِّل',
        'records' => 'سجل',
    ],

    'metrics' => [
        'records' => 'السجلات',
        'total_bases' => 'مجموع القواعد',
        'avg_gc' => 'متوسط GC',
        'avg_length' => 'متوسط الطول',
        'variants' => 'المتغيّرات المكتشفة',
        'unknown' => 'قواعد غير محدّدة',
        'gc_range' => 'مدى GC',
        'length_range' => 'مدى الطول',
    ],

    'track' => [
        'title' => 'مسارات التركيب',
        'subtitle' => 'كل شريط يمثّل سجلًا واحدًا مرسومًا بالمقياس. العلامات أسفل الشريط تبيّن مواضع المتغيّرات.',
        'legend' => 'القاعدة',
        'orientation_note' => 'تُقرأ التسلسلات دائمًا من ٥′ إلى ٣′ من اليسار إلى اليمين، في كل اللغات.',
        'variants_marked' => 'مواضع المتغيّرات نسبةً إلى :reference',
        'no_variants' => 'لا فروق عن السجل الأول.',
    ],

    'table' => [
        'title' => 'السجلات',
        'subtitle' => 'صف واحد لكل سجل في الملف.',
        'id' => 'المعرّف',
        'description' => 'الوصف',
        'length' => 'الطول',
        'gc' => 'GC',
        'tm' => 'Tm',
        'tm_method' => 'طريقة Tm',
        'protein' => 'أطول ORF',
        'composition' => 'A / T / C / G / N',
        'ambiguous' => 'غامضة',
        'quality' => 'الجودة',
        'view_protein' => 'عرض البروتين',
    ],

    'tm_methods' => [
        'wallace' => 'قاعدة والاس',
        'nearest_neighbour' => 'أقرب جار',
        'gc_empirical' => 'تقدير GC',
        'none' => 'غير متاح',
    ],

    'tm' => [
        'estimate' => 'تقدير',
        'estimate_note' => 'ديناميكا أقرب جار تصف الازدواجات القصيرة فقط. فوق :length قاعدة تكون القيمة المعروضة تقديرًا تجريبيًا مبنيًا على GC، لا قياسًا.',
    ],

    'orf' => [
        'title' => 'أطر القراءة المفتوحة',
        'none' => 'لم يُعثر على إطار قراءة مفتوح.',
        'longest' => 'أطول ORF',
        'strand' => 'الشريط',
        'frame' => 'الإطار',
        'position' => 'الموضع',
        'length_aa' => 'الطول',
        'count' => 'عُثر على :count عبر ستة أطر',
        'truncated' => 'فُحصت أول :length قاعدة فقط من كل سجل.',
        'forward' => 'أمامي',
        'reverse' => 'عكسي',
        'protein_title' => 'تسلسل البروتين',
        'protein_empty' => 'لا يحتوي هذا السجل على إطار قراءة قابل للترجمة.',
    ],

    'codon' => [
        'title' => 'أكثر الكودونات تكرارًا',
        'codon' => 'الكودون',
        'amino_acid' => 'الحمض الأميني',
        'count' => 'العدد',
        'frequency' => 'التكرار',
        'empty' => 'أقصر من أن تُحصى كودوناته.',
    ],

    'compare' => [
        'title' => 'المقارنة',
        'subtitle' => 'يُحاذى كل سجل مقابل السجل الأول في الملف.',
        'reference' => 'المرجع',
        'against' => 'مقارنًا بـ',
        'identity' => 'التطابق',
        'method' => 'الطريقة',
        'aligned_length' => 'طول المحاذاة',
        'total' => 'المتغيّرات',
        'none' => 'ارفع سجلَّين أو أكثر لمقارنتهما.',
        'identical' => 'مطابق للمرجع تمامًا.',
        'counts_title' => 'حسب النوع',
        'effects_title' => 'حسب الأثر على البروتين',
        'frameshift' => 'أحداث انزياح الإطار',
        'truncated' => 'تُعرض أول ٥٠٠ متغيّر من أصل :total.',
    ],

    'methods' => [
        'global_alignment' => 'محاذاة شاملة',
        'positional_diff' => 'فرق موضعي',
        'positional_note' => 'يتجاوز طول هذه التسلسلات :length قاعدة، لذا قورنت موضعًا بموضع دون محاذاة. لا تُحلّ عمليات الإدراج والحذف في هذا الوضع.',
    ],

    'variant' => [
        'type' => 'النوع',
        'position' => 'الموضع',
        'codon' => 'الكودون',
        'change' => 'التغيير',
        'effect' => 'الأثر',
        'length' => 'الطول',
        'frameshift' => 'انزياح الإطار',
        'transition' => 'انتقال',
        'transversion' => 'تبادل',
        'inserted' => 'المُدرج',
        'deleted' => 'المحذوف',
    ],

    'variant_types' => [
        'substitution' => 'استبدال',
        'insertion' => 'إدراج',
        'deletion' => 'حذف',
        'length_difference' => 'فرق في الطول',
    ],

    'effects' => [
        'synonymous' => 'مرادفة',
        'missense' => 'خاطئة المعنى',
        'nonsense' => 'عديمة المعنى',
        'stop_lost' => 'فقدان إشارة التوقف',
        'unknown' => 'غير محدَّد',
    ],

    'quality' => [
        'clean' => 'محدَّدة بالكامل',
        'has_ambiguity' => 'تحتوي قواعد غامضة',
        'ambiguity_codes' => 'رموز IUPAC الموجودة: :codes',
        'unknown_fraction' => ':percent٪ غير محدَّدة',
    ],

    'units' => [
        'bp' => 'bp',
        'aa' => 'aa',
        'celsius' => '°C',
        'dalton' => 'Da',
    ],

    'print' => [
        'generated' => 'أُنشئ في :date',
        'source' => 'الملف المصدر',
        'note' => 'من إنتاج نظام تحليل الحمض النووي.',
    ],
];
