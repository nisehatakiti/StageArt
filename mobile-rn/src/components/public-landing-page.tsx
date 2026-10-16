import { Linking, ScrollView, StyleSheet, TouchableOpacity, View, useWindowDimensions } from 'react-native';

import { BrandColors, Radius, Spacing } from '@/constants/theme';
import { getStageArtLoginUrl } from '@/api/site-routing';
import { ThemedText } from '@/components/themed-text';

type PublicLandingPageProps = {
  hostname: string;
};

const FEATURES = [
  {
    number: '01',
    title: '団体・公演をまとめて管理',
    description: '団体情報、公演情報、メンバーをひとつの場所で整理できます。',
  },
  {
    number: '02',
    title: '予定・稽古を共有',
    description: '稽古や会議などの予定を登録し、参加メンバーとの情報共有をスムーズに。',
  },
  {
    number: '03',
    title: 'チケット・受付を管理',
    description: '公演ごとのチケット情報や予約、受付に関わる業務をまとめて管理。',
  },
  {
    number: '04',
    title: '公演の公開ページ',
    description: '団体や公演の情報を一般公開ページとして発信。公演情報への入口をつくれます。',
  },
  {
    number: '05',
    title: 'アンケートで振り返る',
    description: '公演後のアンケートを活用して、お客様の声を次の活動につなげます。',
  },
  {
    number: '06',
    title: '舞台活動の情報を一元化',
    description: '公演運営に必要な情報を整理し、関係者が確認しやすい環境をつくります。',
  },
] as const;

