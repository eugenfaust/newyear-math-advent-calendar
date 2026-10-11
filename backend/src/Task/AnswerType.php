<?php

declare(strict_types=1);

namespace App\Task;

enum AnswerType: string
{
    case NONE = 'NONE';
    case NUMBER = 'NUMBER';
    case TEXT = 'TEXT';
    case MULTIPLE_CHOICE = 'MULTIPLE_CHOICE';
}
