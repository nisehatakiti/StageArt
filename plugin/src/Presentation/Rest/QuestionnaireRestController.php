<?php

declare(strict_types=1);

namespace StageArt\Presentation\Rest;

use InvalidArgumentException;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Application\Questionnaire\AddQuestionCommand;
use StageArt\Application\Questionnaire\AddQuestionUseCase;
use StageArt\Application\Questionnaire\CloseQuestionnaireCommand;
use StageArt\Application\Questionnaire\CloseQuestionnaireUseCase;
use StageArt\Application\Questionnaire\CreateQuestionnaireCommand;
use StageArt\Application\Questionnaire\CreateQuestionnaireUseCase;
use StageArt\Application\Questionnaire\DeleteQuestionCommand;
use StageArt\Application\Questionnaire\DeleteQuestionUseCase;
use StageArt\Application\Questionnaire\GetPublicQuestionnaireQuery;
use StageArt\Application\Questionnaire\GetPublicQuestionnaireUseCase;
use StageArt\Application\Questionnaire\GetQuestionnaireQuery;
use StageArt\Application\Questionnaire\GetQuestionnaireResultsQuery;
use StageArt\Application\Questionnaire\GetQuestionnaireResultsUseCase;
use StageArt\Application\Questionnaire\GetQuestionnaireUseCase;
use StageArt\Application\Questionnaire\PublishQuestionnaireCommand;
use StageArt\Application\Questionnaire\PublishQuestionnaireUseCase;
use StageArt\Application\Questionnaire\QuestionEditLockedException;
use StageArt\Application\Questionnaire\QuestionNotFoundException;
use StageArt\Application\Questionnaire\QuestionnaireAccessDeniedException;
use StageArt\Application\Questionnaire\QuestionnaireAlreadyExistsException;
use StageArt\Application\Questionnaire\QuestionnaireNotAcceptingResponsesException;
use StageArt\Application\Questionnaire\QuestionnaireNotFoundException;
use StageArt\Application\Questionnaire\ReorderQuestionsCommand;
use StageArt\Application\Questionnaire\ReorderQuestionsUseCase;
use StageArt\Application\Questionnaire\SubmitQuestionnaireResponseCommand;
use StageArt\Application\Questionnaire\SubmitQuestionnaireResponseUseCase;
use StageArt\Application\Questionnaire\UpdateQuestionCommand;
use StageArt\Application\Questionnaire\UpdateQuestionUseCase;
use StageArt\Application\Questionnaire\UpdateQuestionnaireCommand;
use StageArt\Application\Questionnaire\UpdateQuestionnaireUseCase;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * §34/§35/§36: admin routes nested under `/productions/{id}/questionnaire`
 * (require_login + the UseCase's own Production-management check, same
 * split as every other admin controller in this codebase), and public
 * routes under `/questionnaires/by-slug/{slug}` (permission_callback
 * `__return_true`, matching `/productions/by-slug/{slug}`'s own
 * precedent) live on this single controller - matching
 * PerformanceRestController's own
 * authenticated-and-public-routes-on-one-controller shape.
 */
final class QuestionnaireRestController
{
    private const API_NAMESPACE = 'stageart/v1';

    private CreateQuestionnaireUseCase $createQuestionnaire;
    private GetQuestionnaireUseCase $getQuestionnaire;
    private UpdateQuestionnaireUseCase $updateQuestionnaire;
    private PublishQuestionnaireUseCase $publishQuestionnaire;
    private CloseQuestionnaireUseCase $closeQuestionnaire;
    private AddQuestionUseCase $addQuestion;
    private UpdateQuestionUseCase $updateQuestion;
    private DeleteQuestionUseCase $deleteQuestion;
    private ReorderQuestionsUseCase $reorderQuestions;
    private GetQuestionnaireResultsUseCase $getResults;
    private GetPublicQuestionnaireUseCase $getPublicQuestionnaire;
    private SubmitQuestionnaireResponseUseCase $submitResponse;

