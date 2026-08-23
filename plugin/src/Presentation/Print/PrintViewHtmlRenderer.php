<?php

declare(strict_types=1);

namespace StageArt\Presentation\Print;

use StageArt\Application\PrintView\ProductionPrintViewResult;
use StageArt\Application\PrintView\RehearsalPrintSectionResult;
use StageArt\Application\TimetableItem\TimetableItemResult;

/**
 * Turns a ProductionPrintViewResult (pure data, Application layer) into
 * a single self-contained HTML document, driven by PrintLayoutOptions
 * (paperSize/orientation - the only two Layout attributes Timetable.md
 * §3 allows Print View to vary by). This exact HTML string is used
 * verbatim for both the browser/Flutter Preview (format=html) and as
 * DompdfRenderer's input (format=pdf) - one template, no duplicated
 * layout logic between preview and final output.
 *
 * Layout choice (Phase 4.5 disclosed scope decision): a single
 * chronological, date-grouped vertical list is used for all 4 paper
 * size/orientation combinations - only font size, column proportions,
 * and density vary between them (see densityPreset()). Timetable.md §10
 * lists a horizontal time-axis (Gantt-style) layout only as a
 * "candidate", not a requirement; building a second, structurally
 * different layout engine for it was judged out of proportion to this
 * phase's scope and is left as a disclosed future option.
 *
 * Pagination (verified empirically against the actual Dompdf install -
 * see the Phase 4.5 report): page-break-inside:avoid is applied only to
 * INDIVIDUAL item rows, never to an entire day/Rehearsal block. Wrapping
 * a block larger than one page in page-break-inside:avoid was found to
 * push the ENTIRE block onto the next page (leaving the previous page
 * mostly blank) rather than splitting it - the opposite of the intended
 * effect. Each date/Rehearsal heading instead gets page-break-after:
 * avoid (a small element, safe to keep whole) so a heading is never
 * stranded alone at the bottom of a page.
 */
final class PrintViewHtmlRenderer
{
    /** Minimum readable font size in points - never scaled below this regardless of item count. */
    private const MIN_FONT_SIZE_A4 = 8.0;
    private const MIN_FONT_SIZE_A3 = 9.0;

    public function render(ProductionPrintViewResult $printView, PrintLayoutOptions $layout): string
    {
        $totalItems = array_sum(array_map(
            static fn (RehearsalPrintSectionResult $section): int => count($section->items),
            $printView->sections
        ));

        $preset = $this->densityPreset($totalItems, $layout->isA3());
        $fontDir = $this->fontDirectoryUrl();
        $generatedAt = (new \DateTimeImmutable())->format('Y-m-d H:i');

        $bodyHtml = $this->renderSections($printView->sections, $preset);

        $paperLabel = $layout->paperSize() . ' ' . ($layout->isLandscape() ? '横 (Landscape)' : '縦 (Portrait)');

        return <<<HTML
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<title>{$this->esc($printView->productionName)} - Timetable Print View</title>
<style>
{$this->css($layout, $preset, $fontDir)}
</style>
</head>
<body>
<div id="print-header">
    <div class="ph-title">{$this->esc($printView->productionName)}</div>
    <div class="ph-meta">Print View ({$paperLabel}) ・ 出力日時: {$generatedAt}</div>
</div>
<div id="print-footer">{$this->esc($printView->productionName)}</div>
{$bodyHtml}
<script type="text/php">
if (isset(\$pdf)) {
    \$w = \$pdf->get_width();
    \$pdf->page_text(\$w - 90, \$pdf->get_height() - 34, "Page {PAGE_NUM} / {PAGE_COUNT}", null, 9, array(0.4, 0.4, 0.4));
}
</script>
</body>
</html>
HTML;
    }

