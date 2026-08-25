import { useRouter } from 'expo-router';
import { useState } from 'react';
import { ActivityIndicator, ScrollView, StyleSheet, TouchableOpacity } from 'react-native';

import { useAuth } from '@/auth/AuthContext';
import { AppShell } from '@/components/app-shell';
import { ThemedText } from '@/components/themed-text';
import { ThemedTextInput } from '@/components/themed-text-input';
import { ThemedView } from '@/components/themed-view';
import { BrandColors, Radius, Spacing } from '@/constants/theme';
import { resolveJoinKey } from '@/features/joinKey/api';
import { useRequestOrganizationMembership } from '@/features/membership/useMembership';
import { useRequestProductionParticipation } from '@/features/participation/useParticipation';
import { useQuery } from '@tanstack/react-query';
import type { ResolvedJoinKey } from '@/types/api';
import { getErrorMessage } from '@/utils/errorMessage';

/**
 * docs/03-InitialOnboardingAndJoinKey.md §09.2's single "参加コードを入力"
 * entry point: resolves a code to its target, shows a confirmation
 * before actually creating a Membership/Participant Request ("確認なし
 * に即時参加を確定してはならない").
 */
export default function JoinScreen() {
  const { apiClient } = useAuth();
  const router = useRouter();

  const [code, setCode] = useState('');
  const [submittedCode, setSubmittedCode] = useState<string | null>(null);
  const [participantType, setParticipantType] = useState<'CAST' | 'STAFF'>('CAST');

  const resolveQuery = useQuery<ResolvedJoinKey>({
    queryKey: ['resolve-join-key', submittedCode],
    queryFn: () => resolveJoinKey(apiClient, submittedCode as string),
    enabled: !!submittedCode,
    retry: false,
  });

  const requestMembership = useRequestOrganizationMembership();
  const requestParticipation = useRequestProductionParticipation();

  const confirmed = requestMembership.isSuccess || requestParticipation.isSuccess;

  function handleCheck() {
    setSubmittedCode(code.trim());
  }

  function handleConfirm() {
    if (!resolveQuery.data) {
      return;
    }

    if (resolveQuery.data.target_type === 'ORGANIZATION') {
      requestMembership.mutate({ joinKeyCode: submittedCode as string });
    } else {
      requestParticipation.mutate({ joinKeyCode: submittedCode as string, participantType });
    }
  }

  return (
    <AppShell scroll>
      <ScrollView contentContainerStyle={styles.container}>
        <ThemedText type="title" style={styles.title}>
          団体・公演に参加
        </ThemedText>
        <ThemedText themeColor="textSecondary">団体・公演の管理者から受け取った参加コードを入力してください。</ThemedText>

        <ThemedTextInput
          testID="join-code-input"
          placeholder="AB7K29XZ"
          value={code}
          onChangeText={setCode}
          autoCapitalize="characters"
          autoCorrect={false}
          style={styles.input}
        />

        <TouchableOpacity
          testID="join-code-check"
          onPress={handleCheck}
          disabled={!code.trim() || resolveQuery.isFetching}
          style={[styles.button, (!code.trim() || resolveQuery.isFetching) && styles.buttonDisabled]}
        >
          {resolveQuery.isFetching ? <ActivityIndicator color="#fff" /> : <ThemedText style={styles.buttonText}>確認する</ThemedText>}
        </TouchableOpacity>

        {resolveQuery.isError && (
          <ThemedText testID="join-code-error" style={styles.error}>
            {getErrorMessage(resolveQuery.error)}
          </ThemedText>
        )}

        {resolveQuery.data && !confirmed && (
          <ThemedView style={styles.confirmCard} testID="join-code-confirm">
            <ThemedText type="small" themeColor="textSecondary">
              {resolveQuery.data.target_type === 'ORGANIZATION' ? 'これは団体への参加コードです' : 'これは公演・活動への参加コードです'}
            </ThemedText>
            <ThemedText type="title">{resolveQuery.data.target_name}</ThemedText>

            {resolveQuery.data.target_type === 'PRODUCTION' && (
              <ThemedView style={styles.typeRow}>
                <TouchableOpacity
                  testID="join-participant-type-cast"
                  onPress={() => setParticipantType('CAST')}
                  style={[styles.typeOption, participantType === 'CAST' && styles.typeOptionSelected]}
                >
                  <ThemedText>出演者として</ThemedText>
                </TouchableOpacity>
                <TouchableOpacity
                  testID="join-participant-type-staff"
                  onPress={() => setParticipantType('STAFF')}
                  style={[styles.typeOption, participantType === 'STAFF' && styles.typeOptionSelected]}
                >
                  <ThemedText>スタッフとして</ThemedText>
                </TouchableOpacity>
              </ThemedView>
            )}

            <TouchableOpacity
              testID="join-code-confirm-button"
              onPress={handleConfirm}
              disabled={requestMembership.isPending || requestParticipation.isPending}
              style={styles.button}
            >
              {requestMembership.isPending || requestParticipation.isPending ? (
                <ActivityIndicator color="#fff" />
              ) : (
                <ThemedText style={styles.buttonText}>参加を申請する</ThemedText>
              )}
            </TouchableOpacity>

            {(requestMembership.isError || requestParticipation.isError) && (
              <ThemedText style={styles.error}>{getErrorMessage(requestMembership.error ?? requestParticipation.error)}</ThemedText>
            )}
          </ThemedView>
        )}

        {confirmed && (
          <ThemedView style={styles.confirmCard} testID="join-code-success">
            <ThemedText type="title">申請しました</ThemedText>
            <ThemedText themeColor="textSecondary">管理者の承認をお待ちください。</ThemedText>
            <TouchableOpacity testID="join-code-done" onPress={() => router.replace('/home')}>
              <ThemedText type="link">Homeへ戻る</ThemedText>
            </TouchableOpacity>
          </ThemedView>
        )}
      </ScrollView>
    </AppShell>
  );
}

const styles = StyleSheet.create({
  container: { padding: Spacing.four, gap: Spacing.three },
  title: { fontSize: 22, lineHeight: 28 },
  input: {
    borderWidth: 1,
    borderColor: '#ccc',
    borderRadius: 8,
    paddingHorizontal: Spacing.three,
    paddingVertical: Spacing.two,
    fontSize: 18,
    letterSpacing: 2,
  },
  button: {
    backgroundColor: BrandColors.warmAmber,
    borderRadius: Radius.medium,
    paddingVertical: Spacing.three,
    alignItems: 'center',
  },
  buttonDisabled: { opacity: 0.6 },
  buttonText: { color: '#fff', fontWeight: '600' },
  error: { color: '#a6483a' },
  confirmCard: {
    borderWidth: 1,
    borderColor: BrandColors.warmAmber,
    borderRadius: Radius.medium,
    padding: Spacing.four,
    gap: Spacing.two,
  },
  typeRow: { flexDirection: 'row', gap: Spacing.two },
  typeOption: {
    flex: 1,
    borderWidth: 1,
    borderColor: '#ccc',
    borderRadius: Radius.medium,
    paddingVertical: Spacing.two,
    alignItems: 'center',
  },
  typeOptionSelected: { borderColor: BrandColors.warmAmber, backgroundColor: '#FBEFDD' },
});
