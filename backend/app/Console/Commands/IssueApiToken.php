<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Http\Requests\Admin\StoreApiTokenRequest;
use App\Models\Company;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class IssueApiToken extends Command
{
    protected $signature = 'api:token
        {client : Name of the API client (service account), e.g. "Suppli integration"}
        {--company= : Company id}
        {--name=default : Token name}
        {--ability=* : write, references:write (repeat the option); none = read-only}
        {--expires-days= : Days until the token expires}';

    protected $description = 'Issue an API token for an integration (creates the API client if needed)';

    public function handle(): int
    {
        $company = Company::query()->find((int) $this->option('company'));

        if ($company === null) {
            $this->error('Company not found; pass --company=<id>.');

            return self::FAILURE;
        }

        $abilities = array_values((array) $this->option('ability'));
        $unknown = array_diff($abilities, StoreApiTokenRequest::ABILITIES);

        if ($unknown !== []) {
            $this->error('Unknown abilities: '.implode(', ', $unknown));

            return self::FAILURE;
        }

        $client = User::query()->firstOrCreate(
            ['company_id' => $company->id, 'name' => $this->argument('client'), 'is_service_account' => true],
            ['role' => UserRole::CompanyUser],
        );

        $days = $this->option('expires-days');
        $token = $client->createToken(
            (string) $this->option('name'),
            $abilities,
            $days ? CarbonImmutable::now()->addDays((int) $days) : null,
        );

        $this->info("Token for \"{$client->name}\" ({$company->name}). It is shown only once:");
        $this->line($token->plainTextToken);

        return self::SUCCESS;
    }
}
