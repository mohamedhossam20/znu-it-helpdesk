<?php

namespace Database\Seeders;

use App\Models\AssetType;
use Illuminate\Database\Seeder;

class AssetTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['PC', 'PC', 'كمبيوتر مكتبي', 'Desktop PC', 'heroicon-o-computer-desktop'],
            ['LAPTOP', 'LT', 'لابتوب', 'Laptop', 'heroicon-o-device-tablet'],
            ['PRINTER', 'PR', 'طابعة', 'Printer', 'heroicon-o-printer'],
            ['SCANNER', 'SC', 'ماسح ضوئي', 'Scanner', 'heroicon-o-document-magnifying-glass'],
            ['SCREEN', 'SCR', 'شاشة عرض', 'Display screen', 'heroicon-o-tv'],
            ['PROJECTOR', 'PJ', 'بروجيكتور', 'Projector', 'heroicon-o-film'],
            ['SPEAKER', 'SP', 'سماعات', 'Speakers', 'heroicon-o-speaker-wave'],
            ['MIC', 'MIC', 'ميكروفون', 'Microphone', 'heroicon-o-microphone'],
            ['AMPLIFIER', 'AMP', 'أمبلفاير / مكسر صوت', 'Amplifier / mixer', 'heroicon-o-adjustments-horizontal'],
            ['SWITCH', 'SW', 'سويتش شبكة', 'Network switch', 'heroicon-o-server-stack'],
            ['ACCESS_POINT', 'AP', 'نقطة Wi-Fi', 'Wi-Fi access point', 'heroicon-o-wifi'],
            ['RACK', 'RK', 'راك شبكة', 'Network rack', 'heroicon-o-server'],
            ['PHONE', 'TEL', 'تليفون داخلي', 'Desk phone', 'heroicon-o-phone'],
        ];

        foreach ($types as [$code, $prefix, $nameAr, $nameEn, $icon]) {
            AssetType::updateOrCreate(
                ['code' => $code],
                ['code_prefix' => $prefix, 'name_ar' => $nameAr, 'name_en' => $nameEn, 'icon' => $icon],
            );
        }
    }
}
