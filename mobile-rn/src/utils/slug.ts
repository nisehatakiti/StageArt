/**
 * Mirrors the Backend's OrganizationSlug/ProductionSlug validation
 * (lowercase a-z0-9-, 3-64 chars, no leading/trailing/double hyphen) for
 * immediate client-side feedback - the Backend remains the source of
 * truth (its own DB UNIQUE constraint is the final guard against a
 * race), this is purely UX.
 */
export const SLUG_PATTERN = /^[a-z0-9]+(-[a-z0-9]+)*$/;
export const SLUG_MIN_LENGTH = 3;
export const SLUG_MAX_LENGTH = 64;

export function isValidSlug(slug: string): boolean {
  return slug.length >= SLUG_MIN_LENGTH && slug.length <= SLUG_MAX_LENGTH && SLUG_PATTERN.test(slug);
}

function slugify(input: string): string {
  return input
    .trim()
    .toLowerCase()
    .replace(/[^a-z0-9\s-]/g, '')
    .replace(/[\s_]+/g, '-')
    .replace(/-+/g, '-')
    .replace(/^-+|-+$/g, '');
}

function randomSuffix(length = 6): string {
  const chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
  let out = '';
  for (let i = 0; i < length; i += 1) {
    out += chars[Math.floor(Math.random() * chars.length)];
  }
  return out;
}

/**
 * `fallbackPrefix` is used when the entered name has no usable ASCII
 * content once stripped (the common case for Japanese names, which is
 * most StageArt Organization/Production names) - automatic kanji/kana
 * -> romaji transliteration is unreliable, so this deliberately falls
 * back to a short random slug rather than guessing. The user always
 * sees and can edit the result before saving, never required to type in
 * English themselves.
 */
export function suggestSlug(name: string, fallbackPrefix: string): string {
  const slugified = slugify(name);

  if (isValidSlug(slugified)) {
    return slugified;
  }

  return `${fallbackPrefix}-${randomSuffix()}`;
}
