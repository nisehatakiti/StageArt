<?php

declare(strict_types=1);

namespace StageArt\Presentation\Print;

use InvalidArgumentException;

/**
 * Validated (paperSize, orientation) pair - the only two Presentation/
 * Layout attributes Timetable.md §3 allows Print View to vary by. Kept
 * as a tiny dedicated Value Object (not a raw string pair) so
 * PrintViewHtmlRenderer and DompdfRenderer both work from the same
 * validated, normalized representation.
 */
final class PrintLayoutOptions
{
    public const PAPER_SIZES = ['A4', 'A3'];
    public const ORIENTATIONS = ['portrait', 'landscape'];

    private string $paperSize;
    private string $orientation;

    public function __construct(string $paperSize, string $orientation)
    {
        $paperSize = strtoupper($paperSize);
        $orientation = strtolower($orientation);

        if (! in_array($paperSize, self::PAPER_SIZES, true)) {
            throw new InvalidArgumentException("Invalid paperSize: {$paperSize}. Must be A4 or A3.");
        }

        if (! in_array($orientation, self::ORIENTATIONS, true)) {
            throw new InvalidArgumentException("Invalid orientation: {$orientation}. Must be portrait or landscape.");
        }

        $this->paperSize = $paperSize;
        $this->orientation = $orientation;
    }

    public function paperSize(): string
    {
        return $this->paperSize;
    }

    public function orientation(): string
    {
        return $this->orientation;
    }

    public function isA3(): bool
    {
        return $this->paperSize === 'A3';
    }

    public function isLandscape(): bool
    {
        return $this->orientation === 'landscape';
    }
}