    public function __construct(
        CreateQuestionnaireUseCase $createQuestionnaire,
        GetQuestionnaireUseCase $getQuestionnaire,
        UpdateQuestionnaireUseCase $updateQuestionnaire,
        PublishQuestionnaireUseCase $publishQuestionnaire,
        CloseQuestionnaireUseCase $closeQuestionnaire,
        AddQuestionUseCase $addQuestion,
        UpdateQuestionUseCase $updateQuestion,
        DeleteQuestionUseCase $deleteQuestion,
        ReorderQuestionsUseCase $reorderQuestions,
        GetQuestionnaireResultsUseCase $getResults,
        GetPublicQuestionnaireUseCase $getPublicQuestionnaire,
        SubmitQuestionnaireResponseUseCase $submitResponse
    ) {
        $this->createQuestionnaire = $createQuestionnaire;
        $this->getQuestionnaire = $getQuestionnaire;
        $this->updateQuestionnaire = $updateQuestionnaire;
        $this->publishQuestionnaire = $publishQuestionnaire;
        $this->closeQuestionnaire = $closeQuestionnaire;
        $this->addQuestion = $addQuestion;
        $this->updateQuestion = $updateQuestion;
        $this->deleteQuestion = $deleteQuestion;
        $this->reorderQuestions = $reorderQuestions;
        $this->getResults = $getResults;
        $this->getPublicQuestionnaire = $getPublicQuestionnaire;
        $this->submitResponse = $submitResponse;
    }

