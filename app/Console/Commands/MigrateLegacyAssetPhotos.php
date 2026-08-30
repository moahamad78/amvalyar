<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AssetPhoto;
use App\Services\CompanyStorageProfileService;
use App\Services\LegacyAssetPhotoMigrationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class MigrateLegacyAssetPhotos extends Command
{
    protected $signature='assets:migrate-legacy-photos
        {--company= : Restrict to one company ID}
        {--limit=0 : Maximum number of photos to inspect; 0 means no limit}
        {--execute : Perform migration; without this option the command is dry-run}
        {--delete-source : Delete public source after verified migration; requires --execute}';

    protected $description='Safely migrate legacy public asset photos to each company default storage profile.';

    public function handle(
        CompanyStorageProfileService $profiles,
        LegacyAssetPhotoMigrationService $migrator
    ): int {
        $company=$this->option('company');
        $limit=max(0,(int)$this->option('limit'));
        $execute=(bool)$this->option('execute');
        $deleteSource=(bool)$this->option('delete-source');

        if ($deleteSource && !$execute) {
            $this->error('--delete-source requires --execute.');
            return self::FAILURE;
        }

        if ($company !== null && (!ctype_digit((string)$company) || (int)$company <= 0)) {
            $this->error('--company must be a positive integer.');
            return self::FAILURE;
        }

        $query=AssetPhoto::withoutGlobalScopes()
            ->where('storage_driver','public')
            ->whereNull('storage_profile_id')
            ->orderBy('id');

        if ($company !== null) {
            $query->where('company_id',(int)$company);
        }

        if ($limit > 0) {
            $query->limit($limit);
        }

        $photos=$query->get();
        $counts=['eligible'=>0,'migrated'=>0,'missing'=>0,'no_profile'=>0,'failed'=>0];

        $this->info($execute ? 'MODE: EXECUTE' : 'MODE: DRY-RUN');
        $this->line('Candidates: '.$photos->count());

        foreach ($photos as $photo) {
            $profile=$profiles->defaultForCompany((int)$photo->company_id);

            if ($profile === null) {
                $counts['no_profile']++;
                $this->line("SKIP photo={$photo->id} company={$photo->company_id} reason=no_active_profile");
                continue;
            }

            if (!Storage::disk('public')->exists((string)$photo->path)) {
                $counts['missing']++;
                $this->warn("MISSING photo={$photo->id} company={$photo->company_id}");
                continue;
            }

            $counts['eligible']++;

            if (!$execute) {
                $this->line("READY photo={$photo->id} company={$photo->company_id} profile={$profile->id}");
                continue;
            }

            try {
                $result=$migrator->migrate($photo,$deleteSource);
                if ($result['status'] === 'migrated') {
                    $counts['migrated']++;
                    $this->info("MIGRATED photo={$photo->id} company={$photo->company_id}");
                } elseif ($result['status'] === 'missing') {
                    $counts['missing']++;
                } elseif ($result['message'] === 'no_active_profile') {
                    $counts['no_profile']++;
                }
            } catch (Throwable $e) {
                $counts['failed']++;
                report($e);
                $this->error("FAILED photo={$photo->id} company={$photo->company_id}");
            }
        }

        $this->newLine();
        $this->table(
            ['eligible','migrated','missing','no_profile','failed'],
            [[$counts['eligible'],$counts['migrated'],$counts['missing'],$counts['no_profile'],$counts['failed']]]
        );

        return $counts['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}