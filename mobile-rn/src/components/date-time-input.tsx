import { createElement, useState, type ChangeEvent } from 'react';
import { Platform, StyleSheet, TextInput, type TextInputProps } from 'react-native';

type DateTimeInputProps = Pick<TextInputProps, 'value' | 'onChangeText' | 'placeholder' | 'editable' | 'testID'> & {
  mode: 'date' | 'time';
};

const HOURS = Array.from({ length: 24 }, (_, hour) => String(hour).padStart(2, '0'));
const MINUTES = Array.from({ length: 12 }, (_, index) => String(index * 5).padStart(2, '0'));

export function DateTimeInput({ mode, value, onChangeText, placeholder, editable = true, testID }: DateTimeInputProps) {
  const [timePickerOpen, setTimePickerOpen] = useState(false);
  const currentValue = value ?? '';
  const [selectedHour, selectedMinute] = currentValue.split(':');
  const hourValue = HOURS.includes(selectedHour) ? selectedHour : '18';
  const minuteValue = MINUTES.includes(selectedMinute) ? selectedMinute : '00';

  if (Platform.OS === 'web') {
    if (mode === 'time') {
      const setTimePart = (hour: string, minute: string) => {
        onChangeText?.(`${hour}:${minute}`);
      };

      return createElement(
        'div',
        {
          style: { position: 'relative', width: '100%', marginBottom: 8 },
          'data-testid': testID ? `${testID}-container` : undefined,
        },
        createElement('input', {
          type: 'text',
          inputMode: 'none',
          readOnly: true,
          value: currentValue,
          placeholder: placeholder ?? '--:--',
          disabled: !editable,
          'aria-label': '時刻（5分刻み）',
          'data-testid': testID,
          onClick: () => editable && setTimePickerOpen(true),
          onFocus: () => editable && setTimePickerOpen(true),
          onChange: (event: ChangeEvent<HTMLInputElement>) => {
            const candidate = event.target.value;
            const match = /^(\d{2}):(\d{2})$/.exec(candidate);
            if (match && Number(match[2]) % 5 === 0 && Number(match[1]) <= 23 && Number(match[2]) <= 59) {
              onChangeText?.(candidate);
            }
          },
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
            cursor: editable ? 'pointer' : 'not-allowed',
          },
        }),
        timePickerOpen && editable
          ? createElement(
              'div',
              {
                role: 'group',
                'aria-label': '時刻を選択（5分刻み）',
                'data-testid': testID ? `${testID}-picker` : undefined,
                style: {
                  position: 'absolute',
                  zIndex: 20,
                  top: 48,
                  left: 0,
                  display: 'flex',
                  alignItems: 'center',
                  gap: 8,
                  padding: 12,
                  backgroundColor: '#fff',
                  border: '1px solid #d7d3df',
                  borderRadius: 8,
                  boxShadow: '0 4px 12px rgba(0,0,0,0.12)',
                },
              },
              createElement(
                'select',
                {
                  'aria-label': '時',
                  'data-testid': testID ? `${testID}-hour` : undefined,
                  value: hourValue,
                  onChange: (event: ChangeEvent<HTMLSelectElement>) => setTimePart(event.target.value, minuteValue),
                  style: { minHeight: 40, minWidth: 76, border: '1px solid #ccc', borderRadius: 6, padding: '6px 8px', fontSize: 16, backgroundColor: '#fff' },
                },
                ...HOURS.map((hour) => createElement('option', { key: hour, value: hour }, hour)),
              ),
              createElement('span', { 'aria-hidden': true, style: { fontSize: 18, fontWeight: 600 } }, ':'),
              createElement(
                'select',
                {
                  'aria-label': '分（5分刻み）',
                  'data-testid': testID ? `${testID}-minute` : undefined,
                  value: minuteValue,
                  onChange: (event: ChangeEvent<HTMLSelectElement>) => setTimePart(hourValue, event.target.value),
                  style: { minHeight: 40, minWidth: 76, border: '1px solid #ccc', borderRadius: 6, padding: '6px 8px', fontSize: 16, backgroundColor: '#fff' },
                },
                ...MINUTES.map((minute) => createElement('option', { key: minute, value: minute }, minute)),
              ),
              createElement(
                'button',
                {
                  type: 'button',
                  'data-testid': testID ? `${testID}-close` : undefined,
                  onClick: () => setTimePickerOpen(false),
                  style: { minHeight: 40, border: '1px solid #ccc', borderRadius: 6, padding: '6px 10px', backgroundColor: '#fff', cursor: 'pointer' },
                },
                '決定',
              ),
            )
          : null,
      );
    }

    return createElement('input', {
      type: mode,
      value: currentValue,
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
