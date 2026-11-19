import { fireEvent, render, screen } from '@testing-library/react-native';
import { StyleSheet } from 'react-native';

import { heroLayout, PublicPageHero } from './public-page-hero';

const hero = { image_url: 'https://api.test/hero/organization/confetti.jpg', title: '', description: '' };

/**
 * Hero (StageArtCore .stageart-hero): the name as the main element, sized
 * from the Hero's own width, the copy centered vertically, and a minimum
 * height instead of a fixed aspect ratio, so long text grows the box.
 */
describe('PublicPageHero', () => {
  it('uses large, clearly tiered type on a desktop-width hero', () => {
    const wide = heroLayout(752);

    expect(wide.narrow).toBe(false);
    expect(wide.titleSize).toBe(60);
    expect(wide.descriptionSize).toBe(18);
    expect(wide.titleSize / wide.descriptionSize).toBeGreaterThan(3);
    expect(wide.minHeight).toBe(460);
    expect(heroLayout(1200).titleSize).toBe(84); // capped
  });

  it('switches to phone sizes below 600px, still well above the body text', () => {
    const phone = heroLayout(342);

    expect(phone).toMatchObject({ narrow: true, titleSize: 34, descriptionSize: 15, paddingHorizontal: 24, minHeight: 420 });
    expect(heroLayout(280).titleSize).toBe(30); // floor: never tiny
  });

  it('centers the copy, has no fixed height, and falls back to the page name and description', async () => {
    await render(<PublicPageHero testID="hero" hero={hero} fallbackTitle="StageArt劇団" fallbackDescription={'一行目\n二行目'} />);
    await fireEvent(screen.getByTestId('hero'), 'layout', { nativeEvent: { layout: { width: 342, height: 0, x: 0, y: 0 } } });

    const box = StyleSheet.flatten(screen.getByTestId('hero').props.style);
    expect(box.aspectRatio).toBeUndefined();
    expect(box.height).toBeUndefined();
    expect(box.minHeight).toBe(420);
    expect(StyleSheet.flatten(screen.getByTestId('hero-copy').props.style)).toMatchObject({ justifyContent: 'center', paddingHorizontal: 24 });

    const title = StyleSheet.flatten(screen.getByTestId('hero-title').props.style);
    expect(screen.getByTestId('hero-title')).toHaveTextContent('StageArt劇団');
    expect(title).toMatchObject({ fontSize: 34, color: '#fff', fontWeight: '700' });
    expect(screen.getByTestId('hero-title').props.numberOfLines).toBeUndefined();
    expect(screen.getByTestId('hero-description')).toHaveTextContent('一行目\n二行目');
    expect(screen.getByTestId('hero-description').props.numberOfLines).toBeUndefined();
    expect(screen.getByTestId('hero-image').props.resizeMode).toBe('cover');
  });
});
