<?php

declare(strict_types=1);

namespace KimaiPlugin\KimaiExpensesCommunityBundle;

use App\Plugin\PluginInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Free, self-hosted expense tracking for Kimai.
 *
 * Phase 1 intentionally focuses on the core CRUD workflow. Invoice,
 * export, reporting and API integration are planned for later phases.
 */
final class KimaiExpensesCommunityBundle extends Bundle implements PluginInterface
{
}
