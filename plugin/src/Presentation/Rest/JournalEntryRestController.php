<?php

declare(strict_types=1);

namespace StageArt\Presentation\Rest;

use InvalidArgumentException;
use StageArt\Application\JournalEntry\JournalEntryAccessDeniedException;
use StageArt\Application\JournalEntry\JournalEntryNotFoundException;
use StageArt\Application\JournalEntry\ListJournalEntriesUseCase;
use StageArt\Application\JournalEntry\ListProductionJournalEntriesQuery;
use StageArt\Application\JournalEntry\PostJournalEntryCommand;
use StageArt\Application\JournalEntry\PostJournalEntryUseCase;
use StageArt\Application\Production\ProductionNotFoundException;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class JournalEntryRestController
{
    private const API_NAMESPACE = 'stageart/v1';

    private ListJournalEntriesUseCase $listJournalEntries;
    private PostJournalEntryUseCase $postJournalEntry;

    public function __construct(ListJournalEntriesUseCase $listJournalEntries, PostJournalEntryUseCase $postJournalEntry)
    {
        $this->listJournalEntries = $listJournalEntries;
        $this->postJournalEntry = $postJournalEntry;
    }

    public function register_routes(): void
    {
        register_rest_route(self::API_NAMESPACE, '/productions/(?P<id>[^/]+)/journal-entries', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'list'],
                'permission_callback' => [$this, 'require_login'],
            ],
        ]);

        register_rest_route(self::API_NAMESPACE, '/journal-entries/(?P<id>[^/]+)/post', [
            [
                'methods' => 'PATCH',
                'callback' => [$this, 'post'],
                'permission_callback' => [$this, 'require_login'],
            ],
        ]);
    }

    public function require_login(): bool
    {
        return is_user_logged_in();
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function list(WP_REST_Request $request)
    {
        try {
            $query = new ListProductionJournalEntriesQuery((string) $request->get_param('id'), get_current_user_id());

            return new WP_REST_Response(
                array_map(static fn ($result) => $result->toArray(), $this->listJournalEntries->execute($query)),
                200
            );
        } catch (JournalEntryAccessDeniedException $exception) {
            return new WP_Error('stageart_journal_entry_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (ProductionNotFoundException $exception) {
            return new WP_Error('stageart_production_not_found', $exception->getMessage(), ['status' => 404]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function post(WP_REST_Request $request)
    {
        try {
            $command = new PostJournalEntryCommand((string) $request->get_param('id'), get_current_user_id());

            return new WP_REST_Response($this->postJournalEntry->execute($command)->toArray(), 200);
        } catch (JournalEntryAccessDeniedException $exception) {
            return new WP_Error('stageart_journal_entry_access_denied', $exception->getMessage(), ['status' => 403]);
        } catch (JournalEntryNotFoundException $exception) {
            return new WP_Error('stageart_journal_entry_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (ProductionNotFoundException $exception) {
            return new WP_Error('stageart_production_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('stageart_journal_entry_invalid', $exception->getMessage(), ['status' => 422]);
        }
    }
}