    /**
     * @return array{fontSize: float, headingSize: float, cellPadding: string}
     */
    private function densityPreset(int $totalItems, bool $isA3): array
    {
        if ($isA3) {
            if ($totalItems <= 40) {
                return ['fontSize' => 13.0, 'headingSize' => 16.0, 'cellPadding' => '10px 12px'];
            }
            if ($totalItems <= 120) {
                return ['fontSize' => 11.5, 'headingSize' => 14.0, 'cellPadding' => '7px 10px'];
            }

            return ['fontSize' => max(self::MIN_FONT_SIZE_A3, 9.5), 'headingSize' => 12.5, 'cellPadding' => '5px 8px'];
        }

        if ($totalItems <= 20) {
            return ['fontSize' => 11.0, 'headingSize' => 14.0, 'cellPadding' => '8px 10px'];
        }
        if ($totalItems <= 60) {
            return ['fontSize' => 10.0, 'headingSize' => 12.5, 'cellPadding' => '6px 8px'];
        }

        return ['fontSize' => max(self::MIN_FONT_SIZE_A4, 8.5), 'headingSize' => 11.0, 'cellPadding' => '4px 6px'];
    }

    /**
     * @param RehearsalPrintSectionResult[] $sections
     * @param array{fontSize: float, headingSize: float, cellPadding: string} $preset
     */
    private function renderSections(array $sections, array $preset): string
    {
        if (count($sections) === 0) {
            return '<p class="empty-note">現在Publishされている予定はありません。</p>';
        }

        $html = '';

        foreach ($sections as $section) {
            $html .= $this->renderSection($section, $preset);
        }

        return $html;
    }

    /**
     * @param array{fontSize: float, headingSize: float, cellPadding: string} $preset
     */
    private function renderSection(RehearsalPrintSectionResult $section, array $preset): string
    {
        $dateLabel = $section->rehearsalStartDateTime !== null
            ? (new \DateTimeImmutable($section->rehearsalStartDateTime))->format('Y/m/d (D)')
            : '日時未設定';

        $versionLabel = $section->timetableVersion !== null
            ? 'Timetable v' . $section->timetableVersion
            : '';

        $publishedLabel = $section->publishedAt !== null
            ? 'Published: ' . (new \DateTimeImmutable($section->publishedAt))->format('Y/m/d H:i')
            : '';

        $changeSummaryHtml = '';
        if ($section->changeSummary !== null && trim($section->changeSummary) !== '') {
            $changeSummaryHtml = '<div class="section-change-summary">変更概要: ' . $this->esc($section->changeSummary) . '</div>';
        }

        $rowsHtml = '';
        foreach ($section->items as $item) {
            $rowsHtml .= $this->renderItemRow($item);
        }

        if ($rowsHtml === '') {
            $rowsHtml = '<div class="item-row empty-note">この日程にはItemがありません。</div>';
        }

        return <<<HTML
<div class="rehearsal-section">
    <div class="section-heading">
        <span class="section-date">{$dateLabel}</span>
        <span class="section-title">{$this->esc($section->rehearsalTitle ?? '')}</span>
        <span class="section-version">{$this->esc($versionLabel)}</span>
        <span class="section-published">{$this->esc($publishedLabel)}</span>
    </div>
    {$changeSummaryHtml}
    <div class="item-table">
        {$rowsHtml}
    </div>
</div>
HTML;
    }

    private function renderItemRow(TimetableItemResult $item): string
    {
        $start = (new \DateTimeImmutable($item->startDateTime))->format('H:i');
        $end = $item->endDateTime !== null ? (new \DateTimeImmutable($item->endDateTime))->format('H:i') : '';
        $timeLabel = $end !== '' ? "{$start} - {$end}" : $start;

        $isStage = $item->venue !== null && mb_strpos($item->venue, '舞台') !== false;
        $stageClass = $isStage ? ' item-row-stage' : '';

        $venueHtml = $item->venue !== null && $item->venue !== ''
            ? '<span class="badge' . ($isStage ? ' badge-stage' : '') . '">' . $this->esc($item->venue) . ($isStage ? ' ★舞台使用' : '') . '</span>'
            : '';

        $categoryHtml = $item->category !== null && $item->category !== ''
            ? '<span class="badge badge-category">' . $this->esc($item->category) . '</span>'
            : '';

        $roleHtml = $item->participantType !== null
            ? '<span class="badge badge-role">' . $this->esc($item->participantType) . '</span>'
            : '';

        $personCount = count($item->targetPersonIds);
        $personHtml = $personCount > 0
            ? '<span class="badge badge-person">個人指定 ' . $personCount . '名</span>'
            : '';

        $descriptionHtml = $item->description !== null && $item->description !== ''
            ? '<div class="item-description">' . $this->esc($item->description) . '</div>'
            : '';

        $notesHtml = $item->notes !== null && $item->notes !== ''
            ? '<div class="item-notes">' . $this->esc($item->notes) . '</div>'
            : '';

        return <<<HTML
<div class="item-row{$stageClass}">
    <div class="item-time">{$timeLabel}</div>
    <div class="item-main">
        <div class="item-title">{$this->esc($item->title)}</div>
        {$descriptionHtml}
        <div class="item-badges">{$categoryHtml}{$venueHtml}{$roleHtml}{$personHtml}</div>
        {$notesHtml}
    </div>
</div>
HTML;
    }

