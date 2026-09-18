<?php

declare(strict_types=1);

/**
 * Test polyfill for the global error_get_last() builtin.
 *
 * The {@see \SovereignStack\Core\ErrorHandler\ErrorHandler::handleFatal()}
 * source calls `error_get_last()` UNQUALIFIED — PHP's name-resolution rules
 * look up the function in the current namespace
 * (SovereignStack\Core\ErrorHandler) before falling back to the global
 * builtin. Defining this namespaced function lets tests inject a non-null
 * error array, exercising the fatalSeverities guard in handleFatal()
 * without relying on real PHP fatal errors (which would terminate the
 * process before assertions can fire).
 *
 * Tests opt in by setting $GLOBALS['dglab_test_handleFatal_error'] to an
 * array shaped like ['type' => int, 'message' => string, 'file' => string,
 * 'line' => int]. When unset, this polyfill returns null — matching the
 * real global builtin's behavior in a clean test environment, so existing
 * handleFatal() tests that assume "no error" remain unaffected.
 *
 * This file is loaded on demand by {@see ErrorHandlerTest} via
 * require_once; the function_exists guard makes the require idempotent.
 */

namespace SovereignStack\Core\ErrorHandler;

if (!function_exists(__NAMESPACE__ . '\\error_get_last')) {
    /**
     * @return array{type: int, message: string, file: string, line: int}|null
     */
    function error_get_last(): ?array
    {
        /** @var array{type: int, message: string, file: string, line: int}|null $err */
        $err = $GLOBALS['dglab_test_handleFatal_error'] ?? null;
        return $err;
    }
}
