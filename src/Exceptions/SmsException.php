<?php

declare(strict_types=1);

namespace Digitalize\Sms\Exceptions;

/** Base class of every exception this package throws, so one catch covers them all. */
abstract class SmsException extends \RuntimeException
{
}
