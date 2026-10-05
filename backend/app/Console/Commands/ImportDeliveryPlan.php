<?php

namespace App\Console\Commands;

use App\Enums\ImportStatus;
use App\Models\Company;
use App\Models\User;
use App\Services\Import\DeliveryPlanImporter;
use Illuminate\Console\Command;

class ImportDeliveryPlan extends Command
{
    protected $signature = 'shipments:import
        {file : Path to the delivery plan (.xlsx)}
        {--company= : Company id or exact name}
        {--user= : Email of the user the import is attributed to}
        {--dry-run : Validate and report without saving}';

    protected $description = 'Import the Excel delivery plan for a company and print the report';

    public function handle(DeliveryPlanImporter $importer): int
    {
        $path = (string) $this->argument('file');

        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $company = Company::query()
            ->where('id', ctype_digit((string) $this->option('company')) ? (int) $this->option('company') : 0)
            ->orWhereRaw('lower(name) = ?', [mb_strtolower((string) $this->option('company'))])
            ->first();

        if ($company === null) {
            $this->error('Company not found; pass --company=<id or name>.');

            return self::FAILURE;
        }

        $user = $this->option('user') ? User::query()->where('email', mb_strtolower($this->option('user')))->first() : null;
        $import = $importer->import($path, basename($path), $company, $user, (bool) $this->option('dry-run'));

        $this->line(($import->dry_run ? '[dry run] ' : '')."Import #{$import->id} {$import->status->value}");

        if ($import->error_message) {
            $this->error($import->error_message);
        }

        $this->table(['rows', 'imported', 'skipped', 'rejected', 'blank', 'warnings', 'shipments', 'containers'], [[
            $import->total_rows, $import->imported_rows, $import->skipped_rows, $import->rejected_rows,
            $import->blank_rows, $import->warning_count, $import->shipments_created, $import->containers_created,
        ]]);

        if ($import->issues) {
            $this->table(['row', 'severity', 'column', 'message'], array_map(
                fn (array $issue) => [$issue['row'] ?? '-', $issue['severity'], $issue['column'] ?? '-', $issue['message']],
                $import->issues,
            ));
        }

        return $import->status === ImportStatus::Failed ? self::FAILURE : self::SUCCESS;
    }
}
