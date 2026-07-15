<?php

declare(strict_types=1);

namespace App\Services\Sage;

use App\Models\Municipality;
use App\Models\Service;
use App\Models\ServiceType;
use App\Support\Tenancy\CurrentMunicipality;
use Illuminate\Support\Facades\DB;
use ZipArchive;

/**
 * Imports the council's per-client charge schedule (the "Billing.Groups2"
 * workbook) into O-Billing: each Service_N sheet lists one Sage debtor account
 * per row ({stand}-{token}-{portion}) with the USD amount that client is
 * charged for that service per billing period.
 *
 * The amount is stored on the customer_service pivot, where it overrides the
 * suburb tariff during billing. The importer also records each service's
 * cadence and marks every ledger service VAT-EXEMPT — the council's own Sage
 * invoice templates bill all of these charges with tax type 7 (Exempt).
 *
 * Idempotent: re-running replaces the stored amounts.
 */
final class SageChargeImportService
{
    /**
     * Billing cadence per account-type token. The residential development levy
     * ($5/month) and assessment rates (annual) keep the cadence set by the
     * tariff importer; the workbook's business charges (licences, renewals,
     * lease rent, commercial/mines development levy) are annual charges.
     */
    private const TOKEN_FREQUENCIES = [
        'LIC' => 'annually',
        'LICR' => 'annually',
        'LEAR' => 'annually',
        'DEVC' => 'annually',
        'DEVM' => 'annually',
    ];

    private int $municipalityId;

    /**
     * @return array{municipality:string, tokens:array<string,array<string,mixed>>, warnings:list<string>}
     */
    public function run(string $path): array
    {
        if (! is_file($path)) {
            throw new \RuntimeException("Workbook not found: {$path}");
        }

        $muni = Municipality::where('code', config('sage.municipality.code'))->first();
        if ($muni === null) {
            throw new \RuntimeException('Run sage:import-ledger first — the municipality does not exist yet.');
        }
        $this->municipalityId = $muni->id;

        $sheets = $this->chargeRows($path);

        return app(CurrentMunicipality::class)->runFor($muni->id, function () use ($muni, $sheets): array {
            $customers = DB::table('customers')
                ->where('municipality_id', $muni->id)
                ->pluck('id', 'account_number');

            $tokens = [];
            $warnings = [];

            DB::transaction(function () use ($sheets, $customers, &$tokens, &$warnings): void {
                foreach ($sheets as $sheetName => $rows) {
                    foreach ($rows as [$sageCode, $amount]) {
                        $parts = explode('-', $sageCode);
                        if (count($parts) < 2 || $amount === null) {
                            continue;
                        }
                        $stand = trim($parts[0]);
                        $token = strtoupper(trim($parts[1]));

                        $t = &$tokens[$token];
                        $t ??= ['clients' => 0, 'min' => null, 'max' => null, 'total' => 0.0, 'missing' => 0, 'sheet' => $sheetName];

                        $customerId = $customers[$stand] ?? null;
                        $service = $this->serviceForToken($token);
                        if ($customerId === null || $service === null) {
                            $t['missing']++;
                            unset($t);

                            continue;
                        }

                        DB::table('customer_service')->updateOrInsert(
                            ['customer_id' => $customerId, 'service_id' => $service->id],
                            ['amount' => $amount, 'updated_at' => now(), 'created_at' => now()],
                        );

                        $t['clients']++;
                        $t['min'] = $t['min'] === null ? $amount : min($t['min'], $amount);
                        $t['max'] = $t['max'] === null ? $amount : max($t['max'], $amount);
                        $t['total'] += $amount;
                        unset($t);
                    }
                }

                // Cadence: the workbook's business charges are billed annually.
                foreach (self::TOKEN_FREQUENCIES as $token => $frequency) {
                    ServiceType::where('municipality_id', $this->municipalityId)
                        ->where('code', "LEDGER-{$token}")
                        ->update(['default_frequency' => $frequency]);
                }

                // The council bills all ledger services VAT-exempt (their Sage
                // invoice templates use tax type 7 across the board).
                $exempted = Service::whereHas('serviceType', fn ($q) => $q->where('code', 'like', 'LEDGER-%'))
                    ->where('taxable', true)
                    ->update(['taxable' => false]);
                if ($exempted > 0) {
                    $warnings[] = "{$exempted} ledger service(s) switched to VAT-exempt to match the council's Sage invoice templates.";
                }
            });

            $warnings[] = 'DEVC (commercial) and DEVM (mines) development levies now bill ANNUALLY at each client\'s scheduled amount — they were previously billed monthly at a flat $5.';
            $warnings[] = 'DEVR (residential development levy) keeps its monthly $5 ward tariff.';

            return ['municipality' => $muni->name, 'tokens' => $tokens, 'warnings' => $warnings];
        });
    }

