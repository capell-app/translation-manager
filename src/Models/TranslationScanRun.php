<?php

declare(strict_types=1);

namespace Capell\TranslationManager\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Override;

/**
 * @property int $id
 * @property string $type
 * @property string $source_key
 * @property string $source_locale
 * @property string $status
 * @property array<string, mixed>|list<array<string, mixed>>|null $result
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property string|null $error_message
 */
final class TranslationScanRun extends Model
{
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    protected $table = 'capell_translation_scan_runs';

    /** @var list<string> */
    protected $guarded = ['id'];

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return [
            'result' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
