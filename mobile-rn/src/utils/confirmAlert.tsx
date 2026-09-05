import { Alert } from 'react-native';

export type ConfirmAlertButton = {
  text: string;
  style?: 'default' | 'cancel' | 'destructive';
  onPress?: () => void;
};

/**
 * StageArt Webログアウト修正 Phase: Native's half of the Platform-split
 * confirm dialog (see confirmAlert.web.tsx for Web's half, and that
 * file's docblock for why this split exists at all - react-native-web's
 * own Alert.alert is a no-op). Same call signature as Alert.alert,
 * delegating to it unchanged, so existing Native behavior (and every
 * test that does `jest.spyOn(Alert, 'alert')`) is untouched.
 */
export function confirmAlert(title: string, message: string, buttons: ConfirmAlertButton[]): void {
  Alert.alert(title, message, buttons);
}

/** No-op on Native: Alert.alert already works natively, so there is
 * nothing for this host to render. See confirmAlert.web.tsx for the
 * real implementation. */
export function ConfirmAlertHost() {
  return null;
}
