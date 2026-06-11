import { describe, it, expect } from 'vitest';
import { render, screen } from '@testing-library/react';
import { PaginationControls } from '../PaginationControls';

describe('PaginationControls', () => {
  it('renders nothing when total fits in one page', () => {
    const { container } = render(<PaginationControls total={5} offset={0} limit={10} />);
    expect(container).toBeEmptyDOMElement();
  });

  it('shows the page range and a Next link on the first page', () => {
    render(<PaginationControls total={42} offset={0} limit={10} />);
    expect(screen.getByText(/Showing 1–10 of 42/)).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Next' })).toHaveAttribute('href', '?offset=10');
  });

  it('shows a Previous link when not on the first page', () => {
    render(<PaginationControls total={42} offset={20} limit={10} />);
    expect(screen.getByRole('link', { name: 'Previous' })).toHaveAttribute('href', '?offset=10');
    expect(screen.getByRole('link', { name: 'Next' })).toHaveAttribute('href', '?offset=30');
  });
});
