import Svg, { Ellipse, G, Path, Rect } from 'react-native-svg';

type Props = {
  size?: number;
};

/**
 * The canonical app icon, transcribed verbatim from
 * docs/assets/brand/stageart-icon.svg into react-native-svg primitives
 * (paths/coordinates/colors unchanged) - never redrawn by hand, per
 * docs/03-BrandIdentity.md's explicit instruction. Black field (#050505)
 * with the beige S (#E8D7B8) wrapping the bronze ladder-like A
 * (#C89B5E).
 */
export function StageArtIcon({ size = 96 }: Props) {
  return (
    <Svg width={size} height={size} viewBox="0 0 512 512" role="img" aria-label="StageArt app icon">
      <Rect width={512} height={512} fill="#050505" />
      <G fill="none" strokeLinecap="round" strokeLinejoin="round">
        <Path
          d="M352 108C316 80 258 78 211 99C160 122 151 171 191 202C228 230 298 234 325 268C353 303 325 355 273 376C223 397 171 382 137 349"
          stroke="#E8D7B8"
          strokeWidth={23}
        />
        <Path
          d="M171 401L256 151L341 401M201 306H311M220 251H292"
          stroke="#C89B5E"
          strokeWidth={20}
          strokeLinecap="square"
        />
      </G>
      <Ellipse cx={256} cy={411} rx={150} ry={13} fill="#C89B5E" opacity={0.35} />
    </Svg>
  );
}
