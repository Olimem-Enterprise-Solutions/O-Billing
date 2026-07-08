<?php

declare(strict_types=1);

namespace App\Models\Sage;

/**
 * Sage `_ccg_EB_Categories` — a property classification (Residential H/D,
 * Commercial, Gov Dpt, Light Industry, …) used for rating, usage and zoning.
 */
class Category extends SageModel
{
    protected $table = '_ccg_EB_Categories';
}
