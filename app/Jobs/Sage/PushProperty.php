<?php

declare(strict_types=1);

namespace App\Jobs\Sage;

use App\Models\Customer;
use App\Models\SageOperation;
use App\Services\Sage\SagePropertyWriter;
use RuntimeException;

/** Creates a property (debtor account/s or property-module row) in Sage for an O-Billing customer. */
final class PushProperty extends SageJob
{
    protected function execute(SageOperation $operation): array
    {
        $customer = Customer::withoutGlobalScopes()
            ->with(['area', 'services.serviceType'])
            ->findOrFail($operation->subject_id);

        $result = app(SagePropertyWriter::class)->pushProperty($customer);

        if (! ($result['ok'] ?? false)) {
            throw new RuntimeException($result['error'] ?? 'Push failed.');
        }

        $message = ($result['mode'] ?? null) === 'ledger'
            ? 'Created Sage account(s): '.implode(', ', $result['created'] ?? [])
            : "Created property #{$result['property_id']} (erf {$result['erf']}).";

        return [$result, $message];
    }

    protected function title(SageOperation $operation): string
    {
        return 'Send property to Sage';
    }
}
