<?php

namespace App\Exceptions;

use Exception;

class FatalProfileException extends Exception
{
    // Fatal exceptions trigger immediate failure, no retries
}
