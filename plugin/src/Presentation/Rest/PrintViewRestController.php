<?php

declare(strict_types=1);

namespace StageArt\Presentation\Rest;

use InvalidArgumentException;
use StageArt\Application\PrintView\GetProductionPrintViewUseCase;
use StageArt\Application\PrintView\PdfRendererInterface;
use StageArt\Application\PrintView\PrintViewAccessDeniedException;
use StageArt\Application\PrintView\ProductionPrintViewQuery;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Presentation\Print\PrintLayoutOptions;
use StageArt\Presentation\Print\PrintViewHtmlRenderer;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * `format=pdf` (the default) and `format=html` both succeed by directly
 * emitting headers + raw body + exit(), bypassing WP_REST_Response's
 * normal JSON-encoding pathway entirely - WP core always
 * wp_json_encode()s a WP_REST_Response's data before output, which
 * would corrupt binary PDF bytes (and needlessly escape/quote an HTML
 * document) rather than serve either as-is. Error paths (403/404/422)
 * are unaffected and still return normal WP_Error JSON responses,
 * since those are decided BEFORE any binary/HTML content is generated.
 *
 * No Role/Person query parameter exists on this route at all (Phase 4.5
 * instruction §16 explicitly prohibits both) - paperSize/orientation
 * are the only parameters, and they only ever affect Layout, never
 * which data is included.
 */
final class PrintViewRestController
{
    private const API_NAMESPACE = 'stageart/v1';

    private GetProductionPrintViewUseCase $getPrintView;
    private PrintViewHtmlRenderer $htmlRenderer;
    private PdfRendererInterface $pdfRenderer;

    public function __construct(
        GetProductionPrintViewUseCase $getPrintView,
        PrintViewHtmlRenderer $htmlRenderer,
        PdfRendererInterface $pdfRenderer
    ) {
        $this->getPrintView = $getPrintView;
        $this->htmlRenderer = $htmlRenderer;
        $this->pdfRenderer = $pdfRenderer;
    }

    public function register_routes(): void
    {
        register_rest_route(self::API_NAMESPACE, '/productions/(?P<id>[^/]+)/timetable/print', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'print'],
                'permission_callback' => [$this, 'require_login'],
            ],
        ]);
    }

    public function require_login(): bool
    {
        return is_user_logged_in();
    }

    /**
     * @return WP_REST_Response|WP_Error|void
     */
    public function print(WP_REST_Request $request)
    {
        try {
            $query = new ProductionPrintViewQuery((string) $request->get_param('id'), get_current_user_id());
            $printView = $this->getPrintView->execute($query);

            $layout = new PrintLayoutOptions(
                (string) ($request->get_param('paperSize') ?: 'A4'),
                (string) ($request->get_param('orientation') ?: 'portrait')
            );

            $format = (string) ($request->get_param('format') ?: 'pdf');

            if (! in_array($format, ['pdf', 'html'], true)) {
                return new WP_Error('stageart_print_view_invalid', "Invalid format: {$format}. Must be pdf or html.", ['status' => 422]);
            }

            $html = $this->htmlRenderer->render($printView, $layout);

            if ($format === 'html') {
                nocache_headers();
                header('Content-Type: text/html; charset=UTF-8');
                echo $html;
                exit;
            }

            $pdf = $this->pdfRenderer->render($html, $layout->paperSize(), $layout->orientation());

            $filename = sanitize_file_name($printView->productionName) . '-timetable-print.pdf';

            nocache_headers();
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="' . $filename . '"');
            header('Content-Length: ' . strlen($pdf));
            echo $pdf;
            exit;
        } catch (PrintViewAccessDeniedException $exception) {
            return new WP_Error('stageart_print_view_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (ProductionNotFoundException $exception) {
            return new WP_Error('stageart_production_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_print_view_invalid', $exception->getMessage(), ['status' => 422]);
        }
    }
}
