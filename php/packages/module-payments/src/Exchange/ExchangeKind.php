<?php

declare(strict_types=1);

namespace WebxUi\Payments\Exchange;

/** What an event of the exchange was about (spec §20.1). */
enum ExchangeKind: string
{
    case Start = 'start';
    case Status = 'status';
    case Cancel = 'cancel';
    case Complete = 'complete';
    case Capture = 'capture';
    case Void = 'void';
    case Connection = 'connection';
    /** A signing key or an OAuth token the satellite fetched. */
    case Key = 'key';
    case Other = 'other';
    case Notify = 'notify';
    case Handoff = 'handoff';
    case Return = 'return';
    case Expire = 'expire';
    case Manual = 'manual';
    /** A hold captured by the shop — the base's decision after the gateway's answer to `capture`. */
    case Captured = 'captured';
    /** A hold let go by the shop — the base's decision after the gateway's answer to `void`. */
    case Released = 'released';

    public function direction(): Direction
    {
        return match ($this) {
            self::Notify => Direction::In,
            self::Handoff, self::Return => Direction::Browser,
            self::Expire, self::Manual, self::Captured, self::Released => Direction::Internal,
            default => Direction::Out,
        };
    }
}
