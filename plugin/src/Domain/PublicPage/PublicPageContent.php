<?php

declare(strict_types=1);

namespace StageArt\Domain\PublicPage;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use StageArt\Domain\Shared\Uuid;

/**
 * 自由コンテンツ of one public page: StageArtCore's `stageart_free_content`
 * (FreeContentAdmin) - a title, a body and a status, where only
 * "publish" content is ever shown publicly and "draft" is kept but hidden
 * (Core FreeContentAdmin::canReference() / isPublished()).
 *
 * Owned by one page (site + owner id), as Core's owner meta ties a content
 * to the TOP page or to one Production; a content is never shared between
 * pages.
 *
 * Unlike Core (a WordPress editor body run through wp_kses_post()), the
 * body is plain text: the public pages are rendered by the app, which
 * shows text, not stored HTML. Line breaks are kept.
 */
final class PublicPageContent
{
    public const STATUS_PUBLISH = 'publish';
    public const STATUS_DRAFT = 'draft';

    public const TITLE_MAX_LENGTH = 200;
    public const BODY_MAX_LENGTH = 20000;

    private string $id;
    private PublicPageSite $site;
    private string $ownerId;
    private string $title;
    private string $body;
    private string $status;
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;

    private function __construct(
        string $id,
        PublicPageSite $site,
        string $ownerId,
        string $title,
        string $body,
        string $status,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt
    ) {
        $this->id = $id;
        $this->site = $site;
        $this->ownerId = $ownerId;
        $this->title = $title;
        $this->body = $body;
        $this->status = $status;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    public static function create(PublicPageSite $site, string $ownerId, string $title, string $body, string $status): self
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        return new self(Uuid::generate(), $site, $ownerId, self::validTitle($title), self::validBody($body), self::validStatus($status), $now, $now);
    }

    public static function reconstitute(
        string $id,
        PublicPageSite $site,
        string $ownerId,
        string $title,
        string $body,
        string $status,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt
    ): self {
        return new self($id, $site, $ownerId, $title, $body, $status === self::STATUS_PUBLISH ? self::STATUS_PUBLISH : self::STATUS_DRAFT, $createdAt, $updatedAt);
    }

    public function edit(string $title, string $body, string $status): self
    {
        return new self(
            $this->id,
            $this->site,
            $this->ownerId,
            self::validTitle($title),
            self::validBody($body),
            self::validStatus($status),
            $this->createdAt,
            new DateTimeImmutable('now', new DateTimeZone('UTC'))
        );
    }

    public function belongsTo(PublicPageSite $site, string $ownerId): bool
    {
        return $this->site->equals($site) && $this->ownerId === $ownerId;
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISH;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function site(): PublicPageSite
    {
        return $this->site;
    }

    public function ownerId(): string
    {
        return $this->ownerId;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    private static function validTitle(string $title): string
    {
        $title = trim((string) preg_replace('/\s+/u', ' ', $title));

        if ($title === '') {
            throw new InvalidArgumentException('タイトルを入力してください。');
        }

        if (mb_strlen($title) > self::TITLE_MAX_LENGTH) {
            throw new InvalidArgumentException(sprintf('タイトルは%d文字以内で入力してください。', self::TITLE_MAX_LENGTH));
        }

        return $title;
    }

    private static function validBody(string $body): string
    {
        $body = trim(str_replace(["\r\n", "\r"], "\n", $body));

        if (mb_strlen($body) > self::BODY_MAX_LENGTH) {
            throw new InvalidArgumentException(sprintf('本文は%d文字以内で入力してください。', self::BODY_MAX_LENGTH));
        }

        return $body;
    }

    private static function validStatus(string $status): string
    {
        if (! in_array($status, [self::STATUS_PUBLISH, self::STATUS_DRAFT], true)) {
            throw new InvalidArgumentException('status must be "publish" or "draft".');
        }

        return $status;
    }
}
