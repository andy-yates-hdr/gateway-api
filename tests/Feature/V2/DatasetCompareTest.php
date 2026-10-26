<?php

namespace Tests\Feature\V2;

use Config;
use Tests\TestCase;
use App\Http\Enums\TeamMemberOf;
use App\Models\Dataset;
use App\Models\DatasetVersion;
use App\Services\Compare\DataGetExpressionEvaluator;
use App\Services\Compare\ExpressionEvaluator;
use Tests\Traits\Authorization;
use Tests\Traits\MockExternalApis;

/**
 * POST /api/v2/datasets/compare. Covers retrieval, visibility, validation and response shape.
 */
class DatasetCompareTest extends TestCase
{
    use Authorization;
    use MockExternalApis {
        setUp as commonSetUp;
    }

    public const COMPARE_URL       = '/api/v2/datasets/compare';
    public const DATASETS_URL      = '/api/v2/datasets';
    public const TEAMS_URL         = '/api/v1/teams';
    public const NOTIFICATIONS_URL = '/api/v1/notifications';
    public const USERS_URL         = '/api/v1/users';

    private const TITLE = 'data.versions.0.metadata.metadata.summary.title';

    public function setUp(): void
    {
        $this->commonSetUp();

        Dataset::flushEventListeners();
        DatasetVersion::flushEventListeners();

        $this->app->bind(ExpressionEvaluator::class, DataGetExpressionEvaluator::class);
    }

    public function test_compare_returns_matrix_and_comparisons(): void
    {
        [$teamId, $userId] = $this->createTeamAndUser();
        $a = $this->createDataset($teamId, $userId, 'Alpha');
        $b = $this->createDataset($teamId, $userId, 'Beta');

        $response = $this->json('POST', self::COMPARE_URL, [
            'datasets' => [$a, $b],
            'primary_dataset' => $a,
            'paths' => [self::TITLE, 'data.versions.0.metadata.metadata.nothing.here'],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'success')
            ->assertJsonPath('data.matrix', [['Alpha', 'Beta'], [null, null]])
            ->assertJsonPath('data.comparisons', [[true, false], [false, false]]);
    }

    public function test_comparisons_are_false_without_a_primary(): void
    {
        [$teamId, $userId] = $this->createTeamAndUser();
        $a = $this->createDataset($teamId, $userId, 'Alpha');
        $b = $this->createDataset($teamId, $userId, 'Beta');

        $this->json('POST', self::COMPARE_URL, ['datasets' => [$a, $b], 'paths' => [self::TITLE]])
            ->assertStatus(200)
            ->assertJsonPath('data.comparisons', [[false, false]]);
    }

    public function test_missing_dataset_returns_404(): void
    {
        [$teamId, $userId] = $this->createTeamAndUser();
        $a = $this->createDataset($teamId, $userId, 'Alpha');

        $this->json('POST', self::COMPARE_URL, ['datasets' => [$a, 999999], 'paths' => [self::TITLE]])
            ->assertStatus(404);
    }

    public function test_non_active_dataset_is_indistinguishable_from_missing(): void
    {
        [$teamId, $userId] = $this->createTeamAndUser();
        $a = $this->createDataset($teamId, $userId, 'Alpha');
        $draft = $this->createDataset($teamId, $userId, 'Draft', Dataset::STATUS_DRAFT);

        $this->json('POST', self::COMPARE_URL, ['datasets' => [$a, $draft], 'paths' => [self::TITLE]])
            ->assertStatus(404)
            ->assertJsonPath('message', 'Dataset not found');
    }

    public function test_other_partner_dataset_is_not_visible(): void
    {
        [$teamId, $userId] = $this->createTeamAndUser();
        $a = $this->createDataset($teamId, $userId, 'Alpha');
        $b = $this->createDataset($teamId, $userId, 'Beta');

        $this->json('POST', self::COMPARE_URL, ['datasets' => [$a, $b], 'paths' => [self::TITLE]], ['x-partner-context' => 'CRUK'])
            ->assertStatus(404);
    }

    /**
     * @dataProvider invalidPayloads
     */
    public function test_invalid_payloads_return_400(array $payload): void
    {
        $this->json('POST', self::COMPARE_URL, $payload)->assertStatus(400);
    }

    public static function invalidPayloads(): array
    {
        return [
            'one dataset' => [['datasets' => [1], 'paths' => ['a']]],
            'too many datasets' => [['datasets' => [1, 2, 3, 4, 5, 6], 'paths' => ['a']]],
            'duplicate datasets' => [['datasets' => [1, 1], 'paths' => ['a']]],
            'primary not in datasets' => [['datasets' => [1, 2], 'primary_dataset' => 3, 'paths' => ['a']]],
            'no expressions' => [['datasets' => [1, 2], 'paths' => []]],
            'expression too long' => [['datasets' => [1, 2], 'paths' => [str_repeat('a', 501)]]],
            'schema without version' => [['datasets' => [1, 2], 'schema' => 'HDRUK', 'paths' => ['a']]],
        ];
    }

    private function createDataset(int $teamId, int $userId, string $title, string $status = Dataset::STATUS_ACTIVE): int
    {
        $metadata = $this->getMetadata();
        $metadata['metadata']['summary']['title'] = $title;

        $response = $this->json('POST', self::DATASETS_URL, [
            'team_id'       => $teamId,
            'user_id'       => $userId,
            'metadata'      => $metadata,
            'create_origin' => Dataset::ORIGIN_MANUAL,
            'status'        => $status,
        ], $this->header);

        $response->assertStatus(Config::get('statuscodes.STATUS_CREATED.code'));

        return $response->json('data');
    }

    private function createTeamAndUser(): array
    {
        $notificationId = $this->json('POST', self::NOTIFICATIONS_URL, [
            'notification_type' => 'applicationSubmitted',
            'message'           => 'Test',
            'email'             => null,
            'user_id'           => 3,
            'opt_in'            => 1,
            'enabled'           => 1,
        ], $this->header)->json('data');

        $teamId = $this->json('POST', self::TEAMS_URL, [
            'name'                        => 'Compare Test Team ' . uniqid(),
            'enabled'                     => 1,
            'allows_messaging'            => 1,
            'workflow_enabled'            => 1,
            'access_requests_management'  => 1,
            'uses_5_safes'                => 1,
            'is_admin'                    => 1,
            'member_of'                   => TeamMemberOf::OTHER,
            'contact_point'               => 'test@example.com',
            'application_form_updated_by' => 'Test',
            'application_form_updated_on' => '2023-04-06 15:44:41',
            'notifications'               => [$notificationId],
            'users'                       => [],
        ], $this->header)->json('data');

        $userId = $this->json('POST', self::USERS_URL, [
            'firstname'        => 'Test',
            'lastname'         => 'User',
            'email'            => 'test.compare.' . uniqid() . '@example.com',
            'password'         => 'Passw@rd1!',
            'sector_id'        => 1,
            'organisation'     => 'Test Org',
            'bio'              => 'Bio',
            'domain'           => 'https://example.com',
            'link'             => 'https://example.com/link',
            'orcid'            => 'https://orcid.org/00000000',
            'contact_feedback' => 1,
            'contact_news'     => 1,
        ], $this->header)->json('data');

        return [$teamId, $userId];
    }
}
