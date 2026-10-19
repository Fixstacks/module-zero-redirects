<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 *
 * Zero_Redirects: a log of the storefront's 404s (which address, how often,
 * from where) and a 301 redirect for one in a click, as Magento's own URL
 * rewrite. For a store after a migration or a catalog change.
 */

use Magento\Framework\Component\ComponentRegistrar;

ComponentRegistrar::register(ComponentRegistrar::MODULE, 'Zero_Redirects', __DIR__);
