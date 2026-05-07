<?php

namespace App\Model;

enum ToolCallStatus: int
{
    case pending = 0;
    case completed = 1;
    case error = 2;
}
