import React from 'react';

export interface SearchBarProps extends React.HTMLAttributes<HTMLDivElement> {
  /** Click handler for the BUSCAR button. */
  onSearch?: () => void;
}

/**
 * Hero property search bar: Operation · Type · Zone · Price + BUSCAR.
 * White floating card designed to overlap the cinematic hero.
 *
 * @startingPoint section="Real Estate" subtitle="Hero search bar" viewport="900x90"
 */
export function SearchBar(props: SearchBarProps): JSX.Element;
