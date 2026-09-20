<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when FCM refused the message (bad token, missing credentials,
 * transport error). Callers turn this into otp_logs.status = failed.
 */
class FcmDeliveryException extends RuntimeException
{
}
