/**
 * PR #7 R14-A (F9): the same rule as the Backend's WebUrl - a link or image
 * URL that came from user input is used only when it is an absolute
 * http(s) URL with a host. The Backend now refuses anything else, but rows
 * saved before R14-A may still hold e.g. `javascript://…`; this is the
 * last check before opening one (react-native-web's Linking.openURL calls
 * window.open with the value as given).
 */
const CONTROL_OR_SPACE = /[\u0000- \u007F]/;

export function isHttpUrl(value: string | null | undefined): value is string {
  if (!value || CONTROL_OR_SPACE.test(value)) {
    return false;
  }
  if (!/^https?:\/\//i.test(value)) {
    return false;
  }
  try {
    const url = new URL(value);
    return (url.protocol === 'http:' || url.protocol === 'https:') && url.hostname !== '';
  } catch {
    return false;
  }
}
