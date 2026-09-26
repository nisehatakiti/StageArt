import { forwardRef, createElement, type CSSProperties } from 'react';
import { Platform, TextInput, type TextInput as TextInputInstance, type TextInputProps } from 'react-native';

import { ThemeColor } from '@/constants/theme';
import { useTheme } from '@/hooks/use-theme';

export type FormInputKind = 'text' | 'date' | 'time' | 'textarea';

export type FormInputProps = TextInputProps & {
  themeColor?: ThemeColor;
  kind?: FormInputKind;
};

export const FormInput = forwardRef<TextInputInstance, FormInputProps>(function FormInput(
  { style, themeColor, placeholderTextColor, kind = 'text', multiline, onChangeText, testID, ...rest },
  ref
) {
  const theme = useTheme();

  if (Platform.OS === 'web' && kind !== 'text') {
    const flatStyle = (Array.isArray(style) ? Object.assign({}, ...style) : style) as CSSProperties | undefined;
    const baseStyle: CSSProperties = {
      boxSizing: 'border-box',
      width: '100%',
      minHeight: kind === 'textarea' ? 120 : 42,
      border: '1px solid #d7d2ca',
      borderRadius: 8,
      padding: kind === 'textarea' ? '10px 12px' : '8px 12px',
      fontSize: 16,
      lineHeight: '24px',
      color: theme[themeColor ?? 'text'],
      backgroundColor: '#fff',
      fontFamily: 'inherit',
      outline: 'none',
      ...(flatStyle ?? {}),
    };

    if (kind === 'textarea') {
      return createElement('textarea', {
        ...rest,
        ref: ref as never,
        value: rest.value ?? '',
        placeholder: rest.placeholder,
        onChange: (event: { target: { value: string } }) => onChangeText?.(event.target.value),
        'data-testid': testID,
        style: baseStyle,
      });
    }

    return createElement('input', {
      ...rest,
      ref: ref as never,
      type: kind,
      value: rest.value ?? '',
      placeholder: rest.placeholder,
      onChange: (event: { target: { value: string } }) => onChangeText?.(event.target.value),
      'data-testid': testID,
      style: baseStyle,
    });
  }

  return (
    <TextInput
      ref={ref}
      style={[{ color: theme[themeColor ?? 'text'] }, style]}
      placeholderTextColor={placeholderTextColor ?? theme.textSecondary}
      multiline={kind === 'textarea' || multiline}
      {...rest}
      onChangeText={onChangeText}
      testID={testID}
    />
  );
});
