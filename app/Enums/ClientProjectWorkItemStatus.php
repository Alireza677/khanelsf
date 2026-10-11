<?php

namespace App\Enums;

enum ClientProjectWorkItemStatus: string
{
    case Planned = 'planned';
    case Ready = 'ready';
    case InProgress = 'in_progress';
    case Submitted = 'submitted';
    case Accepted = 'accepted';
    case Completed = 'completed';
    case Blocked = 'blocked';
    case RevisionRequested = 'revision_requested';
    case Cancelled = 'cancelled';

    public static function options(): array
    {
        return ['planned' => 'برنامه‌ریزی‌شده', 'ready' => 'آماده', 'in_progress' => 'در حال اجرا', 'submitted' => 'اجرا شده؛ منتظر بررسی', 'accepted' => 'تأییدشده', 'completed' => 'تکمیل‌شده', 'blocked' => 'مسدود', 'revision_requested' => 'نیازمند اصلاح', 'cancelled' => 'لغوشده'];
    }

    /** Acceptance and revision requests use the delivery review manager, not admin transitions. */
    public function administrativeTargets(bool $approvalRequired): array
    {
        return match ($this) {
            self::Planned => ['ready', 'cancelled'],
            self::Ready => ['planned', 'in_progress', 'blocked', 'cancelled'],
            self::InProgress => $approvalRequired ? ['submitted', 'blocked', 'cancelled'] : ['submitted', 'completed', 'blocked', 'cancelled'],
            self::Blocked, self::RevisionRequested => ['ready', 'in_progress', 'cancelled'],
            self::Submitted => $approvalRequired ? [] : ['completed', 'in_progress', 'cancelled'],
            self::Accepted => ['completed'],
            self::Completed, self::Cancelled => [],
        };
    }
}
