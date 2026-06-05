<?php
namespace App\Domain\Waste\Enums;

enum CollectTaskState: string
{
    case PROGRAMMING = 'programming';
    case EXECUTION = 'execution';
    case CONFIRMATION = 'confirmation';
}