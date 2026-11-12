import { Stack } from 'expo-router';

/** Nested Stack for the 予定 tab: list -> Schedule Item Detail (§20),
 * with its own native back affordance while the bottom Tab bar stays
 * visible (the Tabs navigator itself is unaffected by this inner
 * Stack). */
export default function ScheduleTabLayout() {
  return (
    <Stack screenOptions={{ headerShown: false }}>
      <Stack.Screen name="index" />
      <Stack.Screen name="[itemId]" />
    </Stack>
  );
}
