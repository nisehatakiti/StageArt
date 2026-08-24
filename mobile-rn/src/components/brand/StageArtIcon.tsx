import Svg, { Circle, Path } from 'react-native-svg';

type Props = {
  size?: number;
};

/**
 * StageArt Web First Phase 1: the canonical app icon, transcribed
 * verbatim from docs/assets/brand/stageart-icon.svg into react-native-svg
 * primitives (paths/circle/coordinates unchanged) - never redrawn by
 * hand, per docs/03-BrandIdentity.md's explicit instruction. Beige
 * circular field (#E8D7B8) with the black S + ladder-like A mark
 * (#0A0A0A).
 */
export function StageArtIcon({ size = 96 }: Props) {
  return (
    <Svg width={size} height={size} viewBox="0 0 512 512" role="img" aria-label="StageArt app icon">
      <Circle cx={256} cy={256} r={236} fill="#E8D7B8" />
      <Path
        d="M344 116C312 87 253 78 204 99C150 122 139 174 181 205C218 232 291 234 321 269C351 304 322 359 267 379C220 396 167 380 136 349"
        fill="none"
        stroke="#0A0A0A"
        strokeWidth={22}
        strokeLinecap="round"
        strokeLinejoin="round"
      />
      <Path
        d="M171 394L256 150L341 394"
        fill="none"
        stroke="#0A0A0A"
        strokeWidth={20}
        strokeLinecap="square"
        strokeLinejoin="round"
      />
      <Path d="M203 302H309M220 250H292" fill="none" stroke="#0A0A0A" strokeWidth={16} strokeLinecap="square" />
    </Svg>
  );
}
