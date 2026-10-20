<?php

declare(strict_types=1);

namespace J87NL\Mailing\Signup;

enum SignupVerdict
{
    case Human;
    case Bot;
    case Expired;
}
