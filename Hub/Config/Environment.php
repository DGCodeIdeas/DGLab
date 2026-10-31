<?php

declare(strict_types=1);

namespace SovereignStack\Hub\Config;

/**
 * Runtime environment for feature-flag evaluation.
 *
 * NOTE: CORE-10 does not yet ship an Environment enum. This local enum
 * satisfies HUB-01's Context dependency at depth 2. When CORE-10 promotes
 * to include an Environment enum, this can be replaced with a use-clause
 * swap — the string values are stable.
 *
 * @package SovereignStack\Hub\Config
 */
enum Environment: string
{
    case Development = 'development';
    case Staging     = 'staging';
    case Production  = 'production';
    case Testing     = 'testing';
}
