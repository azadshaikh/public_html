import { FieldLabel } from '@/components/ui/field';

export function slugify(value: string): string {
    return value
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9\s-]/g, '')
        .replace(/\s+/g, '-')
        .replace(/-+/g, '-')
        .replace(/^-+/, '')
        .replace(/-+$/, '');
}

export function buildPermalink(
    baseUrl: string,
    preSlug: string,
    slug: string,
): string {
    const base = baseUrl.replace(/\/$/, '');
    const cleanedSlug = slug.trim() === '' ? 'your-slug-here' : slug.trim();

    if (preSlug === '/' || preSlug.trim() === '') {
        return `${base}/${cleanedSlug}`;
    }

    const prefix = preSlug.replace(/^\/+|\/+$/g, '');

    return `${base}/${prefix}/${cleanedSlug}`;
}

export function RequiredLabel({
    htmlFor,
    children,
}: {
    htmlFor?: string;
    children: string;
}) {
    return (
        <FieldLabel htmlFor={htmlFor}>
            {children} <span className="text-destructive">*</span>
        </FieldLabel>
    );
}
