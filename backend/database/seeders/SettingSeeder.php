<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        Setting::setValue('tax_rate', '0');
        Setting::setValue('store_name', 'POS Store');
        Setting::setValue('store_address', '123 Main Street, City');
        Setting::setValue('store_phone', '+1 234 567 890');
        Setting::setValue('currency_symbol', '$');
        Setting::setValue('receipt_footer', 'Thank you for shopping with us!');
    }
}
