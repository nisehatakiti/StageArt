import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';
import { TouchableOpacity, View } from 'react-native';

// Explicit `.web` filename bypasses Jest/Metro's platform-extension
// resolution (which otherwise picks confirmAlert.ts for this native test
// environment) - this file tests the Web implementation directly. See
// confirmAlert.web.tsx's own docblock for why it exists: react-native-web's
// own Alert.alert is a no-op, so Web needs its own confirm dialog.
import { ConfirmAlertHost, confirmAlert } from '@/utils/confirmAlert.web';
import { ThemedText } from '@/components/themed-text';

/** confirmAlert() is always invoked from a real event handler in
 * practice (see WebLayout.tsx/WebProfileContent.tsx's handleLogout) -
 * this tiny trigger button reproduces that exactly. */
function ConfirmAlertTrigger({ title, message, onCancel, onConfirm }: { title: string; message: string; onCancel: () => void; onConfirm: () => void }) {
  return (
    <TouchableOpacity
      testID="trigger"
      onPress={() =>
        confirmAlert(title, message, [
          { text: 'キャンセル', style: 'cancel', onPress: onCancel },
          { text: '確定', style: 'destructive', onPress: onConfirm },
        ])
      }
    >
      <ThemedText>開く</ThemedText>
    </TouchableOpacity>
  );
}

describe('confirmAlert (Web)', () => {
  it('renders the requested title/message/buttons once <ConfirmAlertHost/> is mounted, and calls the pressed button\'s onPress', async () => {
    const onCancel = jest.fn();
    const onConfirm = jest.fn();

    render(
      <View>
        <ConfirmAlertHost />
        <ConfirmAlertTrigger title="ログアウト" message="ログアウトしますか？" onCancel={onCancel} onConfirm={onConfirm} />
      </View>
    );

    await waitFor(() => expect(screen.getByTestId('trigger')).toBeVisible());
    fireEvent.press(screen.getByTestId('trigger'));

    await waitFor(() => expect(screen.getByText('ログアウト')).toBeVisible());
    expect(screen.getByText('ログアウトしますか？')).toBeVisible();

    fireEvent.press(screen.getByTestId('confirm-alert-button-1'));

    expect(onConfirm).toHaveBeenCalledTimes(1);
    expect(onCancel).not.toHaveBeenCalled();
  });

  it('dismisses without calling any onPress when cancel is pressed, and does not call the other button', async () => {
    const onCancel = jest.fn();
    const onConfirm = jest.fn();

    render(
      <View>
        <ConfirmAlertHost />
        <ConfirmAlertTrigger title="コメントを削除" message="このコメントを削除しますか？" onCancel={onCancel} onConfirm={onConfirm} />
      </View>
    );

    await waitFor(() => expect(screen.getByTestId('trigger')).toBeVisible());
    fireEvent.press(screen.getByTestId('trigger'));
    await waitFor(() => expect(screen.getByTestId('confirm-alert-button-0')).toBeVisible());

    fireEvent.press(screen.getByTestId('confirm-alert-button-0'));

    expect(onCancel).toHaveBeenCalledTimes(1);
    expect(onConfirm).not.toHaveBeenCalled();
    await waitFor(() => expect(screen.queryByText('コメントを削除')).toBeNull());
  });

  it('does not throw when confirmAlert() is called before any host is mounted', () => {
    expect(() => confirmAlert('タイトル', 'メッセージ', [{ text: 'OK' }])).not.toThrow();
  });
});
