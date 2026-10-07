import { useLocalSearchParams, useRouter, type Href } from 'expo-router';
import { useState } from 'react';
import { ActivityIndicator, ScrollView, StyleSheet, TouchableOpacity, View } from 'react-native';

import { ThemedText } from '@/components/themed-text';
import { ThemedTextInput } from '@/components/themed-text-input';
import { BrandColors, Radius, Spacing } from '@/constants/theme';
import { useCreateOrganizationInvitation } from '@/features/organization/useOrganizations';
import { getErrorMessage } from '@/utils/errorMessage';

/**
 * StageArt 団体管理UI整理 "メンバーを追加": mirrors the Production
 * participant-add flow's own 氏名＋メールアドレス＋備考 shape
 * (productions/[id]/participants.tsx) - the single entry point behind
 * `POST /organizations/{id}/invitations`
 * (CreateOrganizationInvitationUseCase), which decides server-side (by
 * email) whether this becomes an existing Person's immediate Membership
 * or a new invitation email. No Role selector here - every added
 * Member starts as a plain MEMBER; elevating someone is a separate,
 * deliberate 代理人を設定 action.
 */
export default function OrganizationMembersAddScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const router = useRouter();
  const createInvitation = useCreateOrganizationInvitation(id);

  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [remarks, setRemarks] = useState('');
  const [resultMessage, setResultMessage] = useState<string | null>(null);

  const canSubmit = !!name.trim() && !!email.trim() && !createInvitation.isPending;

  async function handleSubmit() {
    if (!canSubmit) {
      return;
    }

    setResultMessage(null);

    try {
      const result = await createInvitation.mutateAsync({
        name: name.trim(),
        email: email.trim(),
        remarks: remarks.trim() || undefined,
      });

      if (result.outcome === 'MEMBER_ADDED') {
        setResultMessage(`${name.trim()} さんをメンバーに追加しました。`);
      } else if (result.outcome === 'INVITATION_CREATED') {
        setResultMessage(`${email.trim()} 宛に招待メールを送信しました。登録が完了するとメンバーに追加されます。`);
      } else {
        setResultMessage(`${email.trim()} 宛に招待メールを再送しました。`);
      }

      setName('');
      setEmail('');
      setRemarks('');
    } catch {
      // handled via createInvitation.isError below
    }
  }

  return (
    <ScrollView contentContainerStyle={styles.container}>
      <ThemedText type="title" style={styles.pageTitle}>
        メンバーを追加
      </ThemedText>

      <View style={styles.form}>
        <ThemedText type="small" themeColor="textSecondary">
          氏名
        </ThemedText>
        <ThemedTextInput testID="organization-members-add-name" value={name} onChangeText={setName} style={styles.input} />

        <ThemedText type="small" themeColor="textSecondary">
          メールアドレス
        </ThemedText>
        <ThemedTextInput
          testID="organization-members-add-email"
          value={email}
          onChangeText={setEmail}
          autoCapitalize="none"
          autoCorrect={false}
          keyboardType="email-address"
          style={styles.input}
        />

        <ThemedText type="small" themeColor="textSecondary">
          備考（任意）
        </ThemedText>
        <ThemedTextInput testID="organization-members-add-remarks" value={remarks} onChangeText={setRemarks} style={styles.input} />

        {createInvitation.isError && (
          <ThemedText testID="organization-members-add-error" style={styles.error}>
            {getErrorMessage(createInvitation.error)}
          </ThemedText>
        )}

        {resultMessage && (
          <ThemedText testID="organization-members-add-result" style={styles.success}>
            {resultMessage}
          </ThemedText>
        )}

        <View style={styles.actions}>
          <TouchableOpacity
            testID="organization-members-add-submit"
            onPress={handleSubmit}
            disabled={!canSubmit}
            style={[styles.button, !canSubmit && styles.buttonDisabled]}
          >
            {createInvitation.isPending ? <ActivityIndicator color="#fff" /> : <ThemedText style={styles.buttonText}>追加する</ThemedText>}
          </TouchableOpacity>
          <TouchableOpacity testID="organization-members-add-back" onPress={() => router.push(`/organizations/${id}/members` as Href)}>
            <ThemedText type="link">メンバー管理へ戻る</ThemedText>
          </TouchableOpacity>
        </View>
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { padding: Spacing.four, gap: Spacing.one },
  pageTitle: { marginBottom: Spacing.four },
  form: { gap: Spacing.one, maxWidth: 480 },
  input: {
    borderWidth: 1,
    borderColor: '#ccc',
    borderRadius: 8,
    paddingHorizontal: Spacing.three,
    paddingVertical: Spacing.two,
    fontSize: 16,
    marginBottom: Spacing.two,
  },
  error: { color: '#a6483a', marginBottom: Spacing.two },
  success: { color: '#2e7d32', marginBottom: Spacing.two },
  actions: { flexDirection: 'row', alignItems: 'center', gap: Spacing.four, marginTop: Spacing.two },
  button: {
    backgroundColor: BrandColors.warmAmber,
    borderRadius: Radius.medium,
    paddingVertical: Spacing.two,
    paddingHorizontal: Spacing.four,
    alignItems: 'center',
  },
  buttonDisabled: { opacity: 0.6 },
  buttonText: { color: '#fff', fontWeight: '600' },
});
