<?php

namespace App\Enums;

enum TaskStatus: string
{
    case BACKLOG = 'backlog';
    case TODO = 'todo';
    case IN_PROGRESS = 'in_progress';
    case REVIEW = 'review';
    case CLIENT_APPROVAL = 'client_approval';
    case DONE = 'done';
    case CANCELLED = 'cancelled';
}
