// Jeu d'icônes SVG inline (aucune dépendance, aucun appel réseau).

const paths = {
  check: 'M4 10.5 8 14.5 16 6',
  arrow: 'M4 10h12M11 5l5 5-5 5',
  user: 'M10 10a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Zm-6 7c0-3 2.7-5 6-5s6 2 6 5',
  shield: 'M10 2.5 3.5 5v4.6c0 4 2.8 7 6.5 7.9 3.7-.9 6.5-3.9 6.5-7.9V5L10 2.5Z',
  server: 'M3.5 4h13v5h-13zM3.5 11h13v5h-13zM6.5 6.5h.01M6.5 13.5h.01',
  cloud: 'M6 15.5h8.5a3.5 3.5 0 0 0 .4-7A5 5 0 0 0 5.2 9.4 3 3 0 0 0 6 15.5Z',
  bolt: 'M11 2.5 4.5 11H10l-1 6.5L15.5 9H10l1-6.5Z',
  log: 'M5 3.5h10v13H5zM7.5 7h5M7.5 10h5M7.5 13h3',
  voice: 'M10 2.5a2.5 2.5 0 0 0-2.5 2.5v5a2.5 2.5 0 0 0 5 0V5A2.5 2.5 0 0 0 10 2.5ZM5 9.5a5 5 0 0 0 10 0M10 14.5v3',
  eye: 'M1.5 10S4.5 4.5 10 4.5 18.5 10 18.5 10 15.5 15.5 10 15.5 1.5 10 1.5 10Zm8.5 2.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z',
  license: 'M4 3.5h12v13H4zM7 7h6M7 10h6M10 13.5l1.5 1.5L14 12.5',
  sun: 'M10 13.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7ZM10 1.5v2M10 16.5v2M1.5 10h2M16.5 10h2M4 4l1.4 1.4M14.6 14.6 16 16M4 16l1.4-1.4M14.6 5.4 16 4',
  moon: 'M16.5 12A7 7 0 0 1 8 3.5a7 7 0 1 0 8.5 8.5Z',
} as const;

export type IconName = keyof typeof paths;

export function Icon({ name, size = 18 }: { name: IconName; size?: number }) {
  return (
    <svg
      width={size}
      height={size}
      viewBox="0 0 20 20"
      fill="none"
      stroke="currentColor"
      strokeWidth={1.6}
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
      focusable="false"
    >
      <path d={paths[name]} />
    </svg>
  );
}
