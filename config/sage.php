<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Municipality identity for imports
    |--------------------------------------------------------------------------
    |
    | The Sage Evolution company database is imported into a single O-Billing
    | municipality, keyed on this code. Set these to match the council whose
    | Sage database the `sage` connection points at. Defaults to Plumtree Town
    | Council for backward compatibility.
    |
    */

    'municipality' => [
        'code' => env('SAGE_MUNI_CODE', 'PTC'),
        'name' => env('SAGE_MUNI_NAME', 'Plumtree Town Council'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Posting billing runs to Sage (direct invoice documents)
    |--------------------------------------------------------------------------
    |
    | `sage:post-run` posts a completed O-Billing billing run straight into
    | Sage Evolution as POSTED customer invoices: an InvNum document (plus
    | lines) per charge, with the matching PostAR debtor transaction and the
    | balanced PostGL double entry (debtors control ↔ revenue ↔ VAT), exactly
    | replicating what Evolution's own Invoice Maintenance posting writes.
    |
    | The GL wiring is resolved live from Sage itself:
    |   debtors control + VAT control  ← the client's class (CliClass)
    |   revenue account                ← the service item's inventory group
    |   invoice tr code + numbering    ← inventory defaults (StDfTbl)
    |
    | - class_items: the Sage service item (StkItem code) billed per client
    |   class, from the council's price-list schedule. A client whose class has
    |   no mapping is reported and skipped.
    | - tax_type_id / exempt_tax_type_id: output-tax types for taxed lines and
    |   exempt lines (the council bills its levies/licences exempt).
    | - currency_id: the Sage currency of the debtor accounts (USD).
    | - agent_id / username: recorded as the posting operator.
    |
    */

    'posting' => [
        'tax_type_id' => (int) env('SAGE_POST_TAXTYPE', 1), // Output Tax 15.5%
        'exempt_tax_type_id' => (int) env('SAGE_POST_EXEMPT_TAXTYPE', 7), // Exempt 0%
        'currency_id' => (int) env('SAGE_POST_CURRENCY', 1), // USD
        'agent_id' => (int) env('SAGE_POST_AGENT', 1),
        'username' => env('SAGE_POST_USERNAME', 'O-Billing'),
        'class_items' => [
            1 => 'P1SP4-SVS162',  // General Dealer Licence
            2 => 'P1SP4-SVS039',  // Liquor Licence
            3 => 'P1SP4-SVS480',  // Land Development Levy - Commercial
            4 => 'P1SP4-SVS304',  // Land Development Levy - Residential
            5 => 'P1SP4-SVS650',  // Assessment Rate (low density)
            6 => 'P1SP4-SVS407',  // Land Development Levy - Mines
            7 => 'P3SP3-SVS357',  // Lease Rent
            8 => 'P1SP4-SVS078',  // Licence Renewal
            9 => 'P1SP4-SVS171',  // Grinding Mill Licence
            10 => 'P1SP4-SVS077', // Butchery Licence
            11 => 'P1SP4-SVS690', // Supermarket Licence
            12 => 'P1SP4-SVS838', // Restaurant Licence
            13 => 'P1SP4-SVS180', // Hardware Licence
            14 => 'P1SP4-SVS905', // Welding Shop Licence
            15 => 'P1SP4-SVS718', // Carpentry Licence
            16 => 'P1SP4-SVS684', // Filling Station Licence
            18 => 'P1SP4-SVS653', // Eating House Licence
            19 => 'P1SP4-SVS801', // Furniture Shop Licence
            20 => 'P1SP4-SVS039', // Bottle Store (liquor licence item)
            21 => 'P1SP4-SVS788', // Lodge Licence
            22 => 'P1SP4-SVS176', // Hair Salon Licence
            23 => 'P1SP4-SVS090', // Clothing Shop Licence
            24 => 'P1SP4-SVS029', // Bakery Licence
            25 => 'P1SP4-SVS652', // Booking House Licence
            26 => 'P1SP4-SVS690', // Superette (supermarket item)
            28 => 'P1SP4-SVS852', // Theme Park Licence
            29 => 'P1SP4-SVS872', // Truckers Inn Licence
            30 => 'P1SP4-SVS885', // Event Centre Licence
            31 => 'P1SP4-SVS831', // Base Station (renewal item)
            68 => 'P1SP4-SVS184', // Development Levy - Communal (per capita)
        ],
    ],

];
