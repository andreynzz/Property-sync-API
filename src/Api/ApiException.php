<?php
/**
 * Exception raised when the external property API cannot be consumed safely.
 *
 * @package PropertySync
 */

declare(strict_types=1);

namespace PropertySync\Api;

use RuntimeException;

final class ApiException extends RuntimeException
{
}
