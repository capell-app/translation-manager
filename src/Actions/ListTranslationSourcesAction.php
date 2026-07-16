<?php

declare(strict_types=1);

namespace Capell\TranslationManager\Actions;

use Capell\TranslationManager\Contracts\TranslationSourceResolver;
use Capell\TranslationManager\Data\TranslationSourceData;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * @method static array<int, TranslationSourceData> run()
 */
final class ListTranslationSourcesAction
{
    use AsFake;
    use AsObject;

    /**
     * @return array<int, TranslationSourceData>
     */
    public function handle(): array
    {
        return resolve(TranslationSourceResolver::class)->sources();
    }
}
