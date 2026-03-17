import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import SecretForm from '../../../components/secrets/secret-form';
import type { PlatformOption, SecretFormValues } from '../../../types/platform';

type SecretsEditPageProps = {
    secret: {
        id: number;
        key: string;
    };
    initialValues: SecretFormValues;
    typeOptions: PlatformOption[];
    secretableTypeOptions: PlatformOption[];
};

export default function SecretsEdit(props: SecretsEditPageProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: route('dashboard') },
        { title: 'Platform', href: route('platform.secrets.index', { status: 'all' }) },
        { title: 'Secrets', href: route('platform.secrets.index', { status: 'all' }) },
        { title: props.secret.key, href: route('platform.secrets.show', props.secret.id) },
        { title: 'Edit', href: route('platform.secrets.edit', props.secret.id) },
    ];

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={`Edit ${props.secret.key}`}
            description="Update the secret payload, assignment, or expiration schedule for this record."
        >
            <SecretForm
                mode="edit"
                secret={props.secret}
                initialValues={props.initialValues}
                typeOptions={props.typeOptions}
                secretableTypeOptions={props.secretableTypeOptions}
            />
        </AppLayout>
    );
}
