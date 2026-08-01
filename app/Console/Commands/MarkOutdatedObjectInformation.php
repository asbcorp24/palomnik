<?php

namespace App\Console\Commands;

use App\Models\PilgrimageObject;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class MarkOutdatedObjectInformation extends Command
{
    protected $signature = 'objects:mark-information-outdated';

    protected $description = 'Пометить сведения об объектах как устаревшие после даты следующей проверки или 90 дней без проверки';

    public function handle(): int
    {
        $updated = PilgrimageObject::query()
            ->where('verification_status', PilgrimageObject::VERIFICATION_VERIFIED)
            ->where(function (Builder $query): void {
                $query->where(function (Builder $query): void {
                    $query->whereNotNull('next_verification_at')
                        ->where('next_verification_at', '<', now());
                })->orWhere(function (Builder $query): void {
                    $query->whereNull('next_verification_at')
                        ->whereNotNull('information_verified_at')
                        ->where('information_verified_at', '<=', now()->subDays(90));
                });
            })
            ->update([
                'verification_status' => PilgrimageObject::VERIFICATION_OUTDATED,
                'updated_at' => now(),
            ]);

        $this->info('Помечено устаревшими: '.$updated);

        return self::SUCCESS;
    }
}