    public function register_routes(): void
    {
        register_rest_route(self::API_NAMESPACE, '/productions/(?P<id>[^/]+)/questionnaire', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get'],
                'permission_callback' => [$this, 'require_login'],
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'create'],
                'permission_callback' => [$this, 'require_login'],
            ],
            [
                'methods' => 'PUT',
                'callback' => [$this, 'update'],
                'permission_callback' => [$this, 'require_login'],
            ],
        ]);

        register_rest_route(self::API_NAMESPACE, '/productions/(?P<id>[^/]+)/questionnaire/publish', [
            [
                'methods' => 'PATCH',
                'callback' => [$this, 'publish'],
                'permission_callback' => [$this, 'require_login'],
            ],
        ]);

        register_rest_route(self::API_NAMESPACE, '/productions/(?P<id>[^/]+)/questionnaire/close', [
            [
                'methods' => 'PATCH',
                'callback' => [$this, 'close'],
                'permission_callback' => [$this, 'require_login'],
            ],
        ]);

        register_rest_route(self::API_NAMESPACE, '/productions/(?P<id>[^/]+)/questionnaire/results', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'results'],
                'permission_callback' => [$this, 'require_login'],
            ],
        ]);

        register_rest_route(self::API_NAMESPACE, '/productions/(?P<id>[^/]+)/questionnaire/questions', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'addQuestion'],
                'permission_callback' => [$this, 'require_login'],
            ],
        ]);

        register_rest_route(self::API_NAMESPACE, '/productions/(?P<id>[^/]+)/questionnaire/questions/reorder', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'reorderQuestions'],
                'permission_callback' => [$this, 'require_login'],
            ],
        ]);

        register_rest_route(self::API_NAMESPACE, '/productions/(?P<id>[^/]+)/questionnaire/questions/(?P<questionId>[^/]+)', [
            [
                'methods' => 'PUT',
                'callback' => [$this, 'updateQuestion'],
                'permission_callback' => [$this, 'require_login'],
            ],
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'deleteQuestion'],
                'permission_callback' => [$this, 'require_login'],
            ],
        ]);

        // §5/§35/§36: public, unauthenticated - resolved by Production
        // slug only, matching `/productions/by-slug/{slug}`'s own
        // precedent (see GetPublicQuestionnaireUseCase's own docblock).
        register_rest_route(self::API_NAMESPACE, '/questionnaires/by-slug/(?P<slug>[^/]+)', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'getPublic'],
                'permission_callback' => '__return_true',
            ],
        ]);

        register_rest_route(self::API_NAMESPACE, '/questionnaires/by-slug/(?P<slug>[^/]+)/responses', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'submitResponse'],
                'permission_callback' => '__return_true',
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
    public function get(WP_REST_Request $request)
    {
        try {
            $query = new GetQuestionnaireQuery((string) $request->get_param('id'), get_current_user_id());

            return new WP_REST_Response($this->getQuestionnaire->execute($query)->toArray(), 200);
        } catch (QuestionnaireAccessDeniedException $exception) {
            return $this->accessDenied($exception);
        } catch (QuestionnaireNotFoundException $exception) {
            return $this->notFound($exception);
        } catch (ProductionNotFoundException $exception) {
            return new WP_Error('stageart_production_not_found', $exception->getMessage(), ['status' => 404]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function create(WP_REST_Request $request)
    {
        try {
            $command = new CreateQuestionnaireCommand(
                (string) $request->get_param('id'),
                get_current_user_id(),
                (string) $request->get_param('title'),
                $this->stringOrNull($request->get_param('description'))
            );

            return new WP_REST_Response($this->createQuestionnaire->execute($command)->toArray(), 201);
        } catch (QuestionnaireAccessDeniedException $exception) {
            return $this->accessDenied($exception);
        } catch (QuestionnaireAlreadyExistsException $exception) {
            return new WP_Error('stageart_questionnaire_already_exists', $exception->getMessage(), ['status' => 409]);
        } catch (ProductionNotFoundException $exception) {
            return new WP_Error('stageart_production_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (InvalidArgumentException $exception) {
            return $this->invalid($exception);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function update(WP_REST_Request $request)
    {
        try {
            $command = new UpdateQuestionnaireCommand(
                (string) $request->get_param('id'),
                get_current_user_id(),
                (string) $request->get_param('title'),
                $this->stringOrNull($request->get_param('description')),
                $this->stringOrNull($request->get_param('response_end_at'))
            );

            return new WP_REST_Response($this->updateQuestionnaire->execute($command)->toArray(), 200);
        } catch (QuestionnaireAccessDeniedException $exception) {
            return $this->accessDenied($exception);
        } catch (QuestionnaireNotFoundException $exception) {
            return $this->notFound($exception);
        } catch (ProductionNotFoundException $exception) {
            return new WP_Error('stageart_production_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (InvalidArgumentException $exception) {
            return $this->invalid($exception);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function publish(WP_REST_Request $request)
    {
        try {
            $command = new PublishQuestionnaireCommand((string) $request->get_param('id'), get_current_user_id());

            return new WP_REST_Response($this->publishQuestionnaire->execute($command)->toArray(), 200);
        } catch (QuestionnaireAccessDeniedException $exception) {
            return $this->accessDenied($exception);
        } catch (QuestionnaireNotFoundException $exception) {
            return $this->notFound($exception);
        } catch (ProductionNotFoundException $exception) {
            return new WP_Error('stageart_production_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (InvalidArgumentException $exception) {
            return $this->invalid($exception);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function close(WP_REST_Request $request)
    {
        try {
            $command = new CloseQuestionnaireCommand((string) $request->get_param('id'), get_current_user_id());

            return new WP_REST_Response($this->closeQuestionnaire->execute($command)->toArray(), 200);
        } catch (QuestionnaireAccessDeniedException $exception) {
            return $this->accessDenied($exception);
        } catch (QuestionnaireNotFoundException $exception) {
            return $this->notFound($exception);
        } catch (ProductionNotFoundException $exception) {
            return new WP_Error('stageart_production_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (InvalidArgumentException $exception) {
            return $this->invalid($exception);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function results(WP_REST_Request $request)
    {
        try {
            $query = new GetQuestionnaireResultsQuery((string) $request->get_param('id'), get_current_user_id());

            return new WP_REST_Response($this->getResults->execute($query)->toArray(), 200);
        } catch (QuestionnaireAccessDeniedException $exception) {
            return $this->accessDenied($exception);
        } catch (QuestionnaireNotFoundException $exception) {
            return $this->notFound($exception);
        } catch (ProductionNotFoundException $exception) {
            return new WP_Error('stageart_production_not_found', $exception->getMessage(), ['status' => 404]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function addQuestion(WP_REST_Request $request)
    {
        try {
            $choiceLabels = $request->get_param('choices');
            $command = new AddQuestionCommand(
                (string) $request->get_param('id'),
                get_current_user_id(),
                (string) $request->get_param('text'),
                (string) $request->get_param('type'),
                (bool) $request->get_param('required'),
                is_array($choiceLabels) ? array_map('strval', $choiceLabels) : []
            );

            return new WP_REST_Response($this->addQuestion->execute($command)->toArray(), 201);
        } catch (QuestionnaireAccessDeniedException $exception) {
            return $this->accessDenied($exception);
        } catch (QuestionnaireNotFoundException $exception) {
            return $this->notFound($exception);
        } catch (ProductionNotFoundException $exception) {
            return new WP_Error('stageart_production_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (InvalidArgumentException $exception) {
            return $this->invalid($exception);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function updateQuestion(WP_REST_Request $request)
    {
        try {
            $choicesInput = $request->get_param('choices');
            $choices = [];
            foreach (is_array($choicesInput) ? $choicesInput : [] as $choiceInput) {
                $choices[] = [
                    'id' => isset($choiceInput['id']) && $choiceInput['id'] !== '' ? (string) $choiceInput['id'] : null,
                    'label' => (string) ($choiceInput['label'] ?? ''),
                ];
            }

            $command = new UpdateQuestionCommand(
                (string) $request->get_param('id'),
                (string) $request->get_param('questionId'),
                get_current_user_id(),
                (string) $request->get_param('text'),
                (bool) $request->get_param('required'),
                $choices
            );

            return new WP_REST_Response($this->updateQuestion->execute($command)->toArray(), 200);
        } catch (QuestionnaireAccessDeniedException $exception) {
            return $this->accessDenied($exception);
        } catch (QuestionEditLockedException $exception) {
            return new WP_Error('stageart_question_edit_locked', $exception->getMessage(), ['status' => 409]);
        } catch (QuestionNotFoundException $exception) {
            return new WP_Error('stageart_question_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (QuestionnaireNotFoundException $exception) {
            return $this->notFound($exception);
        } catch (ProductionNotFoundException $exception) {
            return new WP_Error('stageart_production_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (InvalidArgumentException $exception) {
            return $this->invalid($exception);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function deleteQuestion(WP_REST_Request $request)
    {
        try {
            $command = new DeleteQuestionCommand(
                (string) $request->get_param('id'),
                (string) $request->get_param('questionId'),
                get_current_user_id()
            );

            $this->deleteQuestion->execute($command);

            return new WP_REST_Response(null, 204);
        } catch (QuestionnaireAccessDeniedException $exception) {
            return $this->accessDenied($exception);
        } catch (QuestionEditLockedException $exception) {
            return new WP_Error('stageart_question_edit_locked', $exception->getMessage(), ['status' => 409]);
        } catch (QuestionNotFoundException $exception) {
            return new WP_Error('stageart_question_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (QuestionnaireNotFoundException $exception) {
            return $this->notFound($exception);
        } catch (ProductionNotFoundException $exception) {
            return new WP_Error('stageart_production_not_found', $exception->getMessage(), ['status' => 404]);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function reorderQuestions(WP_REST_Request $request)
    {
        try {
            $orderedIds = $request->get_param('question_ids');
            $command = new ReorderQuestionsCommand(
                (string) $request->get_param('id'),
                get_current_user_id(),
                is_array($orderedIds) ? array_map('strval', $orderedIds) : []
            );

            $results = $this->reorderQuestions->execute($command);

            return new WP_REST_Response(array_map(static fn ($result) => $result->toArray(), $results), 200);
        } catch (QuestionnaireAccessDeniedException $exception) {
            return $this->accessDenied($exception);
        } catch (QuestionnaireNotFoundException $exception) {
            return $this->notFound($exception);
        } catch (ProductionNotFoundException $exception) {
            return new WP_Error('stageart_production_not_found', $exception->getMessage(), ['status' => 404]);
        } catch (InvalidArgumentException $exception) {
            return $this->invalid($exception);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function getPublic(WP_REST_Request $request)
    {
        try {
            $query = new GetPublicQuestionnaireQuery((string) $request->get_param('slug'));

            return new WP_REST_Response($this->getPublicQuestionnaire->execute($query)->toArray(), 200);
        } catch (QuestionnaireNotFoundException $exception) {
            return $this->notFound($exception);
        }
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function submitResponse(WP_REST_Request $request)
    {
        try {
            $answersInput = $request->get_param('answers');
            $answers = [];
            foreach (is_array($answersInput) ? $answersInput : [] as $answerInput) {
                $answers[] = [
                    'question_id' => (string) ($answerInput['question_id'] ?? ''),
                    'value' => $answerInput['value'] ?? null,
                ];
            }

            $command = new SubmitQuestionnaireResponseCommand((string) $request->get_param('slug'), $answers);

            $this->submitResponse->execute($command);

            return new WP_REST_Response(['submitted' => true], 201);
        } catch (QuestionnaireNotAcceptingResponsesException $exception) {
            return new WP_Error('stageart_questionnaire_not_accepting_responses', $exception->getMessage(), ['status' => 403]);
        } catch (QuestionnaireNotFoundException $exception) {
            return $this->notFound($exception);
        } catch (InvalidArgumentException $exception) {
            return $this->invalid($exception);
        }
    }

    private function stringOrNull($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    private function accessDenied(QuestionnaireAccessDeniedException $exception): WP_Error
    {
        return new WP_Error('stageart_questionnaire_access_denied', $exception->getMessage(), ['status' => 403]);
    }

    private function notFound(QuestionnaireNotFoundException $exception): WP_Error
    {
        return new WP_Error('stageart_questionnaire_not_found', $exception->getMessage(), ['status' => 404]);
    }

    private function invalid(InvalidArgumentException $exception): WP_Error
    {
        return new WP_Error('stageart_questionnaire_invalid', $exception->getMessage(), ['status' => 422]);
    }
}