    /**
     * @param array{fontSize: float, headingSize: float, cellPadding: string} $preset
     */
    private function css(PrintLayoutOptions $layout, array $preset, string $fontDir): string
    {
        $pageSize = $layout->paperSize() . ' ' . $layout->orientation();
        $fontSize = $preset['fontSize'];
        $headingSize = $preset['headingSize'];
        $cellPadding = $preset['cellPadding'];

        return <<<CSS
@font-face {
    font-family: 'NotoSansJP';
    font-weight: normal;
    src: url('{$fontDir}NotoSansJP-Regular.ttf') format('truetype');
}
@font-face {
    font-family: 'NotoSansJP';
    font-weight: bold;
    src: url('{$fontDir}NotoSansJP-Bold.ttf') format('truetype');
}
@page {
    size: {$pageSize};
    margin: 95px 30px 55px 30px;
}
* { box-sizing: border-box; }
body {
    font-family: 'NotoSansJP', sans-serif;
    font-size: {$fontSize}pt;
    color: #1a1a1a;
    margin: 0;
}
#print-header {
    position: fixed;
    left: 0px; right: 0px; top: -80px; height: 65px;
    border-bottom: 2px solid #333;
}
.ph-title { font-weight: bold; font-size: {$headingSize}pt; }
.ph-meta { font-size: 9pt; color: #555; margin-top: 3px; }
#print-footer {
    position: fixed;
    left: 0px; bottom: -40px; height: 20px;
    font-size: 8pt; color: #888;
}
.rehearsal-section { margin-bottom: 14px; }
.section-heading {
    page-break-after: avoid;
    background: #2b2b2b;
    color: #fff;
    padding: {$cellPadding};
    font-size: {$headingSize}pt;
    font-weight: bold;
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: baseline;
}
.section-date { color: #fff; }
.section-title { color: #ddd; font-weight: normal; }
.section-version { margin-left: auto; color: #ffd27a; font-size: 0.85em; }
.section-published { color: #bbb; font-size: 0.8em; }
.section-change-summary {
    font-size: 0.85em;
    color: #444;
    background: #f3f3f3;
    padding: 4px {$cellPadding};
    border-left: 3px solid #999;
}
.item-table { border: 1px solid #ccc; border-top: none; }
.item-row {
    display: flex;
    gap: 10px;
    padding: {$cellPadding};
    border-bottom: 1px dashed #ccc;
    page-break-inside: avoid;
}
.item-row-stage { background: #fff6e0; }
.item-time { width: 15%; min-width: 70px; font-weight: bold; font-variant-numeric: tabular-nums; }
.item-main { flex: 1; }
.item-title { font-weight: bold; }
.item-description { color: #333; margin-top: 2px; }
.item-notes { color: #555; font-size: 0.85em; margin-top: 2px; }
.item-badges { margin-top: 3px; display: flex; flex-wrap: wrap; gap: 5px; }
.badge {
    display: inline-block;
    font-size: 0.8em;
    padding: 1px 7px;
    border-radius: 3px;
    background: #eee;
    color: #333;
}
.badge-category { background: #e3ecf5; }
.badge-role { background: #eee3f5; }
.badge-person { background: #e3f5e6; }
.badge-stage { background: #ffb703; color: #1a1a1a; font-weight: bold; }
.empty-note { color: #888; font-style: italic; padding: 8px; }
CSS;
    }

    private function fontDirectoryUrl(): string
    {
        if (defined('STAGEART_PLUGIN_DIR')) {
            return 'file://' . STAGEART_PLUGIN_DIR . 'assets/fonts/';
        }

        return 'file://' . __DIR__ . '/../../../assets/fonts/';
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