export function PublicLandingPage({ hostname }: PublicLandingPageProps) {
  const loginUrl = getStageArtLoginUrl(hostname);
  const { width } = useWindowDimensions();
  const compact = width < 760;
  const narrow = width < 520;
  const pageGutter = narrow ? 20 : compact ? 28 : Spacing.five;
  const featureWidth = narrow ? '100%' : compact ? '47%' : '31.5%';

  return (
    <ScrollView
      testID="stageart-public-landing"
      style={styles.page}
      contentContainerStyle={styles.pageContent}
    >
      <View style={[styles.header, { paddingHorizontal: pageGutter }]}>
        <View style={styles.brandRow}>
          <View style={styles.brandMark}>
            <ThemedText style={styles.brandMarkText}>S</ThemedText>
          </View>
          <ThemedText style={styles.brandName}>StageArt</ThemedText>
        </View>
        <TouchableOpacity
          accessibilityRole="link"
          testID="stageart-landing-login-header"
          onPress={() => void Linking.openURL(loginUrl)}
          style={styles.headerLogin}
        >
          <ThemedText style={styles.headerLoginText}>ログイン</ThemedText>
        </TouchableOpacity>
      </View>

      <View style={[styles.hero, { paddingHorizontal: pageGutter, flexDirection: compact ? 'column' : 'row', alignItems: compact ? 'stretch' : 'center', paddingVertical: compact ? 24 : 42, minHeight: compact ? 0 : 450 }]}>
        <View style={[styles.heroCopy, { flex: compact ? 0 : 1.1 }]}>
          <View style={styles.eyebrow}>
            <View style={styles.eyebrowLine} />
            <ThemedText style={styles.eyebrowText}>THE STAGE, CONNECTED</ThemedText>
          </View>
          <ThemedText testID="stageart-landing-headline" style={[styles.headline, { fontSize: narrow ? 32 : compact ? 36 : 42, lineHeight: narrow ? 43 : compact ? 48 : 56 }]}>
            舞台をつくる人の、{ '\\n' }毎日をもっとスムーズに。
          </ThemedText>
          <ThemedText style={styles.heroDescription}>
            団体運営から公演準備、稽古、チケット、観客とのつながりまで。舞台活動に必要な情報と仕事を、StageArtでひとつにつなぎます。
          </ThemedText>
          <View style={styles.heroActions}>
            <TouchableOpacity
              accessibilityRole="link"
              testID="stageart-landing-login-primary"
              onPress={() => void Linking.openURL(loginUrl)}
              style={styles.primaryButton}
            >
              <ThemedText style={styles.primaryButtonText}>StageArtにログイン</ThemedText>
              <ThemedText style={styles.primaryArrow}>→</ThemedText>
            </TouchableOpacity>
            <ThemedText style={styles.heroFootnote}>舞台活動の管理を、ひとつの場所から。</ThemedText>
          </View>
        </View>
        <View style={[styles.heroVisual, { width: compact ? '100%' : undefined, minWidth: compact ? 0 : 260, height: narrow ? 250 : 320, flex: compact ? 0 : 0.9 }]} accessibilityLabel="舞台の照明をイメージした装飾">
          <View style={styles.lightBeamOne} />
          <View style={styles.lightBeamTwo} />
          <View style={styles.stageCircle} />
          <View style={styles.stageLine} />
          <View style={styles.stageLabel}>
            <ThemedText style={styles.stageLabelSmall}>YOUR NEXT</ThemedText>
            <ThemedText style={styles.stageLabelLarge}>ACT</ThemedText>
          </View>
        </View>
      </View>

      <View style={[styles.introSection, { paddingHorizontal: pageGutter }]}>
        <ThemedText style={styles.sectionEyebrow}>BUILT FOR STAGE PEOPLE</ThemedText>
        <ThemedText style={styles.sectionTitle}>舞台づくりに必要なことを、ひとつに。</ThemedText>
        <ThemedText style={styles.sectionDescription}>
          情報がいろいろな場所に散らばると、準備や共有に手間がかかります。StageArtは、舞台に関わる人たちの活動をつなぐための管理プラットフォームです。
        </ThemedText>
      </View>

      <View style={[styles.features, { paddingHorizontal: pageGutter }]}>
        {FEATURES.map((feature) => (
          <View key={feature.number} style={[styles.featureCard, { width: featureWidth }]}>
            <ThemedText style={styles.featureNumber}>{feature.number}</ThemedText>
            <ThemedText style={styles.featureTitle}>{feature.title}</ThemedText>
            <ThemedText style={styles.featureDescription}>{feature.description}</ThemedText>
          </View>
        ))}
      </View>

      <View style={[styles.closingCta, { paddingHorizontal: pageGutter }]}>
        <ThemedText style={styles.closingEyebrow}>READY FOR YOUR NEXT ACT?</ThemedText>
        <ThemedText style={styles.closingTitle}>次の舞台を、もっといい体験に。</ThemedText>
        <ThemedText style={styles.closingDescription}>
          StageArtにログインして、団体や公演の管理を始めましょう。
        </ThemedText>
        <TouchableOpacity
          accessibilityRole="link"
          testID="stageart-landing-login-footer"
          onPress={() => void Linking.openURL(loginUrl)}
          style={styles.primaryButton}
        >
          <ThemedText style={styles.primaryButtonText}>ログインする</ThemedText>
          <ThemedText style={styles.primaryArrow}>→</ThemedText>
        </TouchableOpacity>
      </View>

      <View style={[styles.footer, { paddingHorizontal: pageGutter, flexDirection: narrow ? 'column' : 'row', justifyContent: narrow ? 'center' : 'space-between', alignItems: narrow ? 'flex-start' : 'center', paddingVertical: narrow ? 22 : 0, gap: 8 }]}>
        <ThemedText style={styles.footerBrand}>StageArt</ThemedText>
        <ThemedText style={styles.footerText}>舞台活動を支えるプラットフォーム</ThemedText>
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  page: {
    flex: 1,
    backgroundColor: '#F8F6F1',
  },
  pageContent: {
    alignItems: 'center',
    paddingBottom: 0,
  },
  header: {
    width: '100%',
    maxWidth: 1180,
    minHeight: 82,
    paddingHorizontal: Spacing.five,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  brandRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
  },
  brandMark: {
    width: 36,
    height: 36,
    borderRadius: 18,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: BrandColors.blackoutBlack,
    borderWidth: 1,
    borderColor: BrandColors.warmGold,
  },
  brandMarkText: {
    color: BrandColors.warmGold,
    fontFamily: 'serif',
    fontSize: 22,
    fontWeight: '700',
  },
  brandName: {
    color: BrandColors.blackoutBlack,
    fontSize: 23,
    fontWeight: '700',
    letterSpacing: 0.5,
  },
  headerLogin: {
    borderWidth: 1,
    borderColor: '#B8A47F',
    borderRadius: Radius.medium,
    paddingHorizontal: 22,
    paddingVertical: 10,
  },
  headerLoginText: {
    color: '#31291D',
    fontSize: 14,
    fontWeight: '700',
  },
  hero: {
    width: '100%',
    maxWidth: 1180,
    minHeight: 450,
    paddingHorizontal: Spacing.five,
    paddingVertical: 42,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 24,
  },
  heroCopy: {
    flex: 1.1,
    minWidth: 0,
    paddingVertical: 24,
  },
  eyebrow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    marginBottom: 24,
  },
  eyebrowLine: {
    width: 32,
    height: 2,
    backgroundColor: BrandColors.warmAmber,
  },
  eyebrowText: {
    color: '#89652C',
    fontSize: 12,
    fontWeight: '700',
    letterSpacing: 2,
  },
  headline: {
    color: BrandColors.blackoutBlack,
    fontSize: 42,
    lineHeight: 1.32,
    fontWeight: '700',
    letterSpacing: 0.4,
  },
  heroDescription: {
    color: '#575149',
    fontSize: 16,
    lineHeight: 29,
    marginTop: 20,
    maxWidth: 590,
  },
  heroActions: {
    alignItems: 'flex-start',
    marginTop: 28,
    gap: 12,
  },
  primaryButton: {
    minHeight: 54,
    paddingHorizontal: 24,
    paddingVertical: 15,
    borderRadius: Radius.medium,
    backgroundColor: BrandColors.blackoutBlack,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 22,
  },
  primaryButtonText: {
    color: '#FFF8EA',
    fontSize: 15,
    fontWeight: '700',
  },
  primaryArrow: {
    color: BrandColors.warmGold,
    fontSize: 19,
    fontWeight: '700',
  },
  heroFootnote: {
    color: '#71695D',
    fontSize: 12,
  },
  heroVisual: {
    flex: 0.9,
    minWidth: 260,
    height: 360,
    overflow: 'hidden',
    borderRadius: 28,
    backgroundColor: BrandColors.blackoutBlack,
    alignItems: 'center',
    justifyContent: 'center',
  },
  lightBeamOne: {
    position: 'absolute',
    top: -80,
    left: '16%',
    width: '35%',
    height: 420,
    backgroundColor: 'rgba(226,177,90,0.14)',
    transform: [{ rotate: '23deg' }],
  },
  lightBeamTwo: {
    position: 'absolute',
    top: -60,
    right: '15%',
    width: '28%',
    height: 400,
    backgroundColor: 'rgba(240,208,138,0.10)',
    transform: [{ rotate: '-18deg' }],
  },
  stageCircle: {
    position: 'absolute',
    width: 230,
    height: 230,
    borderRadius: 115,
    backgroundColor: 'rgba(198,137,43,0.12)',
    borderWidth: 1,
    borderColor: 'rgba(226,177,90,0.32)',
  },
  stageLine: {
    position: 'absolute',
    bottom: 72,
    width: '72%',
    height: 2,
    backgroundColor: BrandColors.warmGold,
    opacity: 0.8,
  },
  stageLabel: {
    alignItems: 'center',
    justifyContent: 'center',
  },
  stageLabelSmall: {
    color: BrandColors.warmGold,
    fontSize: 12,
    fontWeight: '700',
    letterSpacing: 4,
  },
  stageLabelLarge: {
    color: '#FFF5DE',
    fontSize: 64,
    fontWeight: '800',
    letterSpacing: 8,
  },
  introSection: {
    width: '100%',
    maxWidth: 1180,
    paddingHorizontal: Spacing.five,
    paddingTop: 48,
    paddingBottom: 28,
  },
  sectionEyebrow: {
    color: '#89652C',
    fontSize: 12,
    fontWeight: '700',
    letterSpacing: 2,
  },
  sectionTitle: {
    color: BrandColors.blackoutBlack,
    fontSize: 30,
    lineHeight: 40,
    fontWeight: '700',
    marginTop: 12,
  },
  sectionDescription: {
    color: '#575149',
    fontSize: 15,
    lineHeight: 27,
    maxWidth: 760,
    marginTop: 14,
  },
  features: {
    width: '100%',
    maxWidth: 1180,
    paddingHorizontal: Spacing.five,
    paddingTop: 12,
    paddingBottom: 64,
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 16,
  },
  featureCard: {
    width: '31.5%',
    minWidth: 230,
    flexGrow: 1,
    padding: 24,
    borderRadius: Radius.large,
    backgroundColor: '#FFFFFF',
    borderWidth: 1,
    borderColor: '#E9E1D4',
    minHeight: 174,
  },
  featureNumber: {
    color: BrandColors.warmAmber,
    fontSize: 12,
    fontWeight: '800',
    letterSpacing: 1.5,
  },
  featureTitle: {
    color: BrandColors.blackoutBlack,
    fontSize: 18,
    lineHeight: 26,
    fontWeight: '700',
    marginTop: 15,
  },
  featureDescription: {
    color: '#655D52',
    fontSize: 14,
    lineHeight: 23,
    marginTop: 10,
  },
  closingCta: {
    width: '100%',
    paddingHorizontal: Spacing.five,
    paddingVertical: 58,
    alignItems: 'center',
    backgroundColor: '#EFE7D9',
  },
  closingEyebrow: {
    color: '#89652C',
    fontSize: 12,
    fontWeight: '700',
    letterSpacing: 2,
    textAlign: 'center',
  },
  closingTitle: {
    color: BrandColors.blackoutBlack,
    fontSize: 30,
    lineHeight: 40,
    fontWeight: '700',
    textAlign: 'center',
    marginTop: 12,
  },
  closingDescription: {
    color: '#575149',
    fontSize: 15,
    lineHeight: 26,
    textAlign: 'center',
    marginTop: 12,
    marginBottom: 24,
  },
  footer: {
    width: '100%',
    minHeight: 92,
    paddingHorizontal: Spacing.five,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    backgroundColor: BrandColors.blackoutBlack,
  },
  footerBrand: {
    color: BrandColors.warmGold,
    fontSize: 18,
    fontWeight: '700',
  },
  footerText: {
    color: '#D6C9B0',
    fontSize: 12,
  },
});
