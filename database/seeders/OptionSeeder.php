<?php

namespace Database\Seeders;

use App\Models\Option;
use App\Models\OptionGroup;
use App\Support\Options\OptionCatalog;
use App\Support\Options\Options;
use Illuminate\Database\Seeder;

/**
 * Creates option groups and their initial values. Existing options are not
 * modified, so labels/order changed by administrators survive re-seeding.
 */
class OptionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (OptionCatalog::groups() as $key => $definition) {
            $group = OptionGroup::query()->updateOrCreate(['key' => $key], [
                'name' => $definition['name'],
                'description' => $definition['description'],
            ]);

            foreach ($definition['values'] as $index => $value) {
                Option::query()->firstOrCreate(
                    ['option_group_id' => $group->id, 'value' => $value[0]],
                    [
                        'label' => $value[1],
                        'color' => $value[2] ?? 'secondary',
                        'behavior' => $value[3] ?? null,
                        'sort_order' => ($index + 1) * 10,
                        'is_active' => true,
                    ],
                );
            }
        }

        app(Options::class)->flush();
    }
}
