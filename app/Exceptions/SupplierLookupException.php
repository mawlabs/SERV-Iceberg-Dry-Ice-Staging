<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * The closest-supplier lookup could not be completed (Google unreachable,
 * timed out, or rejected the request). Different from "no supplier covers
 * this address", which is a normal null result.
 */
class SupplierLookupException extends RuntimeException
{
}
