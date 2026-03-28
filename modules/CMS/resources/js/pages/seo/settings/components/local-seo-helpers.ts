import type { LocalSeoFormValues } from '../../../../types/seo';

export function optionalUrlValidator(label: string) {
    return (value: string) => {
        if (value.trim() === '') {
            return undefined;
        }

        try {
            new URL(value);

            return undefined;
        } catch {
            return `${label} must be a valid URL.`;
        }
    };
}

export function buildScore(values: LocalSeoFormValues): {
    score: number;
    grade: string;
    completed: number;
    total: number;
} {
    const requiredFields = [
        values.name,
        values.description,
        values.url,
        values.phone,
        values.email,
        values.street_address,
        values.locality,
        values.region,
        values.postal_code,
        values.country_code,
        values.facebook_url,
        values.twitter_url,
        values.linkedin_url,
        values.instagram_url,
        values.youtube_url,
    ];

    const total = requiredFields.length + 2;
    const completed =
        requiredFields.filter((value) => value.trim() !== '').length +
        (values.logo_image ? 1 : 0) +
        (values.is_opening_hour_24_7 ||
        values.opening_hour_day.some((day) => day.trim() !== '')
            ? 1
            : 0);

    const score = Math.round((completed / total) * 100);
    const grade =
        score >= 90
            ? 'A'
            : score >= 75
              ? 'B'
              : score >= 60
                ? 'C'
                : score >= 40
                  ? 'D'
                  : 'F';

    return { score, grade, completed, total };
}
