export const PARTICIPANT_TYPE_LABEL: Record<string, string> = { CAST: '出演者', STAFF: 'スタッフ' };

/** R14-B §7.2: why an invitation cannot be accepted now (it can still be declined). */
export const UNAVAILABLE_REASON_LABEL: Record<string, string> = {
  PRODUCTION_CLOSED: '公演が終了または中止されたため、承諾できません',
  INVITER_REVOKED: '招待した管理者の権限がなくなったため、承諾できません',
};
