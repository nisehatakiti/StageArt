import { Linking } from 'react-native';
import { fireEvent, render, screen } from '@testing-library/react-native';

import { PublicLandingPage } from '@/components/public-landing-page';

describe('StageArt public feature landing page', () => {
  beforeEach(() => {
    jest.spyOn(Linking, 'openURL').mockResolvedValue(undefined);
  });

  afterEach(() => {
    jest.restoreAllMocks();
  });

  it('shows the feature introduction and sends production visitors to app.stageart.top', async () => {
    await render(<PublicLandingPage hostname="stageart.top" />);

    expect(screen.getByTestId('stageart-landing-headline')).toBeVisible();
    expect(screen.getByText('団体・公演をまとめて管理')).toBeVisible();
    expect(screen.getByText('予定・稽古を共有')).toBeVisible();

    fireEvent.press(screen.getByTestId('stageart-landing-login-primary'));
    expect(Linking.openURL).toHaveBeenCalledWith('https://app.stageart.top/login/');
  });

  it('sends development public-site visitors to dev.stageart.top', async () => {
    await render(<PublicLandingPage hostname="dummy.stageart.top" />);

    fireEvent.press(screen.getByTestId('stageart-landing-login-header'));
    expect(Linking.openURL).toHaveBeenCalledWith('https://dev.stageart.top/login/');
  });
});
