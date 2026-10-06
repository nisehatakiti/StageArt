import { createElement, type ChangeEvent } from 'react';
import { Platform, StyleSheet, TextInput, type TextInputProps } from 'react-native';

type DateTimeInputProps = Pick<TextInputProps, 'value' | 'onChangeText' | 'placeholder' | 'editable' | 'testID'> & {
  mode: 'date' | 'time';
};

export function DateTimeInput({ mode, value, onChangeText, placeholder, editable = true, testID }: DateTimeInputProps) {
  if (Platform.OS === 'web') {
    return createElement('input', {
      type: mode,
      step: mode === 'time' ? 300 : undefined,
      value: value ?? '',
      onChange: (event: ChangeEvent<HTMLInputElement>) => onChangeText?.(event.target.value),
      disabled: !editable,
      placeholder,
      'data-testid': testID,
      style: {
        width: '100%',
        minHeight: 44,
        boxSizing: 'border-box',
        border: '1px solid #ccc',
        borderRadius: 8,
        padding: '8px 12px',
        fontSize: 16,
        backgroundColor: editable ? '#fff' : '#f3f3f3',
        color: '#111',
      },
    });
  }

  return (
    <TextInput
      testID={testID}
      value={value}
      onChangeText={onChangeText}
      placeholder={placeholder}
      editable={editable}
      keyboardType={mode === 'time' ? 'numbers-and-punctuation' : 'numbers-and-punctuation'}
      autoCapitalize="none"
      style={styles.nativeInput}
    />
  );
}

const styles = StyleSheet.create({
  nativeInput: {
    borderWidth: 1,
    borderColor: '#ccc',
    borderRadius: 8,
    paddingHorizontal: 12,
    paddingVertical: 8,
    fontSize: 16,
    minHeight: 44,
    marginBottom: 8,
  },
});
