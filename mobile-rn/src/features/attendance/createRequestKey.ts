import { useState } from 'react';

/**
 * PR #7 R12 (docs/20-RehearsalManagementScreen.md §26): the idempotency key
 * of a Rehearsal create request.
 *
 * - The first send of a form's content gets a new key.
 * - Sending the same content again (e.g. after a network error, when the
 *   app cannot know whether the first send was created) reuses that key,
 *   so the Backend answers with the Rehearsal already created instead of
 *   creating a second one.
 * - Changed content is a new create action and gets a new key (the
 *   Backend would answer 409 to the old key with different content).
 * - After a successful create the key is forgotten, so the next create on
 *   the same screen is a new one.
 *
 * This does not rely on the submit button being disabled while sending.
 */
export function generateIdempotencyKey(): string {
  if (typeof crypto !== 'undefined' && 'randomUUID' in crypto) {
    return crypto.randomUUID();
  }
  return `rehearsal-${Date.now()}-${Math.random().toString(36).slice(2)}`;
}

export class CreateRequestKey {
  private content: string | null = null;
  private key: string | null = null;

  constructor(private readonly generate: () => string = generateIdempotencyKey) {}

  /** The key to send with this content. */
  keyFor(content: unknown): string {
    const serialized = JSON.stringify(content);
    if (this.key === null || serialized !== this.content) {
      this.key = this.generate();
      this.content = serialized;
    }
    return this.key;
  }

  /** Forget the key after a successful create. */
  reset(): void {
    this.key = null;
    this.content = null;
  }
}

/** One CreateRequestKey per mounted screen. */
export function useCreateRequestKey(): CreateRequestKey {
  const [requestKey] = useState(() => new CreateRequestKey());
  return requestKey;
}
