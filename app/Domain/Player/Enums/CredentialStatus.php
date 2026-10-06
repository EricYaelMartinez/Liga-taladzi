<?php

namespace App\Domain\Player\Enums;

enum CredentialStatus: string
{
    case Active = 'active';
    case Revoked = 'revoked';
}
