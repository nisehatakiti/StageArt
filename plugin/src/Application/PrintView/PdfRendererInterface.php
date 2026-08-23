<?php

declare(strict_types=1);

namespace StageArt\Application\PrintView;

/**
 * Port for HTML -> PDF conversion (mirrors TransactionManagerInterface's
 * role: lets the Presentation layer ask for a PDF without knowing which
 * concrete library performs the conversion - see Timetable.md's "Print
 * View Implementation Decisions" for why a pure-PHP, HTML/CSS-driven
 * library (Dompdf) was chosen as the one Infrastructure adapter).
 */
interface PdfRendererInterface
{
    /**
     * @param string $paperSize 'A4' or 'A3'
     * @param string $orientation 'portrait' or 'landscape'
     * @return string raw PDF binary content
     */
    public function render(string $html, string $paperSize, string $orientation): string;
}
