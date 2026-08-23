import { forwardRef } from 'react';
import { TextInput, type TextInput as TextInputInstance, type TextInputProps } from 'react-native';

import { ThemeColor } from '@/constants/theme';
import { useTheme } from '@/hooks/use-theme';

/**
 * A real-device bug: every TextInput in this app (login/register/reset-
 * password/mypage/etc.) rendered with react-native's default text
 * color (effectively black), never reading from the app's own theme -
 * unlike ThemedText/ThemedView, no themed input existed at all. On a
 * dark background (system dark mode, or any future dark-styled screen)
 * typed characters were invisible - black-on-dark, not merely low
 * contrast. This wraps plain TextInput the same way ThemedText wraps
 * Text, so every input field automatically tracks the active theme's
 * text/placeholder colors without each screen having to remember to
 * set them itself.
 */
export type ThemedTextInputProps = TextInputProps & {
  themeColor?: ThemeColor;
};

export const ThemedTextInput = forwardRef<TextInputInstance, ThemedTextInputProps>(function ThemedTextInput(
  { style, themeColor, placeholderTextColor, ...rest },
  ref
) {
  const theme = useTheme();

  return (
    <TextInput
      ref={ref}
      style={[{ color: theme[themeColor ?? 'text'] }, style]}
      placeholderTextColor={placeholderTextColor ?? theme.textSecondary}
      {...rest}
    />
  );
});
