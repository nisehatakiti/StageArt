import Svg, { Circle, G, Path, Text as SvgText } from 'react-native-svg';

type Props = {
  width?: number;
  height?: number;
};

/**
 * StageArt Web First Phase 1: the canonical logo lockup (icon + wordmark),
 * transcribed verbatim from docs/assets/brand/stageart-logo.svg - never
 * redrawn by hand, per docs/03-BrandIdentity.md's explicit instruction.
 */
export function StageArtLogo({ width = 280, height = 84 }: Props) {
  return (
    <Svg width={width} height={height} viewBox="0 0 1400 420" role="img" aria-label="StageArt logo">
      <G transform="translate(24 24)">
        <Circle cx={186} cy={186} r={162} fill="#E8D7B8" />
        <Path
          d="M247 72C221 51 179 45 145 59C107 75 99 111 128 133C154 153 205 154 226 178C247 202 227 240 189 254C156 266 119 255 97 233"
          fill="none"
          stroke="#0A0A0A"
          strokeWidth={16}
          strokeLinecap="round"
          strokeLinejoin="round"
        />
        <Path
          d="M122 266L186 83L250 266"
          fill="none"
          stroke="#0A0A0A"
          strokeWidth={15}
          strokeLinecap="square"
          strokeLinejoin="round"
        />
        <Path d="M146 197H226M158 158H214" fill="none" stroke="#0A0A0A" strokeWidth={12} strokeLinecap="square" />
      </G>
      <SvgText x={430} y={235} fill="#0A0A0A" fontFamily="Georgia, 'Times New Roman', serif" fontSize={170} letterSpacing={-5}>
        StageArt
      </SvgText>
    </Svg>
  );
}
