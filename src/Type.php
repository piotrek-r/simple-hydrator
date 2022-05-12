<?php

declare(strict_types=1);

namespace PiotrekR\SimpleHydrator;

enum Type
{
    case BOOL;
    case DATETIME;
    case ENUM;
    case FLOAT;
    case INTEGER;
    case JSON;
    case RAW;
    case STRING;
}
