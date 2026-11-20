<?php

namespace Database\Seeders;

use App\Models\Domain;
use App\Models\Task;
use Illuminate\Database\Seeder;

/**
 * Domains A-F and the 43 tasks of the outline, from database/data/taxonomy.json.
 * Tasks carry internal labels only: official_wording is never written (IP hold).
 */
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
        foreach ($data['tasks'] as $code => $task) {
            Task::updateOrCreate(['code' => $code], [
                'domain_id' => $domains[$task['domain']],
                'labels' => $task['labels'],
                'source_version' => $task['source_version'],
                'source_url' => $task['source_url'],
            ]);
        }
    }
}
