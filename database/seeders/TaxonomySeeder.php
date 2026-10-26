<?php

namespace Database\Seeders;

use App\Models\Domain;
use App\Models\Task;
use Illuminate\Database\Seeder;

/** Domains A-F and internal task labels, from database/data/taxonomy.json. */
class TaxonomySeeder extends Seeder
{
    public function run(): void
    {
        $data = json_decode(file_get_contents(database_path('data/taxonomy.json')), true, flags: JSON_THROW_ON_ERROR);

        $sort = 0;
        foreach ($data['domains'] as $code => $names) {
            Domain::updateOrCreate(['code' => $code], ['names' => $names, 'sort' => ++$sort]);
        }

        $domains = Domain::pluck('id', 'code');
        foreach ($data['tasks'] as $code => $labels) {
            Task::updateOrCreate(['code' => $code], [
                'domain_id' => $domains[strtok($code, '.')],
                'labels' => $labels,
            ]);
        }
    }
}
