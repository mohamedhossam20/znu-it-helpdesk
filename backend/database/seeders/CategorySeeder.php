<?php

namespace Database\Seeders;

use App\Enums\TicketPriority;
use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * [name_ar, name_en, icon, default priority, children as [name_ar, name_en]]
     */
    private const TREE = [
        ['كمبيوتر / لابتوب', 'Computer / laptop', 'heroicon-o-computer-desktop', TicketPriority::Normal, [
            ['الجهاز لا يعمل', 'Does not turn on'],
            ['بطء شديد', 'Very slow'],
            ['شاشة زرقاء / إعادة تشغيل', 'Blue screen / restarts'],
            ['مشكلة في برنامج', 'Software problem'],
            ['فيروس / برامج ضارة', 'Virus / malware'],
            ['تثبيت برنامج', 'Install software'],
        ]],
        ['طابعة / ماسح', 'Printer / scanner', 'heroicon-o-printer', TicketPriority::Normal, [
            ['لا تطبع', 'Not printing'],
            ['ورق عالق', 'Paper jam'],
            ['حبر / تونر', 'Ink / toner'],
            ['تعريف الطابعة', 'Printer driver'],
        ]],
        ['شاشات وعرض', 'Screens & display', 'heroicon-o-tv', TicketPriority::High, [
            ['الشاشة لا تعمل', 'Screen not working'],
            ['البروجيكتور', 'Projector'],
            ['كابل HDMI / توصيل', 'HDMI cable / connection'],
            ['لا تظهر صورة من اللابتوب', 'No picture from laptop'],
        ]],
        ['صوت', 'Audio', 'heroicon-o-speaker-wave', TicketPriority::High, [
            ['السماعات', 'Speakers'],
            ['الميكروفون', 'Microphone'],
            ['الأمبلفاير / المكسر', 'Amplifier / mixer'],
            ['تشويش / صفير', 'Noise / feedback'],
        ]],
        ['شبكة وإنترنت', 'Network & internet', 'heroicon-o-wifi', TicketPriority::High, [
            ['لا يوجد إنترنت', 'No internet'],
            ['Wi-Fi ضعيف', 'Weak Wi-Fi'],
            ['نقطة شبكة لا تعمل', 'Network outlet not working'],
            ['راك / سويتش', 'Rack / switch'],
        ]],
        ['حسابات وصلاحيات', 'Accounts & access', 'heroicon-o-key', TicketPriority::Normal, [
            ['البريد الجامعي', 'University email'],
            ['كلمة المرور', 'Password'],
            ['صلاحية على نظام', 'System access'],
        ]],
        ['تليفون داخلي', 'Desk phone', 'heroicon-o-phone', TicketPriority::Normal, []],
        ['أخرى', 'Other', 'heroicon-o-question-mark-circle', TicketPriority::Normal, []],
    ];

    public function run(): void
    {
        foreach (self::TREE as $order => [$nameAr, $nameEn, $icon, $priority, $children]) {
            $parent = Category::updateOrCreate(
                ['parent_id' => null, 'name_ar' => $nameAr],
                ['name_en' => $nameEn, 'icon' => $icon, 'default_priority' => $priority, 'sort_order' => $order],
            );

            foreach ($children as $childOrder => [$childAr, $childEn]) {
                Category::updateOrCreate(
                    ['parent_id' => $parent->id, 'name_ar' => $childAr],
                    ['name_en' => $childEn, 'sort_order' => $childOrder],
                );
            }
        }
    }
}
