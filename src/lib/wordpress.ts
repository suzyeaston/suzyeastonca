export type WpProject = {
  id: number;
  slug: string;
  title: { rendered: string };
  excerpt?: { rendered: string };
  acf?: Record<string, unknown>;
};

const WP_BASE_URL = import.meta.env.PUBLIC_WP_BASE_URL ?? 'https://suzyeaston.ca/wp-json/wp/v2';

export async function fetchFeaturedProjects(): Promise<WpProject[]> {
  const url = new URL(`${WP_BASE_URL}/projects`);
  url.searchParams.set('per_page', '3');
  url.searchParams.set('_fields', 'id,slug,title,excerpt,acf');

  const response = await fetch(url, { headers: { accept: 'application/json' } });
  if (!response.ok) return [];
  return response.json();
}
