<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

/**
 * Holds the active municipality for the current request/process. Bound as a
 * singleton so the municipality-scoping global scope and any tenant-aware
 * service can read it. Set by middleware in HTTP context, or directly in
 * console/tests/billing runs.
 */
final class CurrentMunicipality
{
    private ?int $municipalityId = null;

    public function set(?int $municipalityId): void
    {
        $this->municipalityId = $municipalityId;
    }

    public function id(): ?int
    {
        return $this->municipalityId;
    }

    public function isSet(): bool
    {
        return $this->municipalityId !== null;
    }

    /** Run a callback scoped to a municipality, restoring the previous scope after. */
    public function runFor(int $municipalityId, callable $callback): mixed
    {
        $previous = $this->municipalityId;
        $this->municipalityId = $municipalityId;

        try {
            return $callback();
        } finally {
            $this->municipalityId = $previous;
        }
    }
}
