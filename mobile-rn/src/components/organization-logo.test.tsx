import { fireEvent, render, screen } from '@testing-library/react-native';
import { StyleSheet } from 'react-native';

import { imageSizeFromLoadEvent, OrganizationLogo } from './organization-logo';

/** Renders the logo and reports its image as loaded at width x height (native onLoad shape). */
async function frameAfterLoad(width: number, height: number) {
  await render(<OrganizationLogo uri={`https://example.com/${width}x${height}.png`} testID="logo" frameTestID="frame" />);
  await fireEvent(screen.getByTestId('logo'), 'load', { nativeEvent: { source: { width, height } } });

  return StyleSheet.flatten(screen.getByTestId('frame').props.style);
}

/**
 * 団体ロゴ: the whole image (contain), in a box sized from its own aspect
 * ratio - wide, tall and square logos are neither cropped nor squeezed
 * into a fixed square. (The jest window is 750px wide: not a narrow screen.)
 */
describe('OrganizationLogo', () => {
  it('fits a wide logo to the maximum width', async () => {
    const frame = await frameAfterLoad(800, 200);

    expect(frame).toMatchObject({ width: 480, aspectRatio: 4, maxWidth: '100%' });
    expect(screen.getByTestId('logo').props.resizeMode).toBe('contain');
  });

  it('fits a tall logo to the maximum height', async () => {
    // 160px high -> 80px wide
    expect(await frameAfterLoad(300, 600)).toMatchObject({ width: 80, aspectRatio: 0.5 });
  });

  it('shows a square logo at 160x160 (was 120x120)', async () => {
    expect(await frameAfterLoad(500, 500)).toMatchObject({ width: 160, aspectRatio: 1 });
  });

  it('keeps a very wide logo within the maximum width, never stretched', async () => {
    // 6:1 at 160px high would be 960px wide: capped at 480 (80px high), same ratio
    expect(await frameAfterLoad(1200, 200)).toMatchObject({ width: 480, aspectRatio: 6 });
  });

  it('uses a square box until the image size is known', async () => {
    await render(<OrganizationLogo uri="https://example.com/unknown.png" testID="logo" frameTestID="frame" />);

    expect(StyleSheet.flatten(screen.getByTestId('frame').props.style)).toMatchObject({ width: 160, aspectRatio: 1 });
  });

  it('reads the size from native and react-native-web load events', () => {
    expect(imageSizeFromLoadEvent({ nativeEvent: { source: { width: 400, height: 100 } } })).toEqual({ width: 400, height: 100 });
    expect(imageSizeFromLoadEvent({ nativeEvent: { target: { naturalWidth: 90, naturalHeight: 180 } } })).toEqual({ width: 90, height: 180 });
    expect(imageSizeFromLoadEvent({ nativeEvent: {} })).toBeNull();
  });
});
