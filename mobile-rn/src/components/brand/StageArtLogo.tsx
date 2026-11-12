import Svg, { Ellipse, G, Path, Text as SvgText } from 'react-native-svg';

type Props = {
  width?: number;
  height?: number;
};

/**
 * The canonical logo lockup (icon + wordmark, no tagline - the tagline
 * is reserved for large brand-material display, not this space-
 * constrained header/sidebar lockup, per docs/03-BrandIdentity.md's
 * usage table), transcribed verbatim from docs/assets/brand/stageart-logo.svg
 * - never redrawn by hand. Intentionally has no black background Rect
 * of its own (unlike StageArtIcon) - callers place this over whatever
 * dark surface they already use, matching this lockup's prior
 * transparent-background behavior.
 */
export function StageArtLogo({ width = 280, height = 84 }: Props) {
  return (
    <Svg width={width} height={height} viewBox="0 0 1400 420" role="img" aria-label="StageArt logo">
      <G transform="translate(28 24)" fill="none" strokeLinecap="round" strokeLinejoin="round">
        <Path
          d="M247 72C221 51 179 45 145 59C107 75 99 111 128 133C154 153 205 154 226 178C247 202 227 240 189 254C156 266 119 255 97 233"
          stroke="#E8D7B8"
          strokeWidth={16}
        />
        <Path d="M122 266L186 83L250 266M146 197H226M158 158H214" stroke="#C89B5E" strokeWidth={15} strokeLinecap="square" />
      </G>
      <Ellipse cx={214} cy={306} rx={105} ry={9} fill="#C89B5E" opacity={0.35} />
      <SvgText x={430} y={235} fill="#C89B5E" fontFamily="Georgia, 'Times New Roman', serif" fontSize={170} letterSpacing={-5}>
        StageArt
      </SvgText>
    </Svg>
  );
}
