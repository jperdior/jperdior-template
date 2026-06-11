import { describe, it, expect } from 'vitest';
import { render, screen } from '@testing-library/react';
import NotFound from '../not-found';

describe('smoke', () => {
  it('renders a trivial element via RTL', () => {
    render(<p>hello vitest</p>);
    expect(screen.getByText('hello vitest')).toBeInTheDocument();
  });

  it('renders the 404 page with a back-home link', () => {
    render(<NotFound />);
    expect(screen.getByRole('heading', { name: '404' })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Back home' })).toHaveAttribute('href', '/');
  });
});
