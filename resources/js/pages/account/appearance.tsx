import AppearanceTabs from '@/components/appearance-tabs';
import Heading from '@/components/heading';
import AccountLayout from '@/layouts/account/layout';
import AppLayout from '@/layouts/app-layout';
import { edit as editAppearance } from '@/routes/appearance';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Appearance',
    href: editAppearance(),
  },
];

export default function Appearance() {
  return (
    <AppLayout
      breadcrumbs={breadcrumbs}
      title="Appearance"
      description="Choose the color theme and appearance settings for your account."
    >
      <AccountLayout>
        <div className="space-y-6">
          <Heading
            variant="small"
            title="Theme mode"
            description="Switch between light, dark, or system appearance."
          />
          <AppearanceTabs />
        </div>
      </AccountLayout>
    </AppLayout>
  );
}
