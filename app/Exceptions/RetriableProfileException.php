<?php

namespace App\Exceptions;

use Exception;

class RetriableProfileException extends Exception
{
    // Retriable exceptions trigger exponential backoff retry
}