    /**
     * Charge rows per sheet: [sheetName => list of [sageAccountCode, amount]].
     * Reads the Service_* sheets, locating the account and amount columns by
     * their headers, with no spreadsheet library required.
     *
     * @return array<string, list<array{0:string,1:float|null}>>
     */
    private function chargeRows(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new \RuntimeException("Cannot open workbook: {$path}");
        }

        // Sheet name → sheet file, via the workbook relationships.
        $workbook = simplexml_load_string((string) $zip->getFromName('xl/workbook.xml'));
        $rels = simplexml_load_string((string) $zip->getFromName('xl/_rels/workbook.xml.rels'));
        $relMap = [];
        foreach ($rels->Relationship as $rel) {
            $relMap[(string) $rel['Id']] = 'xl/'.ltrim((string) $rel['Target'], '/');
        }

        $shared = [];
        $ssXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($ssXml !== false) {
            foreach (simplexml_load_string($ssXml)->si as $si) {
                if (isset($si->t)) {
                    $shared[] = (string) $si->t;
                } else {
                    $text = '';
                    foreach ($si->r as $r) {
                        $text .= (string) $r->t;
                    }
                    $shared[] = $text;
                }
            }
        }

        $sheets = [];
        foreach ($workbook->sheets->sheet as $sheet) {
            $name = (string) $sheet['name'];
            if (! str_starts_with($name, 'Service_')) {
                continue;
            }
            $rid = (string) $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
            $file = $relMap[$rid] ?? null;
            if ($file === null) {
                continue;
            }
            $sheets[$name] = $this->parseSheet((string) $zip->getFromName($file), $shared);
        }
        $zip->close();

        if ($sheets === []) {
            throw new \RuntimeException('No Service_* sheets found in the workbook.');
        }

        return $sheets;
    }

    /**
     * @param  list<string>  $shared
     * @return list<array{0:string,1:float|null}>
     */
    private function parseSheet(string $xml, array $shared): array
    {
        $sheet = new \SimpleXMLElement($xml);

        $codeCol = null;
        $amountCol = null;
        $rows = [];

        foreach ($sheet->sheetData->row as $row) {
            $cells = [];
            foreach ($row->c as $c) {
                $idx = $this->columnIndex((string) $c['r']);
                $value = (string) $c->v;
                if ((string) $c['t'] === 's') {
                    $value = $shared[(int) $value] ?? '';
                }
                $cells[$idx] = $value;
            }

            if ($codeCol === null) {
                // Header row: find the account-code and amount columns.
                foreach ($cells as $idx => $header) {
                    $h = strtolower(trim($header));
                    if ($h === 'sage.code') {
                        $codeCol = $idx;
                    }
                    if ($h === 'amount' || str_starts_with($h, 'charge')) {
                        $amountCol = $idx;
                    }
                }

                continue;
            }

            $code = trim($cells[$codeCol] ?? '');
            if ($code === '') {
                continue;
            }
            $raw = trim($cells[$amountCol] ?? '');
            $amount = is_numeric($raw) ? round((float) $raw, 2) : null;

            $rows[] = [$code, $amount];
        }

        return $rows;
    }

    private function columnIndex(string $ref): int
    {
        preg_match('/^([A-Z]+)/', $ref, $m);
        $n = 0;
        foreach (str_split($m[1]) as $ch) {
            $n = $n * 26 + (ord($ch) - 64);
        }

        return $n - 1;
    }

    /** The default O-Billing service for a ledger account-type token. */
    private function serviceForToken(string $token): ?Service
    {
        static $cache = [];
        if (array_key_exists($token, $cache)) {
            return $cache[$token];
        }

        $type = ServiceType::where('municipality_id', $this->municipalityId)
            ->where('code', "LEDGER-{$token}")->first();

        return $cache[$token] = $type?->services()->where('is_default', true)->first()
            ?? $type?->services()->first();
    }
}
