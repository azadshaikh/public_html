import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import SecretForm from '../../../components/secrets/secret-form';
import type { PlatformOption, SecretFormValues } from '../../../types/platform';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    {
        title: 'Platform',
        href: route('platform.secrets.index', { status: 'all' }),
    },
    {
        title: 'Secrets',
        href: route('platform.secrets.index', { status: 'all' }),
    },
    { title: 'Create', href: route('platform.secrets.create') },
];

type SecretsCreatePageProps = {
    initialValues: SecretFormValues;
    typeOptions: PlatformOption[];
    secretableTypeOptions: PlatformOption[];
};

export default function SecretsCreate(props: SecretsCreatePageProps) {
    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Create secret"
            description="Create an encrypted secret and attach it to the correct platform entity."
        >
            <SecretForm
                mode="create"
                initialValues={props.initialValues}
                typeOptions={props.typeOptions}
                secretableTypeOptions={props.secretableTypeOptions}
            />
        </AppLayout>
    );
}
