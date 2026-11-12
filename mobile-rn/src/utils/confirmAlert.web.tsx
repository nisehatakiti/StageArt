import { useEffect, useState } from 'react';
import { Modal, StyleSheet, TouchableOpacity, View } from 'react-native';

import { ThemedText } from '@/components/themed-text';
import { Radius, Spacing } from '@/constants/theme';

export type ConfirmAlertButton = {
  text: string;
  style?: 'default' | 'cancel' | 'destructive';
  onPress?: () => void;
};

type ConfirmAlertRequest = { title: string; message: string; buttons: ConfirmAlertButton[] };

/**
 * StageArt Webログアウト修正 Phase: react-native-web's own Alert.alert is
 * a complete no-op (`static alert() {}` -
 * node_modules/react-native-web/src/exports/Alert/index.js) - every
 * confirm()-gated action on Web (logout, comment deletion) silently did
 * nothing when built against it, confirmed via real-browser testing
 * (no dialog, no console output, no API call). confirmAlert() is the Web
 * half of a Platform-split replacement (see confirmAlert.ts for Native's
 * half, which still delegates to the real Alert.alert unchanged) - same
 * call signature, so call sites only change their import.
 *
 * A React Native `Modal` (react-native-web has a real implementation of
 * this, unlike Alert) rendered by <ConfirmAlertHost/> - mounted once
 * near the app root (_layout.tsx) - is the render target; this module-
 * level `showRequest` ref is the imperative-call -> declarative-render
 * bridge, the same pattern a toast/snackbar host uses. If the host isn't
 * mounted yet, the request is dropped with a console.warn rather than
 * throwing - matching Alert.alert's own fire-and-forget signature (no
 * return value callers already rely on).
 */
let showRequest: ((request: ConfirmAlertRequest) => void) | null = null;

export function confirmAlert(title: string, message: string, buttons: ConfirmAlertButton[]): void {
  if (!showRequest) {
    console.warn('confirmAlert: <ConfirmAlertHost/> is not mounted; dropping dialog', title);
    return;
  }
  showRequest({ title, message, buttons });
}

export function ConfirmAlertHost() {
  const [request, setRequest] = useState<ConfirmAlertRequest | null>(null);

  useEffect(() => {
    showRequest = setRequest;
    return () => {
      showRequest = null;
    };
  }, []);

  function close() {
    setRequest(null);
  }

  function handlePress(button: ConfirmAlertButton) {
    close();
    button.onPress?.();
  }

  if (!request) {
    return null;
  }

  return (
    <Modal transparent animationType="fade" visible onRequestClose={close}>
      <View style={styles.backdrop}>
        <View style={styles.card} testID="confirm-alert-modal">
          <ThemedText type="smallBold" style={styles.title}>
            {request.title}
          </ThemedText>
          <ThemedText type="default" style={styles.message}>
            {request.message}
          </ThemedText>
          <View style={styles.buttonRow}>
            {request.buttons.map((button, index) => (
              <TouchableOpacity
                key={index}
                testID={`confirm-alert-button-${index}`}
                onPress={() => handlePress(button)}
                style={[styles.button, button.style === 'destructive' ? styles.destructiveButton : styles.defaultButton]}
              >
                <ThemedText style={button.style === 'destructive' ? styles.destructiveButtonText : styles.defaultButtonText}>
                  {button.text}
                </ThemedText>
              </TouchableOpacity>
            ))}
          </View>
        </View>
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  backdrop: {
    flex: 1,
    backgroundColor: 'rgba(10, 10, 10, 0.5)',
    alignItems: 'center',
    justifyContent: 'center',
    padding: Spacing.four,
  },
  card: {
    width: '100%',
    maxWidth: 360,
    backgroundColor: '#fff',
    borderRadius: Radius.medium,
    borderWidth: 1,
    borderColor: '#e1dee6',
    padding: Spacing.four,
    gap: Spacing.two,
  },
  title: { fontSize: 18 },
  message: { color: '#60646C' },
  buttonRow: {
    flexDirection: 'row',
    justifyContent: 'flex-end',
    gap: Spacing.two,
    marginTop: Spacing.two,
  },
  button: {
    borderRadius: Radius.small,
    paddingVertical: Spacing.two,
    paddingHorizontal: Spacing.three,
  },
  defaultButton: { backgroundColor: '#F0F0F3' },
  defaultButtonText: { color: '#000' },
  destructiveButton: { backgroundColor: '#a6483a' },
  destructiveButtonText: { color: '#fff', fontWeight: '600' },
});
