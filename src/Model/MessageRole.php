<?php

namespace App\Model;

enum MessageRole: string
{
    case system = "system";
    case user = "user";
    case assistant = "assistant";
    case tool = "tool";
}
