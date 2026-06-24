<?php
declare(strict_types=1);

namespace App\Invite;

final class InviteState
{
    public const DRAFT          = 'draft';
    public const SENT           = 'sent';
    public const OPENED         = 'opened';
    public const RESPONDED      = 'responded';
    public const PENDING_SENDER = 'pending_sender';
    public const CONFIRMED      = 'confirmed';
    public const DECLINED       = 'declined';
    public const CLOSED         = 'closed';
    public const EXPIRED        = 'expired';
    public const BLOCKED        = 'blocked';

    /** @var array<string,string[]> */
    private const TRANSITIONS = [
        self::DRAFT          => [self::SENT],
        self::SENT           => [self::OPENED, self::EXPIRED, self::BLOCKED],
        self::OPENED         => [self::RESPONDED, self::EXPIRED, self::BLOCKED],
        self::RESPONDED      => [self::CONFIRMED, self::PENDING_SENDER],
        self::PENDING_SENDER => [self::CONFIRMED, self::DECLINED, self::EXPIRED],
        self::CONFIRMED      => [self::CLOSED],
        self::DECLINED       => [self::SENT, self::CLOSED],
        self::CLOSED         => [],
        self::EXPIRED        => [],
        self::BLOCKED        => [],
    ];

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    public static function assert(string $from, string $to): void
    {
        if (!self::canTransition($from, $to)) {
            throw new \InvalidArgumentException("Illegal invite transition: {$from} -> {$to}");
        }
    }

    /** @return string[] */
    public static function all(): array
    {
        return array_keys(self::TRANSITIONS);
    }
}
