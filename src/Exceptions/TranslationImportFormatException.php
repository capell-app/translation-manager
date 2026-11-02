<?php

declare(strict_types=1);

namespace Capell\TranslationManager\Exceptions;

use InvalidArgumentException;

final class TranslationImportFormatException extends InvalidArgumentException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct(match ($reason) {
            'csv_headers' => 'Translation CSV must contain key and target_value columns.',
            'po_empty' => 'Translation PO did not contain any entries.',
            'xliff_empty' => 'Translation XLIFF must not be empty.',
            'xliff_doctype' => 'Translation XLIFF must not declare a document type.',
            'xliff_xml' => 'Translation XLIFF must be valid XML.',
            default => 'Translation import format is invalid.',
        });
    }
}
