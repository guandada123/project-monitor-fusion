<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Services\MetricEngine;
use Illuminate\Console\Command;

class ComputeMetricsCommand extends Command
{
    protected $signature = 'metrics:compute
                            {--project= : Compute for specific project slug}
                            {--all : Compute all active projects}';

    protected $description = '计算所有指标并写入快照';

    public function handle(MetricEngine $engine): int
    {
        if ($this->option('project')) {
            $project = Project::where('slug', $this->option('project'))->first();
            if (! $project) {
                $this->error('Project not found: '.$this->option('project'));

                return Command::FAILURE;
            }
            $engine->computeForProject($project);
            $this->info("✅ Metrics computed for {$project->name}");

            return Command::SUCCESS;
        }

        // 默认（含 --all）：全部活跃项目
        $results = $engine->computeAll();
        $count = count($results);
        $this->info("✅ Metrics computed for {$count} projects");

        return Command::SUCCESS;
    }
}
