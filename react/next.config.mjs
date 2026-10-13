// Export statique servi par IIS sous /app (voir scripts/deploy.mjs).
// Aucune logique serveur Next.js : le PHP existant reste l'API.

/** @type {import('next').NextConfig} */
const nextConfig = {
  output: 'export',
  basePath: '/app',
  trailingSlash: true,
  images: { unoptimized: true },
  poweredByHeader: false,
  reactStrictMode: true,
  // ESLint n'est pas installé dans ce lot : la vérification passe par `npm run typecheck`.
  eslint: { ignoreDuringBuilds: true },
};

export default nextConfig;
