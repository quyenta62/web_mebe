<?php

namespace App\Enums;

enum CrawlRunStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Success = 'success';
    case Failed = 'failed';
}
