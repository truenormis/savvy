<?php

namespace Database\Seeders;

use App\Models\Tag;
use Illuminate\Database\Seeder;

class TagSeeder extends Seeder
{
    public function run(): void
    {
        $tags = [
            'Essential',
            'Optional',
            'Recurring',
            'One-time',
            'Business',
            'Personal',
            'Family',
            'Vacation',
            'Emergency',
            'Planned',
        ];

        foreach ($tags as $name) {
            Tag::updateOrCreate(['name' => $name]);
        }
    }
}
